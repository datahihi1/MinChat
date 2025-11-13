<?php
session_start();
$pdo = new PDO('sqlite:messages.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (!isset($_SESSION['user_minchat'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['logout'])) {
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/');
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_minchat']['id']]);
    }
    session_destroy();
    header('Location: login.php');
    exit;
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat Real-time</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
            font-family: Helvetica, Arial, sans-serif;
            background: #f4f4f4;
            overflow: hidden;
        }

        .container {
            max-width: 900px;
            margin: 10px auto;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
            height: 100%;
        }

        .header {
            padding: 12px 14px;
            background: #2b6ea3;
            color: #fff;
            overflow: hidden;
        }

        .header h1 {
            float: left;
            font-size: 18px;
            margin: 0;
        }

        .header .user-info {
            float: right;
            font-size: 14px;
            margin-top: 6px;
        }

        .header .logout-btn {
            display: inline-block;
            margin-left: 8px;
            padding: 6px 10px;
            background: #ff4d4d;
            color: #fff;
            text-decoration: none;
            font-size: 12px;
            border-radius: 4px;
        }

        .header::after {
            content: "";
            display: block;
            clear: both;
        }

        .chat {
            position: relative;
            padding-bottom: 60px;
            height: 100%;
        }

        .messages-wrapper {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 60px;
            overflow: hidden;
        }

        .messages {
            height: 100%;
            overflow-y: scroll;
            -webkit-overflow-scrolling: touch;
            padding: 12px;
        }

        .msg {
            margin-bottom: 10px;
            overflow: hidden;
        }

        .msg.own-msg {
            text-align: right;
        }

        .msg .meta {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .msg .text {
            display: inline-block;
            max-width: 85%;
            padding: 8px;
            border-radius: 5px;
            font-size: 14px;
            background: #eef6ff;
        }

        .msg.own-msg .text {
            background: #d1f7c4;
        }

        .form {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 8px;
            background: #fff;
            border-top: 1px solid #ccc;
            z-index: 1000;
        }

        .form input[type="text"] {
            width: 70%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .form button {
            width: 28%;
            padding: 8px;
            background: #2b6ea3;
            color: #fff;
            border: 0;
            border-radius: 4px;
            font-size: 14px;
        }

        .form::after {
            content: "";
            display: block;
            clear: both;
        }

        .footer-note {
            font-size: 12px;
            color: #666;
            padding: 6px 12px;
            background: #f9f9f9;
            border-top: 1px solid #eee;
        }

        @media (max-width:600px) {
            .container {
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .header {
                padding: 10px 8px;
            }

            .header h1 {
                font-size: 16px;
                float: none;
            }

            .header .user-info {
                float: none;
                text-align: left;
                margin-top: 4px;
                font-size: 13px;
            }

            .form input[type="text"] {
                font-size: 13px;
                padding: 7px;
            }

            .form button {
                font-size: 13px;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <h1>Chat Real-time</h1>
            <div class="user-info">
                Xin chào, <strong>
                    <?php echo !empty($_SESSION['user_minchat']['realname']) ? htmlspecialchars($_SESSION['user_minchat']['realname']) : htmlspecialchars($_SESSION['user_minchat']['username']); ?>
                </strong>
                <a href="?logout=true" class="logout-btn">Đăng xuất</a>
            </div>
        </div>

        <div class="chat">
            <div class="messages-wrapper">
                <div id="messages" class="messages"></div>
            </div>
            <form id="chatForm" class="form" onsubmit="return sendMessage();">
                <input type="text" id="text" placeholder="Gõ tin nhắn..." autocomplete="off">
                <button type="submit">Gửi</button>
            </form>
        </div>

        <div class="footer-note">
            Hỗ trợ iOS 6+, Android 2.3+ (long-polling)
        </div>
    </div>

    <script>
        var lastId = 0;
        var polling = true;
        var loadingOlderMessages = false;
        var messagesEl = document.getElementById('messages');
        var wrapperEl = document.querySelector('.messages-wrapper');

        // === Fix iOS 6: Kích hoạt scroll bằng touchstart ===
        messagesEl.addEventListener('touchstart', function (e) {
            // Không làm gì, chỉ cần event để iOS bật scroll
        }, false);

        // === Force scroll enable trên iOS 6 ===
        setTimeout(function () {
            messagesEl.style.webkitOverflowScrolling = 'touch';
            messagesEl.style.overflowY = 'scroll';
        }, 100);

        // === Escape HTML ===
        function escapeHtml(s) {
            if (!s) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(s));
            return div.innerHTML;
        }

        // === Render tin nhắn ===
        function renderMessages(msgs) {
            var frag = document.createDocumentFragment();
            for (var i = 0; i < msgs.length; i++) {
                var m = msgs[i];
                var div = document.createElement('div');
                div.className = 'msg';
                div.setAttribute('data-id', m.id);
                if (m.user_id == <?php echo (int) $_SESSION['user_minchat']['id']; ?>) {
                    div.className += ' own-msg';
                }

                var meta = document.createElement('div');
                meta.className = 'meta';
                var dt = new Date(m.ts * 1000);
                meta.innerHTML = escapeHtml(m.name) + ' · ' + dt.toLocaleString();

                var text = document.createElement('div');
                text.className = 'text';
                text.innerHTML = escapeHtml(m.text);

                div.appendChild(meta);
                div.appendChild(text);
                frag.appendChild(div);

                if (m.id > lastId) lastId = m.id;
            }
            messagesEl.appendChild(frag);

            // === Force scroll to bottom + repaint ===
            setTimeout(function () {
                messagesEl.scrollTop = messagesEl.scrollHeight + 100;
                messagesEl.scrollTop = messagesEl.scrollHeight;
                // Trigger reflow
                messagesEl.style.display = 'block';
            }, 50);
        }

        // === AJAX ===
        function ajaxGet(url, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try { cb(null, JSON.parse(xhr.responseText)); }
                        catch (e) { cb(e); }
                    } else {
                        cb(new Error('HTTP ' + xhr.status));
                    }
                }
            };
            xhr.send(null);
        }

        function ajaxPost(url, params, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function () {
                if (xhr.readyState == 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try { cb(null, JSON.parse(xhr.responseText)); }
                        catch (e) { cb(e); }
                    } else {
                        cb(new Error('HTTP ' + xhr.status));
                    }
                }
            };
            var body = [];
            for (var k in params) {
                if (params.hasOwnProperty(k)) {
                    body.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
                }
            }
            xhr.send(body.join('&'));
        }

        // === Poll ===
        function poll() {
            if (!polling) return;
            ajaxGet('api.php?action=fetch&last_id=' + lastId, function (err, data) {
                if (!err && data && data.ok && data.messages && data.messages.length) {
                    renderMessages(data.messages);
                    setTimeout(poll, 200);
                } else {
                    setTimeout(poll, 2000);
                }
            });
        }

        // === Send ===
        function sendMessage() {
            var input = document.getElementById('text');
            var text = input.value.replace(/^\s+|\s+$/g, '');
            if (!text) return false;
            ajaxPost('api.php?action=send', { text: text }, function (err, data) {
                if (!err && data && data.ok) {
                    input.value = '';
                } else {
                    alert('Lỗi gửi');
                }
            });
            return false;
        }

        // === Load old messages ===
        function debounce(func, wait) {
            var timeout;
            return function () {
                clearTimeout(timeout);
                timeout = setTimeout(func, wait);
            };
        }

        messagesEl.addEventListener('scroll', debounce(function () {
            if (messagesEl.scrollTop < 100 && !loadingOlderMessages && messagesEl.firstChild) {
                loadingOlderMessages = true;
                var firstId = messagesEl.firstChild.getAttribute('data-id');
                if (!firstId || firstId <= 0) { loadingOlderMessages = false; return; }

                ajaxGet('api.php?action=fetch&before_id=' + firstId, function (err, data) {
                    if (!err && data && data.ok && data.messages && data.messages.length) {
                        var oldHeight = messagesEl.scrollHeight;
                        var frag = document.createDocumentFragment();
                        for (var i = 0; i < data.messages.length; i++) {
                            var m = data.messages[i];
                            var div = document.createElement('div');
                            div.className = 'msg' + (m.user_id == <?php echo (int) $_SESSION['user_minchat']['id']; ?> ? ' own-msg' : '');
                            div.setAttribute('data-id', m.id);

                            var meta = document.createElement('div');
                            meta.className = 'meta';
                            var dt = new Date(m.ts * 1000);
                            meta.innerHTML = escapeHtml(m.name) + ' · ' + dt.toLocaleString();

                            var text = document.createElement('div');
                            text.className = 'text';
                            text.innerHTML = escapeHtml(m.text);

                            div.appendChild(meta);
                            div.appendChild(text);
                            frag.appendChild(div);
                        }
                        messagesEl.insertBefore(frag, messagesEl.firstChild);
                        messagesEl.scrollTop = messagesEl.scrollHeight - oldHeight;
                    }
                    loadingOlderMessages = false;
                });
            }
        }, 300));

        // === Khởi động ===
        (function () {
            // Fix chiều cao wrapper
            function resize() {
                var h = window.innerHeight || document.documentElement.clientHeight;
                var headerH = document.querySelector('.header').offsetHeight || 50;
                var formH = 60;
                var wrapperH = h - headerH - formH - 20;
                wrapperEl.style.height = wrapperH + 'px';
            }
            resize();
            window.addEventListener('resize', resize);

            // Load tin nhắn đầu
            ajaxGet('api.php?action=fetch&last_id=0', function (err, data) {
                if (!err && data && data.ok) {
                    renderMessages(data.messages || []);
                }
                poll();
            });
        })();
    </script>

</body>

</html>
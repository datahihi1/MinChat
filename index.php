<?php
// Kiểm tra người dùng đã đăng nhập hay chưa
session_start();

// Kết nối đến SQLite database
$pdo = new PDO('sqlite:messages.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// var_dump($_COOKIE['remember_me'] ?? 'no remember_me cookie');
if (!isset($_SESSION['user_minchat'])) {
    header('Location: login.php');
    exit;
}

// Xử lý đăng xuất
if (isset($_GET['logout'])) {
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, '/'); // Xóa cookie
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
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chat Real-time (Long-polling) - Simple</title>
    <style>
        /* Basic responsive layout supporting 320px - 1080px.
   Sử dụng CSS đơn giản để tương thích trình duyệt cũ. */
        html,
        body {
            height: 100%;
            margin: 0;
            font-family: Helvetica, Arial, sans-serif;
            background: #f4f4f4
        }

        .container {
            max-width: 900px;
            margin: 10px auto;
            border-radius: 6px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1)
        }

        .header {
            padding: 12px 14px;
            background: #2b6ea3;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .header h1 {
            margin: 0;
            font-size: 18px
        }

        .header .user-info {
            font-size: 14px;
            margin-top: 8px;
        }

        .header .logout-btn {
            padding: 6px 10px;
            border: 0;
            border-radius: 4px;
            background: #ff4d4d;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none
        }

        .chat {
            display: block;
            height: 70vh;
            min-height: 320px;
            position: relative;
        }

        @supports (display: flex) {
            .chat {
                display: flex;
                flex-direction: column;
            }
        }

        .messages {
            overflow: auto;
            padding: 12px;
            -webkit-overflow-scrolling: touch;
            height: calc(100% - 60px);
            box-sizing: border-box;
        }

        @supports (display: flex) {
            .messages {
                flex: 1;
                height: auto;
            }
        }

        .msg {
            margin-bottom: 10px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .msg.own-msg {
            align-items: flex-end;
            /* Tin nhắn của người dùng hiện tại căn phải */
        }

        .msg .meta {
            font-size: 12px;
            color: #666
        }

        .msg .text {
            font-size: 14px;
            background: #eef6ff;
            padding: 8px;
            border-radius: 5px;
            display: inline-block;
            max-width: 85%
        }

        .msg.own-msg .text {
            background: #d1f7c4;
            /* Màu khác cho tin nhắn của người dùng hiện tại */
        }

        .form {
            padding: 10px;
            border-top: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .form input[type="text"] {
            flex: 1 1 120px;
            min-width: 0;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px
        }

        .form button {
            padding: 8px 12px;
            border: 0;
            border-radius: 4px;
            background: #2b6ea3;
            color: #fff;
            min-width: 80px;
        }

        .footer-note {
            font-size: 12px;
            color: #666;
            padding: 6px 12px
        }

        /* Responsive for small screens */
        @media (max-width:600px) {
            .container {
                max-width: 98vw;
                margin: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px 8px;
            }

            .header h1 {
                font-size: 16px;
                margin-bottom: 4px;
            }

            .header .user-info {
                font-size: 13px;
                margin-top: 0;
            }

            .chat {
                min-height: 240px;
                height: 60vh;
            }

            .messages {
                padding: 8px;
            }

            .msg .text {
                font-size: 13px;
                max-width: 98%;
                padding: 6px;
            }

            .form {
                padding: 8px;
                gap: 6px;
            }

            .form button {
                min-width: 60px;
                font-size: 13px;
                padding: 7px 10px;
            }

            .footer-note {
                font-size: 11px;
                padding: 4px 8px;
            }
        }

        /* Extra small screens (320px+) */
        @media (max-width:400px) {
            .header h1 {
                font-size: 14px;
            }

            .msg .text {
                font-size: 12px;
                padding: 5px;
            }

            .form input[type="text"] {
                font-size: 12px;
                padding: 6px;
            }

            .form button {
                font-size: 12px;
                padding: 6px 8px;
            }
        }

        /* Cố định form nhập tin nhắn ở cuối màn hình trên thiết bị di động */
        @media (max-width:600px) {
            .form {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
                background: #fff;
                z-index: 1000;
                border-top: 1px solid #ccc;
            }

            .chat {
                padding-bottom: 60px;
                /* Chừa không gian cho form cố định */
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Chat Real-time (Long-polling)</h1>
            <div class="user-info">
                Xin chào, <strong>
                    <?php if (!empty($_SESSION['user_minchat']['realname'])): ?>
                        <?php echo htmlspecialchars($_SESSION['user_minchat']['realname']); ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($_SESSION['user_minchat']['username']); ?>
                    <?php endif; ?>
                </strong>
                <a href="?logout=true" class="logout-btn">Đăng xuất</a>
            </div>
        </div>
        <div class="chat">
            <div id="messages" class="messages" aria-live="polite"></div>
            <form id="chatForm" class="form" action="?action=send" method="post" onsubmit="return sendMessage();">
                <input type="text" id="text" name="text" class="message" placeholder="Gõ tin nhắn..."
                    autocomplete="off">
                <button type="submit">Gửi</button>
            </form>
            <div class="footer-note">Kỹ thuật: long-polling AJAX — tương thích trình duyệt cũ (Safari iOS6, Chrome
                30–40). Không yêu cầu WebSocket.</div>
        </div>
    </div>

    <script>
        var lastId = 0;
        var polling = true;
        var messagesEl = document.getElementById('messages');
        var loadingOlderMessages = false;

        // Hàm escape HTML để tránh XSS
        function escapeHtml(s) {
            if (!s) return '';
            return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        // Hàm hiển thị tin nhắn
        function renderMessages(msgs) {
            for (var i = 0; i < msgs.length; i++) {
                var m = msgs[i];
                var div = document.createElement('div');
                div.className = 'msg';
                div.dataset.id = m.id;

                if (m.user_id === parseInt('<?php echo $_SESSION['user_minchat']['id']; ?>')) {
                    div.classList.add('own-msg');
                } else {
                    div.classList.add('opposite-msg');
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
                messagesEl.appendChild(div);

                lastId = Math.max(lastId, m.id);
            }
            setTimeout(function() {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }, 200);
        }

        // Hàm debounce để giới hạn tần suất gọi hàm
        function debounce(func, wait) {
            var timeout;
            return function() {
                clearTimeout(timeout);
                timeout = setTimeout(func, wait);
            };
        }

        // Hàm AJAX GET
        function ajaxGet(url, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                        } catch (e) {
                            cb(e);
                            return;
                        }
                        cb(null, data);
                    } else {
                        cb(new Error('HTTP ' + xhr.status));
                    }
                }
            };
            xhr.send(null);
        }

        // Hàm AJAX POST
        function ajaxPost(url, params, cb) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                        } catch (e) {
                            cb(e);
                            return;
                        }
                        cb(null, data);
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

        // Hàm poll tin nhắn mới
        function poll() {
            if (!polling) return;
            var url = 'api.php?action=fetch&last_id=' + encodeURIComponent(lastId);
            ajaxGet(url, function(err, data) {
                if (!err && data && data.ok) {
                    if (data.messages && data.messages.length) {
                        renderMessages(data.messages);
                    }
                    setTimeout(poll, 200);
                } else {
                    setTimeout(poll, 2000);
                }
            });
        }

        // Hàm gửi tin nhắn
        function sendMessage() {
            var text = document.getElementById('text').value.trim();
            if (!text) return false;
            ajaxPost('api.php?action=send', {
                text: text
            }, function(err, data) {
                if (!err && data && data.ok) {
                    document.getElementById('text').value = '';
                } else {
                    alert('Gửi lỗi');
                }
            });
            return false;
        }

        // Bật cuộn chạm
        messagesEl.style.webkitUserSelect = 'none';
        messagesEl.addEventListener('touchstart', function() {}, false);

        // Khởi động poll tin nhắn mới
        (function() {
            ajaxGet('api.php?action=fetch&last_id=0', function(err, data) {
                if (!err && data && data.ok) {
                    renderMessages(data.messages || []);
                }
                poll();
            });
        })();

        // Xử lý cuộn để tải tin nhắn cũ
        messagesEl.addEventListener('scroll', debounce(function() {
            if (messagesEl.scrollTop <= 10 && !loadingOlderMessages) {
                loadingOlderMessages = true;
                var firstMessage = messagesEl.firstChild;
                var firstMessageId = firstMessage ? parseInt(firstMessage.dataset.id) : 0;

                if (firstMessageId > 0) {
                    ajaxGet('api.php?action=fetch&before_id=' + encodeURIComponent(firstMessageId), function(err, data) {
                        if (!err && data && data.ok && data.messages.length) {
                            var currentScrollHeight = messagesEl.scrollHeight;
                            for (var i = 0; i < data.messages.length; i++) {
                                var m = data.messages[i];
                                var div = document.createElement('div');
                                div.className = 'msg';
                                div.dataset.id = m.id;

                                if (m.user_id === parseInt('<?php echo $_SESSION['user_minchat']['id']; ?>')) {
                                    div.classList.add('own-msg');
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
                                messagesEl.insertBefore(div, messagesEl.firstChild);
                            }
                            messagesEl.scrollTop = messagesEl.scrollHeight - currentScrollHeight;
                        }
                        loadingOlderMessages = false;
                    });
                } else {
                    loadingOlderMessages = false;
                }
            }
        }, 200));
    </script>

</body>

</html>
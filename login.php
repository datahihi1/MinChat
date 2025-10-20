<?php
session_start();

try {

    if (isset($_SESSION["user_minchat"])) {
        header("Location: index.php");
        exit();
    }

    // Kết nối đến SQLite database
    $pdo = new PDO('sqlite:messages.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Kiểm tra cookie "remember_me"
    if(isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];

        // Truy vấn người dùng theo token
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Lưu thông tin người dùng vào session
            $_SESSION['user_minchat'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'realname' => ($user['firstname'] !== null) ? $user['firstname'] . ' ' . $user['lastname'] : null,
                'email' => $user['email'],
                'role' => $user['role'],
            ];

            // Chuyển hướng đến trang chính
            header('Location: index.php');
            exit();
        } else {
            // Xoá cookie không hợp lệ
            setcookie('remember_me', '', time() - 3600, '/');
        }
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Lấy dữ liệu từ form
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        // Kiểm tra dữ liệu đầu vào
        if (empty($username) || empty($password)) {
            $_SESSION['notification']['error'] = 'Vui lòng điền đầy đủ thông tin.';
            header('Location: login.php');
            exit();
        }

        // Truy vấn người dùng từ cơ sở dữ liệu
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $_SESSION['notification']['error'] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            header('Location: login.php');
            exit;
        }

        // Kiểm tra mật khẩu
        if (!password_verify($password, $user['password'])) {
            $_SESSION['notification']['error'] = 'Tên đăng nhập hoặc mật khẩu không đúng.';
            header('Location: login.php');
            exit;
        }

        // Kiểm tra nếu tài khoản đã được đăng nhập ở thiết bị khác
        if ($user['remember_token'] !== null) {
            $_SESSION['notification']['error'] = 'Tài khoản này đã được đăng nhập ở thiết bị khác. Bị mất tài khoản?';
            $_SESSION['notification']['verify'] = true;
            header('Location: login.php');
            exit;
        }

        if ($user['firstname'] !== null) {
            $realname = $user['firstname'] . ' ' . $user['lastname'];
        }

        // Lưu thông tin người dùng vào session
        $_SESSION['user_minchat'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'realname' => $realname ?? null,
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        // Kiểm tra nếu người dùng chọn "Ghi nhớ tôi"
        if (!empty($_POST['remember_me'])) {
            $token = bin2hex(random_bytes(16)); // Tạo token ngẫu nhiên
            setcookie('remember_me', $token, time() + (30 * 24 * 60 * 60), '/'); // Lưu cookie 30 ngày

            // Lưu token vào cơ sở dữ liệu
            $stmt = $pdo->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
            $stmt->execute([':token' => $token, ':id' => $user['id']]);
        }

        // Chuyển hướng đến trang chính
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    die('Lỗi: ' . $e->getMessage());
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Đăng nhập - MinChat</title>
    <style>
        /* Basic responsive layout */
        html,
        body {
            height: 100%;
            margin: 0;
            font-family: Helvetica, Arial, sans-serif;
            background: #f4f4f4;
        }

        .container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #2b6ea3;
        }

        .form {
            display: flex;
            flex-direction: column;
        }

        .form label {
            font-size: 14px;
            margin-bottom: 5px;
            color: #333;
        }

        .form input[type="text"],
        .form input[type="password"] {
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .form button {
            padding: 10px;
            border: 0;
            border-radius: 4px;
            background: #2b6ea3;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }

        .form button:hover {
            background: #1f5a85;
        }

        .footer-note {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }

        .notification {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
        }

        .notification.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Đăng nhập</h1>
        </div>

        <!-- Hiển thị thông báo lỗi -->
        <?php if (!empty($_SESSION['notification']['error'])): ?>
            <div class="notification error">
                <?php
                echo htmlspecialchars($_SESSION['notification']['error']);
                unset($_SESSION['notification']['error']); // Xóa thông báo sau khi hiển thị
                if (!empty($_SESSION['notification']['verify'])) {
                    echo "<a href='verify.php'> Xác minh tại đây</a>";
                    unset($_SESSION['notification']['verify']);
                }
                ?>
            </div>
        <?php endif; ?>

        <form class="form" action="login.php" method="post">
            <label for="username">Tên đăng nhập</label>
            <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập">

            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" placeholder="Nhập mật khẩu">

            <label>
                <input type="checkbox" name="remember_me" value="1"> Ghi nhớ tôi (30 ngày)
            </label>

            <button type="submit">Đăng nhập</button>
        </form>
        <div class="footer-note">
            Chưa có tài khoản? <a href="register.php">Đăng ký</a>
        </div>
    </div>
</body>

</html>
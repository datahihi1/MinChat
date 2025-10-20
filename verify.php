<?php
session_start();

if(isset($_GET['token'])) {
    $token = trim($_GET['token']);

    $pdo = new PDO('sqlite:messages.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT * FROM token_reset WHERE token = :token LIMIT 1");
    $stmt->execute([':token' => $token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tokenData) {
        if (strtotime($tokenData['expires_at']) < time()) {
            $_SESSION['notification']['error'] = 'Liên kết xác minh đã hết hạn.';
            $pdo->prepare("DELETE FROM token_reset WHERE id = :id")->execute([':id' => $tokenData['id']]);
            header('Location: verify.php');
            exit;
        } else {
            $newPassword = random_6password();
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $_SESSION['notification']['success'] = 'Xác minh tài khoản thành công.';
            $pdo->prepare("DELETE FROM token_reset WHERE id = :id")->execute([':id' => $tokenData['id']]);
            $pdo->prepare("UPDATE users SET remember_token = null WHERE id = :user_id")->execute([':user_id' => $tokenData['user_id']]);
            $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id")->execute([
                ':password' => $hashedPassword,
                ':user_id' => $tokenData['user_id'],
            ]);
            mail($user['email'],
             "Mật khẩu mới của bạn",
              "
              Mật khẩu mới của bạn là: $newPassword
              \nVui lòng đăng nhập và thay đổi mật khẩu ngay sau khi đăng nhập.
              ");
            header('Location: verify.php');
            exit;
        }
    } else {
        $_SESSION['notification']['error'] = 'Liên kết xác minh không hợp lệ.';
        header('Location: verify.php');
        exit;
    }
}

/**
 * Random string 64 characters long
 * @return string
 */
function random_string64(): string {
    // Lấy 48 byte ngẫu nhiên (48 * 4/3 ≈ 64 ký tự khi mã hóa base64)
    $bytes = random_bytes(48);
    // base64_encode và lọc ký tự không phải chữ/số
    return substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 64);
}
/**
 * Random string 32 characters long
 * @return string
 */
function random_string32(): string {
    // Lấy 24 byte ngẫu nhiên (24 * 4/3 ≈ 32 ký tự khi mã hóa base64)
    $bytes = random_bytes(24);
    // base64_encode và lọc ký tự không phải chữ/số
    return substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 32);
}
function random_6password(): string {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $password = '';
    for ($i = 0; $i < 6; $i++) {
        $index = random_int(0, strlen($characters) - 1);
        $password .= $characters[$index];
    }
    return $password;
}

function current_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
        || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

    return $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

$pdo = new PDO('sqlite:messages.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $_SESSION['notification']['error'] = 'Vui lòng nhập email đăng ký.';
        header('Location: verify.php');
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($user)) {
        $_SESSION['notification']['error'] = 'Email không tồn tại trong hệ thống.';
        header('Location: verify.php');
        exit;
    }

    $token = random_string32();

    $stmt = $pdo->prepare("INSERT INTO token_reset (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
    $result = $stmt->execute([
        ':user_id' => $user['id'],
        ':token' => $token,
        ':expires_at' => date('Y-m-d H:i:s', time() + 1800),
    ]);

    if ($result === true) {
        // Gửi email xác minh (giả lập)
        // Trong thực tế, bạn sẽ sử dụng thư viện gửi email như PHPMailer hoặc SwiftMailer
        $verifyLink = current_url() . "?token=" . urlencode($token);
        mail($email,
         "Yêu cầu xác minh tài khoản",
          "
          Vui lòng nhấp vào liên kết sau để xác minh tài khoản của bạn: $verifyLink
          \n<b>Lưu ý:</b> Liên kết này chỉ có hiệu lực trong 30 phút.
          ");
    } else {
        $_SESSION['notification']['error'] = 'Đã xảy ra lỗi khi tạo yêu cầu xác minh.';
        header('Location: verify.php');
        exit;
    }

    $_SESSION['notification']['success'] = 'Một email xác minh đã được gửi.';
    header('Location: verify.php');
    exit;
}
?>

<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Xác minh tài khoản - MinChat</title>
    <style>
        html, body {
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
        .form input[type="email"] {
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
        .notification.success {
            background-color: #ddffdd;
            color: #4F8A10;
            border: 1px solid #4F8A10;
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
        <h1>Xác minh tài khoản</h1>
    </div>

    <?php if (!empty($_SESSION['notification']['success'])): ?>
        <div class="notification success">
            <?php
            echo htmlspecialchars($_SESSION['notification']['success']);
            unset($_SESSION['notification']['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['notification']['error'])): ?>
        <div class="notification error">
            <?php
            echo htmlspecialchars($_SESSION['notification']['error']);
            unset($_SESSION['notification']['error']);
            ?>
        </div>
    <?php endif; ?>

    <form class="form" action="verify.php" method="post">
        <label for="email">Email đăng ký</label>
        <input type="email" id="email" name="email" placeholder="Nhập email">

        <button type="submit">Yêu cầu xác minh</button>
    </form>
    <div class="footer-note">
        Quay lại <a href="login.php">Đăng nhập</a> hoặc <a href="register.php">Đăng ký</a>
    </div>
</div>
</body>
</html>
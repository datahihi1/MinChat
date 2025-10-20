<?php
session_start();
try {
    // Kết nối đến SQLite database
    $pdo = new PDO('sqlite:messages.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Xử lý form đăng ký
        // Lấy dữ liệu từ form
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $avatar = trim($_POST['avatar'] ?? '');
        $role = 'user'; // Mặc định là 'user'

        // Lưu giá trị cũ vào session
        $_SESSION['old'] = [
            'username' => $username,
            'email' => $email,
            'bio' => $bio,
            'avatar' => $avatar,
        ];

        // Kiểm tra dữ liệu đầu vào
        if (empty($username)) {
            $_SESSION['notification']['error'] = 'Tên đăng nhập không được để trống.';
            header('Location: register.php');
            exit;
        }
        if (empty($email)) {
            $_SESSION['notification']['error'] = 'Email không được để trống.';
            header('Location: register.php');
            exit;
        }
        if (empty($password)) {
            $_SESSION['notification']['error'] = 'Mật khẩu không được để trống.';
            header('Location: register.php');
            exit;
        }
        if (empty($confirm_password)) {
            $_SESSION['notification']['error'] = 'Xác nhận mật khẩu không được để trống.';
            header('Location: register.php');
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['notification']['error'] = 'Email không hợp lệ.';
            header('Location: register.php');
            exit;
        }

        if ($password !== $confirm_password) {
            $_SESSION['notification']['error'] = 'Mật khẩu và xác nhận mật khẩu không khớp.';
            header('Location: register.php');
            exit;
        }

        // Kiểm tra username, email đã tồn tại chưa
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
        $stmt->execute([':username' => $username, ':email' => $email]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $_SESSION['notification']['error'] = 'Tên đăng nhập hoặc email đã tồn tại.';
            header('Location: register.php');
            exit;
        }

        // Mã hóa mật khẩu
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Thêm người dùng vào cơ sở dữ liệu
        $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, bio, avatar, role, created_at, updated_at)
        VALUES (:username, :email, :password, :bio, :avatar, :role, datetime('now'), datetime('now'))");

        $result = $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashed_password,
            ':bio' => $bio ?? null,
            ':avatar' => $avatar ?? null,
            ':role' => $role,
        ]);

        if ($result === false) {
            $_SESSION['notification']['error'] = 'Đăng ký thất bại. Vui lòng thử lại.';
            header('Location: register.php');
            exit;
        }

        // Xóa giá trị cũ khi đăng ký thành công
        unset($_SESSION['old']);
        $_SESSION['notification']['success'] = 'Đăng ký thành công! Bạn có thể đăng nhập ngay bây giờ.';
        header('Location: register.php');
        exit;
    }

    // echo 'Đăng ký thành công! <a href="login.php">Đăng nhập</a>';
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { // SQLite code for UNIQUE constraint violation
        die('Tên đăng nhập hoặc email đã tồn tại.');
    }
    die('Lỗi: ' . $e->getMessage());
}
?>

<!doctype html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Đăng ký - MinChat</title>
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
        .form input[type="email"],
        .form input[type="password"],
        .form textarea {
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
            background: #2a933aff;
            color: #fff;
            font-size: 14px;
            cursor: pointer;
        }

        .form button:hover {
            background: #268d2dff;
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

        .notification.success {
            background-color: #ddffdd;
            color: #4F8A10;
            border: 1px solid #4F8A10;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1>Đăng ký</h1>
        </div>

    <?php if (!empty($_SESSION['notification']['success'])): ?>
        <div class="notification success">
            <?php
            echo htmlspecialchars($_SESSION['notification']['success']);
            unset($_SESSION['notification']['success']); // Xóa thông báo sau khi hiển thị
            ?>
        </div>
    <?php endif; ?>

        <?php if (!empty($_SESSION['notification']['error'])): ?>
            <div class="notification error">
                <?php
                echo htmlspecialchars($_SESSION['notification']['error']);
                unset($_SESSION['notification']['error']); // Xóa thông báo sau khi hiển thị
                ?>
            </div>
        <?php endif; ?>

        <form class="form" action="register.php" method="post">
            <label for="username">Tên đăng nhập</label>
            <input type="text" id="username" name="username" placeholder="Nhập tên đăng nhập" value="<?php echo htmlspecialchars($_SESSION['old']['username'] ?? ''); ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Nhập email" value="<?php echo htmlspecialchars($_SESSION['old']['email'] ?? ''); ?>">

            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" placeholder="Nhập mật khẩu">

            <label for="confirm_password">Xác nhận mật khẩu</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu">

            <label for="avatar">Ảnh đại diện (URL)</label>
            <input type="text" id="avatar" name="avatar" placeholder="Nhập URL ảnh đại diện" value="<?php echo htmlspecialchars($_SESSION['old']['avatar'] ?? ''); ?>">

            <label for="bio">Giới thiệu</label>
            <textarea id="bio" name="bio" placeholder="Viết một vài điều về bạn" rows="3"><?php echo htmlspecialchars($_SESSION['old']['bio'] ?? ''); ?></textarea>

            <button type="submit">Đăng ký</button>
        </form>
        <div class="footer-note">
            Đã có tài khoản? <a href="login.php">Đăng nhập</a>
        </div>
    </div>
</body>

</html>
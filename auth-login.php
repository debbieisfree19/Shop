<?php
/**
 * User Login Page
 * Đăng nhập bằng EMAIL
 */

session_start();
require_once 'db_connect.php';

$error_message = '';

// Nếu đã đăng nhập → redirect theo role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'customer';

    if ($role === 'admin') {
        header('Location: admin-dashboard.php');
    } else {
        header('Location: account-index.php');
    }
    exit;
}

// Xử lý login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error_message = 'Vui lòng nhập email và mật khẩu.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT UserID, Email, PasswordHash, Role
                FROM User_Account
                WHERE Email = :email
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Sai email hoặc mật khẩu
            if (!$user || !password_verify($password, $user['PasswordHash'])) {
                $error_message = 'Email hoặc mật khẩu không chính xác.';
            } else {
                // Login OK
                $_SESSION['user_id']  = $user['UserID'];
                $_SESSION['username'] = $user['Email']; // vì không có Username
                $_SESSION['role']     = strtolower($user['Role'] ?? 'customer');

                if ($_SESSION['role'] === 'admin') {
                    header('Location: admin-dashboard.php');
                } else {
                    header('Location: account-index.php');
                }
                exit;
            }
        } catch (Exception $e) {
            $error_message = 'Có lỗi xảy ra, vui lòng thử lại.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng Nhập - Moonlit Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body">

<main class="auth-main">
    <div class="auth-card">
        <h1 class="auth-title">Đăng Nhập</h1>

        <?php if ($error_message): ?>
            <div class="auth-alert auth-alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="auth-form-group">
                <label class="auth-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="account-input auth-input"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    required
                >
            </div>

            <div class="auth-form-group">
                <label class="auth-label">Mật khẩu</label>
                <input
                    type="password"
                    name="password"
                    class="account-input auth-input"
                    required
                >
            </div>

            <button type="submit" class="account-btn-save auth-btn-submit">
                Đăng Nhập
            </button>

            <div class="auth-divider"></div>

            <div class="auth-footer">
                <p>
                    Bạn chưa có tài khoản?
                    <a href="auth-register.php" class="auth-link">Đăng ký ngay</a>
                </p>
            </div>
        </form>
    </div>
</main>

<footer class="site-footer">
    © 2025 Moonlit — All rights reserved.
</footer>

</body>
</html>

<?php
session_start();

/* =====================
   SHOW PHP ERRORS (DEV)
===================== */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

/* =====================
   AUTH / COMMON
===================== */
$isLoggedIn       = isset($_SESSION['user_id']);
$currentUserID   = $_SESSION['user_id'] ?? null;
$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = basename($_SERVER['PHP_SELF']);

if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

/* =====================
   HANDLE CONTACT FORM
===================== */
$successMessage = '';
$errorMessage   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $subject  = trim($_POST['subject'] ?? '');
    $message  = trim($_POST['message'] ?? '');

    // Validate
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Email không hợp lệ.';
    } else {

        // Giới hạn đúng theo DB
        $message = mb_substr($message, 0, 200);
        $subject = mb_substr("[$category] $subject", 0, 200);

        if ($fullName && $email && $subject && $message) {
            try {

                if ($isLoggedIn) {
                    // Có UserID
                    $stmt = $pdo->prepare("
                        INSERT INTO Contact_Message
                        (UserID, FullName, Email, Subject, Message, CreatedDate, Status)
                        VALUES (?, ?, ?, ?, ?, NOW(), 'New')
                    ");
                    $stmt->execute([
                        $currentUserID,
                        $fullName,
                        $email,
                        $subject,
                        $message
                    ]);
                } else {
                    // Guest → KHÔNG insert UserID
                    $stmt = $pdo->prepare("
                        INSERT INTO Contact_Message
                        (FullName, Email, Subject, Message, CreatedDate, Status)
                        VALUES (?, ?, ?, ?, NOW(), 'New')
                    ");
                    $stmt->execute([
                        $fullName,
                        $email,
                        $subject,
                        $message
                    ]);
                }

                $successMessage = "🌙 Cảm ơn bạn đã liên hệ Moonlit. 
                Đội ngũ hỗ trợ sẽ phản hồi trong vòng 24 giờ làm việc.";

            } catch (PDOException $e) {
                $errorMessage = 'Lỗi hệ thống. Vui lòng thử lại sau.';
            }
        } else {
            $errorMessage = 'Vui lòng điền đầy đủ thông tin.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Contact Us - Moonlit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="moonlit-style.css">
<style>
.contact-section{background:#fff;border-radius:16px;padding:32px;margin-bottom:32px;box-shadow:0 8px 24px rgba(0,0,0,.04)}
.contact-header{display:flex;gap:24px;margin-bottom:32px}
.contact-header-divider{width:4px;background:var(--color-deep-blue);border-radius:2px}
.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px}
.contact-form input,.contact-form textarea,.contact-form select{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #dbe5ff;margin-bottom:14px}
.alert-success{background:#eaf8ef;color:#2b7a4b;padding:14px;border-radius:10px;margin-bottom:16px}
.alert-error{background:#fdeaea;color:#a94442;padding:14px;border-radius:10px;margin-bottom:16px}
.faq-item{margin-bottom:20px}
.faq-item h4{color:var(--color-deep-blue)}
@media(max-width:900px){.contact-grid{grid-template-columns:1fr}}
</style>
</head>

<body class="account-body">

<header class="account-header site-header">
<div class="container header-inner">
<div class="header-left">
<a href="index.php" class="logo-link header-logo">
<img src="img/image.png" alt="Moonlit logo">
</a>
<nav class="header-menu">
<a href="index.php" class="header-menu-link <?= nav_active('index.php',$currentPage) ?>">Trang chủ</a>
<a href="shop.php" class="header-menu-link <?= nav_active('shop.php',$currentPage) ?>">Cửa hàng</a>
<a href="aboutus.php" class="header-menu-link <?= nav_active('aboutus.php',$currentPage) ?>">Về chúng tôi</a>
<a href="forum.php" class="header-menu-link <?= nav_active('forum.php',$currentPage) ?>">Forum</a>
</nav>
</div>
<div class="header-right">
<?php if($isLoggedIn): ?>
Xin chào, <strong><?= htmlspecialchars($currentUsername) ?></strong>
<a href="logout.php" class="account-btn-secondary">Đăng xuất</a>
<?php else: ?>
<a href="auth-login.php" class="account-btn-secondary">Tài khoản</a>
<?php endif; ?>
</div>
</div>
</header>

<main class="account-main">
<div class="container">

<section class="contact-header">
<h1 class="account-section-title">Liên hệ Moonlit</h1>
<div class="contact-header-divider"></div>
<p class="account-section-subtitle">
Moonlit luôn sẵn sàng lắng nghe mọi câu hỏi, góp ý và chia sẻ từ bạn để không ngừng hoàn thiện trải nghiệm đọc sách.
</p>
</section>

<section class="contact-section">
<div class="contact-grid">

<div>
<h3>✉️ Gửi tin nhắn</h3>

<?php if($successMessage): ?><div class="alert-success"><?= $successMessage ?></div><?php endif; ?>
<?php if($errorMessage): ?><div class="alert-error"><?= $errorMessage ?></div><?php endif; ?>

<form method="POST" class="contact-form">
<input name="name" placeholder="Họ và tên" required>
<input name="email" type="email" placeholder="Email" required>
<select name="category" required>
<option value="">-- Loại liên hệ --</option>
<option>Hỗ trợ đơn hàng</option>
<option>Góp ý dịch vụ</option>
<option>Hợp tác</option>
<option>Khác</option>
</select>
<input name="subject" placeholder="Tiêu đề" required>
<textarea name="message" rows="5" maxlength="200" placeholder="Nội dung (tối đa 200 ký tự)" required></textarea>
<button class="account-btn-save">Gửi liên hệ</button>
</form>
</div>

</div>
</section>

</div>
</main>

<footer class="site-footer">
    © 2025 Moonlit — All rights reserved.
</footer>

</body>
</html>

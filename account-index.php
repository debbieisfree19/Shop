<?php
// account-index.php
session_start();
require_once 'db_connect.php';

/**
 * ACCOUNT INDEX
 * - Trang tài khoản sau khi đăng nhập
 * - Menu: Trang chủ / Cửa hàng / Giỏ hàng / Checkout
 * - FIX: không hardcode cột Username/FullName... nữa (auto-detect cột trong DB)
 */

// ===== CHECK ĐĂNG NHẬP =====
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_to'] = 'account-index.php';
    header('Location: auth-login.php');
    exit;
}

// ===== BIẾN DÙNG CHUNG =====
$user_id         = $_SESSION['user_id'];
$isLoggedIn      = true;
$currentUsername = $_SESSION['username'] ?? ''; // chỉ để show trên header, không phụ thuộc DB
$currentPage     = 'account-index.php';

$message      = '';
$message_type = 'error';

// helper active menu
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

// ===== TÍNH SỐ ITEM GIỎ HÀNG (SESSION CART) =====
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $pid => $qty) {
        $cartCount += (int)$qty;
    }
}

// ===== LẤY THÔNG TIN USER (AUTO-DETECT COLUMNS) =====
$user = null;

try {
    if (!isset($pdo)) {
        throw new Exception('PDO chưa khởi tạo. Kiểm tra db_connect.php');
    }

    // Lấy danh sách cột thực tế của bảng User_Account
    $cols = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM User_Account");
    foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cols[$r['Field']] = true;
    }

    $pick = function(array $candidates) use ($cols) {
        foreach ($candidates as $c) {
            if (isset($cols[$c])) return $c;
        }
        return null;
    };

    // Các tên cột hay gặp (mày có thể thêm nếu DB mày đặt khác)
    $nameCol   = $pick(['Username', 'DisplayName', 'FullName', 'Name']);
    $phoneCol  = $pick(['Phone', 'PhoneNumber']);
    $cityCol   = $pick(['City']);
    $distCol   = $pick(['District']);
    $wardCol   = $pick(['Ward']);
    $streetCol = $pick(['Street']);
    $houseCol  = $pick(['HouseNumber']);

    // Build SELECT động, không có cột nào thì bỏ qua
    $selectCols = ["UserID", "Email"];
    if ($nameCol)   $selectCols[] = "$nameCol AS DisplayName";
    if ($phoneCol)  $selectCols[] = "$phoneCol AS Phone";
    if ($cityCol)   $selectCols[] = "$cityCol AS City";
    if ($distCol)   $selectCols[] = "$distCol AS District";
    if ($wardCol)   $selectCols[] = "$wardCol AS Ward";
    if ($streetCol) $selectCols[] = "$streetCol AS Street";
    if ($houseCol)  $selectCols[] = "$houseCol AS HouseNumber";

    $sql = "SELECT " . implode(", ", $selectCols) . " FROM User_Account WHERE UserID = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $message = 'Không tìm thấy tài khoản của bạn. Vui lòng đăng xuất và đăng nhập lại.';
        $message_type = 'error';
    }

} catch (Exception $e) {
    $message = 'Lỗi tải tài khoản: ' . $e->getMessage();
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Tài khoản - Moonlit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body">

<!-- ===================== HEADER ===================== -->
<header class="account-header site-header">
    <div class="container header-inner">
        <div class="header-left">
            <a href="index.php" class="logo-link">
                <span class="account-logo">Moonlit</span>
            </a>

            <nav class="header-menu">
                <a href="index.php" class="header-menu-link <?php echo nav_active('index.php', $currentPage); ?>">
                    Trang chủ
                </a>
                <a href="shop.php" class="header-menu-link <?php echo nav_active('shop.php', $currentPage); ?>">
                    Cửa hàng
                </a>
                <a href="cart.php" class="header-menu-link <?php echo nav_active('cart.php', $currentPage); ?>">
                    Giỏ hàng (<?php echo (int)$cartCount; ?>)
                </a>
                <a href="checkout.php" class="header-menu-link <?php echo nav_active('checkout.php', $currentPage); ?>">
                    Checkout
                </a>
            </nav>
        </div>

        <div class="header-right">
            <div class="header-account">
                <span class="account-username">
                    Xin chào,
                    <strong>
                        <?php echo htmlspecialchars($currentUsername ?: ($user['DisplayName'] ?? $user['Email'] ?? '')); ?>
                    </strong>
                </span>
                <div class="header-account-actions">
                    <a href="account-index.php" class="account-btn-secondary header-account-btn">
                        Tài khoản
                    </a>
                    <a href="logout.php" class="account-btn-secondary header-account-btn">
                        Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- ===================== MAIN ===================== -->
<main class="account-main">
    <div class="container">

        <section class="shop-header">
            <h1 class="account-section-title">Tài khoản của bạn</h1>
            <p class="account-section-subtitle">Quản lý thông tin cá nhân & quay lại mua sắm nè ✨</p>
        </section>

        <?php if (!empty($message)): ?>
            <div class="account-alert account-alert-error">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="account-card">
                <h3 class="account-card-title">Thông tin nhanh</h3>

                <div style="display:grid; gap:10px; margin-top:10px;">
                    <div><strong>Tên hiển thị:</strong> <?php echo htmlspecialchars($user['DisplayName'] ?? $user['Email'] ?? ''); ?></div>
                    <div><strong>Email:</strong> <?php echo htmlspecialchars($user['Email'] ?? ''); ?></div>
                    <div><strong>SĐT:</strong> <?php echo htmlspecialchars($user['Phone'] ?? ''); ?></div>

                    <?php
                        $addr = trim(
                            ($user['HouseNumber'] ?? '') . ' ' .
                            ($user['Street'] ?? '') . ', ' .
                            ($user['Ward'] ?? '') . ', ' .
                            ($user['District'] ?? '') . ', ' .
                            ($user['City'] ?? '')
                        );
                    ?>
                    <div><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($addr ?: 'Chưa cập nhật'); ?></div>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px; flex-wrap:wrap;">
                    <!-- nếu chưa có file này thì đổi link hoặc xoá nút -->
                    <a class="account-btn-save" href="account-profile.php">Cập nhật thông tin</a>

                    <a class="account-btn-secondary" href="shop.php">Tiếp tục mua sắm</a>

                    <a class="account-btn-secondary" href="cart.php">Xem giỏ hàng (<?php echo (int)$cartCount; ?>)</a>

                    <a class="account-btn-secondary" href="checkout.php">Đi checkout</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="account-card">
            <h3 class="account-card-title">Gợi ý nhanh</h3>
            <p class="account-section-subtitle" style="margin-top:8px;">
                Nếu bấm “Thêm vào giỏ” mà bị đá qua login, thì login xong sẽ tự quay lại
                (miễn là auth-login.php redirect theo <code>$_SESSION['redirect_to']</code>).
            </p>
        </div>

    </div>
</main>

<footer class="site-footer">
    © 2025 Moonlit — All rights reserved.
</footer>

</body>
</html>

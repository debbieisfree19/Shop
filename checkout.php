<?php
/**
 * MOONLIT STORE - CHECKOUT PAGE
 * - Lấy dữ liệu từ giỏ hàng trong $_SESSION['cart']
 * - Hiển thị form thông tin nhận hàng + tóm tắt đơn
 * - Hiện tại: DEMO, chưa lưu vào DB, chỉ clear giỏ khi submit thành công
 */

session_start();
require_once 'db_connect.php';

// ============================
// Trạng thái đăng nhập
// ============================

// Sau này nếu muốn bắt buộc đăng nhập thì bỏ comment đoạn này:
// if (!isset($_SESSION['user_id'])) {
//     header('Location: auth_login.php');
//     exit;
// }

$isLoggedIn      = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = 'checkout.php';

// Helper active nav (nếu chưa có)
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

// ============================
// Lấy giỏ hàng từ session
// ============================
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = $_SESSION['cart'];

// ============================
// Lấy thông tin sản phẩm trong giỏ
// ============================
$products      = [];
$error_msg     = '';
$cartTotal     = 0;
$totalItems    = 0;
$form_errors   = [];
$success_msg   = '';

if (!empty($cart)) {
    try {
        $ids = array_keys($cart);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "
            SELECT
                ProductID,
                ProductName,
                Price,
                DiscountPrice,
                Image
            FROM Product
            WHERE ProductID IN ($placeholders)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($products as &$p) {
            $id        = $p['ProductID'];
            $qty       = $cart[$id] ?? 0;
            $unit      = $p['DiscountPrice'] ?? $p['Price'];
            $lineTotal = $unit * $qty;

            $p['quantity']   = $qty;
            $p['unit_price'] = $unit;
            $p['line_total'] = $lineTotal;

            $cartTotal  += $lineTotal;
            $totalItems += $qty;
        }
        unset($p);
    } catch (Exception $e) {
        $error_msg = 'Không thể tải dữ liệu giỏ hàng: ' . $e->getMessage();
    }
}

// ============================
// Xử lý submit ĐẶT HÀNG (DEMO – chưa lưu DB)
// ============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $note      = trim($_POST['note'] ?? '');
    $payment   = trim($_POST['payment_method'] ?? 'cod');

    if ($full_name === '') {
        $form_errors[] = 'Vui lòng nhập họ và tên.';
    }
    if ($phone === '') {
        $form_errors[] = 'Vui lòng nhập số điện thoại.';
    }
    if ($address === '') {
        $form_errors[] = 'Vui lòng nhập địa chỉ nhận hàng.';
    }
    if (empty($cart) || empty($products)) {
        $form_errors[] = 'Giỏ hàng trống, không thể đặt hàng.';
    }

    if (empty($form_errors)) {
        // TODO: Sau này lưu đơn hàng vào bảng Orders, Order_Items ở đây

        // DEMO: clear giỏ, báo thành công
        $_SESSION['cart'] = [];
        $success_msg = 'Đặt hàng thành công (demo). Tụi mình sẽ liên hệ xác nhận đơn trong thời gian sớm nhất ✨';

        // Có thể set lại biến để không render form nữa
        $cart        = [];
        $products    = [];
        $cartTotal   = 0;
        $totalItems  = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán - Moonlit Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="moonlit-style.css">
</head>
<body class="account-body">

    <!-- ===================== HEADER (giống index / cart) ===================== -->
    <header class="account-header site-header">
        <div class="container header-inner">
            <!-- Logo + Menu -->
            <div class="header-left">
                <a href="index.php" class="logo-link">
                    <span class="account-logo">Moonlit</span>
                </a>

                <nav class="header-menu">
                    <a
                        href="index.php"
                        class="header-menu-link <?php echo nav_active('index.php', $currentPage); ?>"
                    >
                        Trang chủ
                    </a>
                    <a
                        href="shop.php"
                        class="header-menu-link <?php echo nav_active('shop.php', $currentPage); ?>"
                    >
                        Cửa hàng
                    </a>
                    <a
                        href="aboutus.php"
                        class="header-menu-link <?php echo nav_active('aboutus.php', $currentPage); ?>"
                    >
                        Về chúng tôi
                    </a>
                    <a
                        href="return-policy.php"
                        class="header-menu-link <?php echo nav_active('return-policy.php', $currentPage); ?>"
                    >
                        Chính sách
                    </a>
                </nav>
            </div>

            <!-- Search + Cart + Account -->
            <div class="header-right">
                <!-- Tìm kiếm -->
                <form method="GET" action="shop.php" class="header-search-form">
                    <input
                        type="text"
                        name="q"
                        class="account-input header-search-input"
                        placeholder="Tìm sách..."
                    >
                    <button type="submit" class="account-btn-save header-search-btn">
                        Tìm
                    </button>
                </form>

                <!-- Nút giỏ hàng -->
                <a href="cart.php" class="account-btn-secondary header-cart-btn">
                    Giỏ hàng
                </a>

                <!-- Khu vực tài khoản -->
                <?php if ($isLoggedIn): ?>
                    <!-- ĐÃ ĐĂNG NHẬP -->
                    <div class="header-account">
                        <span class="account-username">
                            Xin chào, <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
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
                <?php else: ?>
                    <!-- CHƯA ĐĂNG NHẬP -->
                    <a
                        href="auth-login.php"
                        class="account-btn-secondary header-account-btn"
                    >
                        Tài khoản
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ===================== MAIN ===================== -->
    <main class="account-main checkout-main">
        <div class="container">

            <!-- Tiêu đề -->
            <section class="checkout-header">
                <h1 class="account-section-title">Thanh toán</h1>
                <p class="account-section-subtitle">
                    Điền thông tin nhận hàng và kiểm tra lại đơn sách Moonlit của bạn ✨
                </p>
            </section>

            <!-- Lỗi hệ thống (DB) -->
            <?php if (!empty($error_msg)): ?>
                <div class="account-alert account-alert-error">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <!-- Lỗi form -->
            <?php if (!empty($form_errors)): ?>
                <div class="account-alert account-alert-error">
                    <?php foreach ($form_errors as $err): ?>
                        <div><?php echo htmlspecialchars($err); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Thông báo thành công -->
            <?php if (!empty($success_msg)): ?>
                <div class="account-alert account-alert-success">
                    <?php echo htmlspecialchars($success_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart) || empty($products)): ?>
                <!-- Giỏ hàng trống -->
                <div class="account-card cart-empty-card">
                    <p class="account-empty-text">
                        Giỏ hàng của bạn đang trống hoặc đơn đã được đặt. 
                        Hãy quay lại <a href="shop.php" class="auth-link">Cửa hàng Moonlit</a> để chọn thêm sách nhé!
                    </p>
                </div>
            <?php else: ?>
                <!-- Layout 2 cột: bên trái form, bên phải tóm tắt đơn -->
                <div class="checkout-layout">
                    <!-- Form thông tin nhận hàng -->
                    <section class="checkout-form-section">
                        <div class="account-card checkout-form-card">
                            <h2 class="checkout-section-title">Thông tin nhận hàng</h2>

                            <form method="POST" class="checkout-form">
                                <div class="checkout-form-grid">
                                    <!-- Họ tên -->
                                    <div class="checkout-field">
                                        <label for="full_name" class="account-label">Họ và tên *</label>
                                        <input
                                            type="text"
                                            id="full_name"
                                            name="full_name"
                                            class="account-input"
                                            value="<?php echo htmlspecialchars($_POST['full_name'] ?? $currentUsername); ?>"
                                            required
                                        >
                                    </div>

                                    <!-- Email -->
                                    <div class="checkout-field">
                                        <label for="email" class="account-label">Email</label>
                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            class="account-input"
                                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                        >
                                    </div>

                                    <!-- Số điện thoại -->
                                    <div class="checkout-field">
                                        <label for="phone" class="account-label">Số điện thoại *</label>
                                        <input
                                            type="text"
                                            id="phone"
                                            name="phone"
                                            class="account-input"
                                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                            required
                                        >
                                    </div>

                                    <!-- Địa chỉ -->
                                    <div class="checkout-field checkout-field-full">
                                        <label for="address" class="account-label">Địa chỉ nhận hàng *</label>
                                        <textarea
                                            id="address"
                                            name="address"
                                            class="account-input checkout-textarea"
                                            rows="3"
                                            required
                                        ><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                    </div>

                                    <!-- Ghi chú -->
                                    <div class="checkout-field checkout-field-full">
                                        <label for="note" class="account-label">Ghi chú cho đơn hàng</label>
                                        <textarea
                                            id="note"
                                            name="note"
                                            class="account-input checkout-textarea"
                                            rows="3"
                                        ><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                                    </div>

                                    <!-- Phương thức thanh toán -->
                                    <div class="checkout-field checkout-field-full">
                                        <span class="account-label">Phương thức thanh toán</span>
                                        <div class="checkout-payment-options">
                                            <label class="checkout-radio-option">
                                                <input
                                                    type="radio"
                                                    name="payment_method"
                                                    value="cod"
                                                    <?php echo (($_POST['payment_method'] ?? 'cod') === 'cod') ? 'checked' : ''; ?>
                                                >
                                                <span>Thanh toán khi nhận hàng (COD)</span>
                                            </label>
                                            <label class="checkout-radio-option">
                                                <input
                                                    type="radio"
                                                    name="payment_method"
                                                    value="bank"
                                                    <?php echo (($_POST['payment_method'] ?? '') === 'bank') ? 'checked' : ''; ?>
                                                >
                                                <span>Chuyển khoản ngân hàng</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="account-btn-save checkout-submit-btn">
                                    Đặt hàng
                                </button>
                            </form>
                        </div>
                    </section>

                    <!-- Tóm tắt đơn hàng -->
                    <aside class="checkout-summary">
                        <div class="account-card cart-summary-card">
                            <h2 class="cart-summary-title">Đơn hàng của bạn</h2>

                            <div class="checkout-summary-list">
                                <?php foreach ($products as $product): ?>
                                    <div class="checkout-summary-item">
                                        <div class="checkout-summary-info">
                                            <p class="checkout-summary-name">
                                                <?php echo htmlspecialchars($product['ProductName']); ?>
                                            </p>
                                            <p class="checkout-summary-qty">
                                                SL: <?php echo (int)$product['quantity']; ?>
                                            </p>
                                        </div>
                                        <div class="checkout-summary-line-total">
                                            <?php echo number_format($product['line_total'], 0, ',', '.'); ?> đ
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="cart-summary-row">
                                <span>Tổng số lượng</span>
                                <span><?php echo (int)$totalItems; ?></span>
                            </div>
                            <div class="cart-summary-row cart-summary-total">
                                <span>Tổng cộng</span>
                                <span><?php echo number_format($cartTotal, 0, ',', '.'); ?> đ</span>
                            </div>

                            <p class="cart-note">
                                * Đây là trang thanh toán demo, chưa có bước thanh toán online.  
                                Sau này bà có thể thêm QR chuyển khoản hoặc tích hợp cổng thanh toán vào đây.
                            </p>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ===================== FOOTER ===================== -->
    <footer class="site-footer">
        © 2025 Moonlit — All rights reserved.
    </footer>
</body>
</html>

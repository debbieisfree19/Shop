<?php
/**
 * MOONLIT STORE - CART PAGE
 * - Giỏ hàng lưu trong $_SESSION['cart'] = [productId => quantity]
 * - Yêu cầu đăng nhập để xem / thao tác giỏ
 */

session_start();
require_once 'db_connect.php';

// Phải đăng nhập
if (!isset($_SESSION['user_id'])) {
   header('Location: auth-login.php');
  exit;
}

$isLoggedIn      = true;
$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = 'cart.php';

// Helper active nav (đã dùng bên index / shop)
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

// ============================
// Xử lý session cart
// ============================
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart = $_SESSION['cart'];

// Xóa 1 sản phẩm
if (isset($_GET['remove'])) {
    $removeId = $_GET['remove'];
    if (isset($_SESSION['cart'][$removeId])) {
        unset($_SESSION['cart'][$removeId]);
    }
    header('Location: cart.php');
    exit;
}

// Xóa hết giỏ
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    $_SESSION['cart'] = [];
    header('Location: cart.php');
    exit;
}

// Cập nhật số lượng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty']) && is_array($_POST['qty'])) {
    foreach ($_POST['qty'] as $productId => $qty) {
        $qty = (int)$qty;
        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
    }
    header('Location: cart.php');
    exit;
}

$cart = $_SESSION['cart'] ?? [];

// ============================
// Lấy thông tin sản phẩm trong giỏ
// ============================
$products   = [];
$error_msg  = '';
$cartTotal  = 0;
$totalItems = 0;

if (!empty($cart)) {
    try {
        $ids = array_keys($cart);
        // Chuẩn bị chuỗi placeholder ?,?,?
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

        // Tính tổng
        foreach ($products as &$p) {
            $id       = $p['ProductID'];
            $qty      = $cart[$id] ?? 0;
            $unit     = $p['DiscountPrice'] ?? $p['Price'];
            $lineTotal = $unit * $qty;

            $p['quantity']   = $qty;
            $p['unit_price'] = $unit;
            $p['line_total'] = $lineTotal;

            $cartTotal  += $lineTotal;
            $totalItems += $qty;
        }
        unset($p);
    } catch (Exception $e) {
        $error_msg = 'Không thể tải giỏ hàng: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng - Moonlit Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="moonlit-style.css">
</head>
<body class="account-body">

    <!-- ===================== HEADER ===================== -->
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

                <a href="cart.php" class="account-btn-secondary header-cart-btn">
                    Giỏ hàng
                </a>

                <div class="header-account">
                    <!--<span class="account-username">
                        Xin chào, <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
                    </span> -->
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
    <main class="account-main cart-main">
        <div class="container">

            <section class="cart-header">
                <h1 class="account-section-title">Giỏ hàng của bạn</h1>
                <p class="account-section-subtitle">
                    Xem lại những cuốn sách bạn đã chọn trước khi thanh toán ✨
                </p>
            </section>

            <?php if (!empty($error_msg)): ?>
                <div class="account-alert account-alert-error">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($cart) || empty($products)): ?>
                <div class="account-card cart-empty-card">
                    <p class="account-empty-text">
                        Giỏ hàng của bạn đang trống. Bắt đầu khám phá sách tại
                        <a href="shop.php" class="auth-link">Cửa hàng Moonlit</a> nhé!
                    </p>
                </div>
            <?php else: ?>
                <div class="cart-layout">
                    <!-- Danh sách sản phẩm -->
                    <section class="cart-items">
                        <form method="POST">
                            <div class="account-card cart-items-card">

                                <div class="cart-table-header">
                                    <span>Sản phẩm</span>
                                    <span>Giá</span>
                                    <span>Số lượng</span>
                                    <span>Thành tiền</span>
                                    <span></span>
                                </div>

                                <div class="cart-list">
                                    <?php foreach ($products as $product): ?>
                                        <div class="cart-item">
                                            <!-- Ảnh + tên -->
                                            <div class="cart-item-info">
                                                <div class="cart-item-image">
                                                    <?php if (!empty($product['Image'])): ?>
                                                        <img
                                                            src="<?php echo htmlspecialchars($product['Image']); ?>"
                                                            alt="<?php echo htmlspecialchars($product['ProductName']); ?>"
                                                        >
                                                    <?php else: ?>
                                                        <span class="shop-product-image-placeholder">Moonlit</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="cart-item-text">
                                                    <p class="cart-item-title">
                                                        <?php echo htmlspecialchars($product['ProductName']); ?>
                                                    </p>
                                                </div>
                                            </div>

                                            <!-- Giá -->
                                            <div class="cart-item-price">
                                                <?php if (!empty($product['DiscountPrice'])): ?>
                                                    <span class="cart-price-current">
                                                        <?php echo number_format($product['DiscountPrice'], 0, ',', '.'); ?> đ
                                                    </span>
                                                    <span class="cart-price-old">
                                                        <?php echo number_format($product['Price'], 0, ',', '.'); ?> đ
                                                    </span>
                                                <?php else: ?>
                                                    <span class="cart-price-current">
                                                        <?php echo number_format($product['Price'], 0, ',', '.'); ?> đ
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Số lượng -->
                                            <div class="cart-item-qty">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    name="qty[<?php echo $product['ProductID']; ?>]"
                                                    class="account-input cart-qty-input"
                                                    value="<?php echo (int)$product['quantity']; ?>"
                                                >
                                            </div>

                                            <!-- Thành tiền -->
                                            <div class="cart-item-total">
                                                <?php echo number_format($product['line_total'], 0, ',', '.'); ?> đ
                                            </div>

                                            <!-- Xóa -->
                                            <div class="cart-item-remove">
                                                <a
                                                    href="cart.php?remove=<?php echo urlencode($product['ProductID']); ?>"
                                                    class="cart-remove-link"
                                                >
                                                    Xóa
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="cart-actions-row">
                                    <button type="submit" class="account-btn-secondary cart-update-btn">
                                        Cập nhật giỏ hàng
                                    </button>
                                    <a href="cart.php?clear=1" class="cart-clear-link">
                                        Xóa hết giỏ hàng
                                    </a>
                                </div>

                            </div>
                        </form>
                    </section>

                    <!-- Tổng kết -->
                    <aside class="cart-summary">
                        <div class="account-card cart-summary-card">
                            <h2 class="cart-summary-title">Tóm tắt đơn hàng</h2>
                            <div class="cart-summary-row">
                                <span>Số lượng sản phẩm</span>
                                <span><?php echo (int)$totalItems; ?></span>
                            </div>
                            <div class="cart-summary-row cart-summary-total">
                                <span>Tổng cộng</span>
                                <span><?php echo number_format($cartTotal, 0, ',', '.'); ?> đ</span>
                            </div>

                            <a
                                href="checkout.php"
                                class="account-btn-save cart-checkout-btn"
                            >
                                Tiến hành thanh toán
                            </a>

                            <p class="cart-note">
                                * Trang thanh toán (checkout.php) bà tự custom sau hoặc dùng làm trang xác nhận đơn ✨
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

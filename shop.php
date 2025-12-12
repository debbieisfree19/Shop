<?php
/**
 * MOONLIT STORE - SHOP PAGE
 * - Dùng bảng Product, Categories, Product_Categories, Publisher, Review
 */

session_start();
require_once 'db_connect.php';

// Trạng thái đăng nhập
$isLoggedIn      = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? '';

// Lọc từ query string
$search     = trim($_GET['q'] ?? '');
$categoryId = $_GET['category'] ?? '';

// ============================
// Helper nav_active (giống index.php)
// ============================
$currentPage = 'shop.php';
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

// ============================
// Lấy danh sách danh mục
// ============================
$categories = [];
try {
    $cateStmt   = $pdo->query("SELECT CategoryID, CategoryName FROM Categories ORDER BY CategoryName");
    $categories = $cateStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ============================
// Lấy danh sách nhà xuất bản
// ============================
$publishers = [];
try {
    $pubStmt = $pdo->query("SELECT PublisherID, PublisherName FROM Publisher ORDER BY PublisherName");
    $publishers = $pubStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}


// ============================
// Lấy danh sách sản phẩm
// ============================
$error_message = '';
$products      = [];

try {
    $params = [];
    $sql = "
        SELECT
            p.ProductID,
            p.ProductName,
            p.Price,
            p.DiscountPrice,
            p.PublisherID,
            p.CreatedDate,
            CASE
                WHEN p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0 THEN 1
                ELSE 0
            END AS HasImage
        FROM Product p
        WHERE 1 = 1
    ";

    // Lọc nhà xuất bản
    if (!empty($_GET['publisher'])) {
        $sql .= " AND p.PublisherID = :publisher";
        $params[':publisher'] = $_GET['publisher'];
    }

    // Lọc giá
    if (!empty($_GET['min_price'])) {
        $sql .= " AND p.Price >= :min_price";
        $params[':min_price'] = (float)$_GET['min_price'];
    }
    if (!empty($_GET['max_price'])) {
        $sql .= " AND p.Price <= :max_price";
        $params[':max_price'] = (float)$_GET['max_price'];
    }

    // Lọc khuyến mãi
    if (isset($_GET['sale']) && $_GET['sale'] !== '') {
        if ($_GET['sale'] == '1') $sql .= " AND p.DiscountPrice IS NOT NULL";
        if ($_GET['sale'] == '0') $sql .= " AND p.DiscountPrice IS NULL";
    }

    // Lọc theo rating (nếu có bảng Review)
    if (!empty($_GET['rating'])) {
        $sql .= "
            AND (
                SELECT IFNULL(AVG(r.Rating), 0)
                FROM Review r
                WHERE r.ProductID = p.ProductID
            ) >= :rating
        ";
        $params[':rating'] = (int)$_GET['rating'];
    }

    // Tìm theo tên sách
    if ($search !== '') {
        $sql .= " AND p.ProductName LIKE :search";
        $params[':search'] = '%' . $search . '%';
    }

    // Lọc theo danh mục (EXISTS để khỏi JOIN/GROUP BY)
    if ($categoryId !== '') {
        $sql .= "
            AND EXISTS (
                SELECT 1
                FROM Product_Categories pc
                WHERE pc.ProductID = p.ProductID
                  AND pc.CategoryID = :categoryId
            )
        ";
        $params[':categoryId'] = $categoryId;
    }

    $sql .= " ORDER BY p.CreatedDate DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = 'Không thể tải danh sách sản phẩm: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cửa hàng - Moonlit Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="moonlit-style.css">
</head>
<body class="account-body">

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
                <a href="aboutus.php" class="header-menu-link <?php echo nav_active('aboutus.php', $currentPage); ?>">
                    Về chúng tôi
                </a>
                <a href="return-policy.php" class="header-menu-link <?php echo nav_active('return-policy.php', $currentPage); ?>">
                    Chính sách
                </a>
            </nav>
        </div>

        <div class="header-right">
            <form method="GET" action="shop.php" class="header-search-form">
                <input
                    type="text"
                    name="q"
                    class="account-input header-search-input"
                    placeholder="Tìm sách..."
                    value="<?php echo htmlspecialchars($search); ?>"
                >
                <button type="submit" class="account-btn-save header-search-btn">Tìm</button>
            </form>

            <a href="cart.php" class="account-btn-secondary header-cart-btn">Giỏ hàng</a>

            <?php if ($isLoggedIn): ?>
                <div class="header-account">
                    <span class="account-username">
                        Xin chào, <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
                    </span>
                    <div class="header-account-actions">
                        <a href="account-index.php" class="account-btn-secondary header-account-btn">Tài khoản</a>
                        <a href="logout.php" class="account-btn-secondary header-account-btn">Đăng xuất</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="auth-login.php" class="account-btn-secondary header-account-btn">Tài khoản</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="account-main shop-main">
    <div class="container">

        <section class="shop-header">
            <h1 class="account-section-title">Cửa hàng Moonlit</h1>
            <p class="account-section-subtitle">
                Chọn một cuốn sách, pha tách trà, phần còn lại để Moonlit lo ✨
            </p>
        </section>

        <section class="shop-filter-section">
            <div class="account-card">
                <form method="GET" class="shop-filter-form">
                    <input type="hidden" name="q" value="<?php echo htmlspecialchars($search); ?>">

                    <div class="shop-filter-bar">

                        <div class="shop-filter-item">
                            <label class="account-label">Danh mục</label>
                            <select name="category" class="account-input">
                                <option value="">Tất cả</option>
                                <?php foreach ($categories as $cate): ?>
                                    <option
                                        value="<?php echo $cate['CategoryID']; ?>"
                                        <?php echo ($categoryId == $cate['CategoryID']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($cate['CategoryName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="shop-filter-item">
                            <label class="account-label">Nhà xuất bản</label>
                            <select name="publisher" class="account-input">
                                <option value="">Tất cả</option>
                                <?php foreach ($publishers as $pub): ?>
                                    <option
                                        value="<?php echo $pub['PublisherID']; ?>"
                                        <?php echo (!empty($_GET['publisher']) && $_GET['publisher'] == $pub['PublisherID']) ? 'selected' : ''; ?>
                                    >
                                        <?php echo htmlspecialchars($pub['PublisherName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="shop-filter-item">
                            <label class="account-label">Giá từ</label>
                            <input type="number" name="min_price" class="account-input"
                                value="<?php echo htmlspecialchars($_GET['min_price'] ?? ''); ?>">
                        </div>

                        <div class="shop-filter-item">
                            <label class="account-label">Giá đến</label>
                            <input type="number" name="max_price" class="account-input"
                                value="<?php echo htmlspecialchars($_GET['max_price'] ?? ''); ?>">
                        </div>

                        <div class="shop-filter-item">
                            <label class="account-label">Khuyến mãi</label>
                            <select name="sale" class="account-input">
                                <option value="">Tất cả</option>
                                <option value="1" <?php echo (($_GET['sale'] ?? '') === '1') ? 'selected' : ''; ?>>Có</option>
                                <option value="0" <?php echo (($_GET['sale'] ?? '') === '0') ? 'selected' : ''; ?>>Không</option>
                            </select>
                        </div>

                        <div class="shop-filter-item">
                            <label class="account-label">Đánh giá</label>
                            <select name="rating" class="account-input">
                                <option value="">Tất cả</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>"
                                        <?php echo (isset($_GET['rating']) && $_GET['rating'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('★', $i) . str_repeat('☆', 5 - $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="shop-filter-item shop-filter-actions">
                            <button type="submit" class="account-btn-save shop-filter-btn">Lọc</button>
                            <a href="shop.php" class="account-btn-secondary shop-filter-btn">Xóa lọc</a>
                        </div>

                    </div>
                </form>
            </div>
        </section>

        <?php if (!empty($error_message)): ?>
            <div class="account-alert account-alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <section class="shop-products">
            <div class="shop-grid">

                <?php if (empty($products)): ?>
                    <div class="shop-grid-empty">
                        <div class="account-empty-state">
                            <p class="account-empty-text">
                                Hiện chưa có sản phẩm nào phù hợp với bộ lọc. Thử điều chỉnh lại nha!
                            </p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $product): ?>
                        <article class="shop-grid-item">
                            <div class="shop-product-card">

                                <div class="shop-product-image">
                                    <?php if (!empty($product['HasImage'])): ?>
                                        <img
                                            src="product-image.php?id=<?php echo urlencode($product['ProductID']); ?>"
                                            alt="<?php echo htmlspecialchars($product['ProductName']); ?>"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <span class="shop-product-image-placeholder">Moonlit</span>
                                    <?php endif; ?>
                                </div>

                                <h2 class="shop-product-title" title="<?php echo htmlspecialchars($product['ProductName']); ?>">
                                    <?php echo htmlspecialchars($product['ProductName']); ?>
                                </h2>

                                <div class="shop-product-price-row">
                                    <?php if (!empty($product['DiscountPrice'])): ?>
                                        <span class="shop-product-price-current">
                                            <?php echo number_format($product['DiscountPrice'], 0, ',', '.'); ?> đ
                                        </span>
                                        <span class="shop-product-price-old">
                                            <?php echo number_format($product['Price'], 0, ',', '.'); ?> đ
                                        </span>
                                    <?php else: ?>
                                        <span class="shop-product-price-current">
                                            <?php echo number_format($product['Price'], 0, ',', '.'); ?> đ
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="shop-product-actions">
                                    <a href="product-detail.php?id=<?php echo urlencode($product['ProductID']); ?>"
                                       class="account-btn-secondary shop-btn">
                                        Chi tiết
                                    </a>

                                    <a href="cart-add.php?id=<?php echo urlencode($product['ProductID']); ?>&qty=1"
                                       class="account-btn-save shop-btn">
                                        Thêm vào giỏ
                                    </a>

                                    <a href="cart-add.php?id=<?php echo urlencode($product['ProductID']); ?>&qty=1&action=buy_now"
                                       class="account-btn-save shop-btn shop-btn-buy-now">
                                        Mua ngay
                                    </a>
                                </div>

                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </section>

    </div>
</main>

<footer class="site-footer">
    © 2025 Moonlit — All rights reserved.
</footer>

</body>
</html>

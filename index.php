<?php
session_start();
require_once 'db_connect.php';

// Trạng thái đăng nhập
$isLoggedIn      = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = 'index.php';

// Helper nav active
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

// Helper format giá
if (!function_exists('format_price')) {
    function format_price($number): string {
        if ($number === null) return '';
        return number_format((float)$number, 0, ',', '.') . ' đ';
    }
}

// ============================
// LẤY DỮ LIỆU TỪ DB (schema hiện tại: Product + DiscountPrice + Image(BLOB) + Book_Post)
// ============================

// Sách mới nhất
$latestProducts = [];
try {
    $stmt = $pdo->query("
        SELECT
            ProductID,
            ProductName,
            Description,
            Price,
            DiscountPrice,
            (Image IS NOT NULL AND OCTET_LENGTH(Image) > 0) AS HasImage
        FROM Product
        ORDER BY CreatedDate DESC
        LIMIT 4
    ");
    $latestProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Đang khuyến mãi
$saleProducts = [];
try {
    $stmt = $pdo->query("
        SELECT
            ProductID,
            ProductName,
            Description,
            Price,
            DiscountPrice,
            (Image IS NOT NULL AND OCTET_LENGTH(Image) > 0) AS HasImage
        FROM Product
        WHERE DiscountPrice IS NOT NULL
        ORDER BY (Price - DiscountPrice) DESC, CreatedDate DESC
        LIMIT 4
    ");
    $saleProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Bài đăng review sách
$reviewPosts = [];
try {
    $stmt = $pdo->query("
        SELECT
            PostID,
            Title,
            Excerpt,
            ThumbnailUrl,
            CreatedAt,
            AuthorName
        FROM Book_Post
        WHERE Status = 'published'
        ORDER BY CreatedAt DESC
        LIMIT 3
    ");
    $reviewPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Moonlit - Hiệu sách trực tuyến</title>
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
                <input type="text" name="q" class="account-input header-search-input" placeholder="Tìm sách...">
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

<!-- ===================== MAIN ===================== -->
<main class="home-main">

    <!-- ===== BANNER (của mày) ===== -->
    <section class="home-banner">
        <div class="container home-banner-inner">
            <div class="home-banner-content">
                <p class="home-banner-eyebrow">Hiệu sách Moonlit</p>
                <h1 class="home-banner-title">
                    Đọc sách dưới ánh trăng,<br> gom cả thế giới vào giỏ hàng.
                </h1>
                <p class="home-banner-subtitle">
                    Khám phá những cuốn sách bán chạy, ưu đãi độc quyền và các bài review chọn lọc từ Moonlit.
                </p>
                <div class="home-banner-actions">
                    <a href="shop.php" class="account-btn-save home-banner-cta">Khám phá cửa hàng</a>
                    <a href="aboutus.php" class="account-btn-secondary home-banner-ghost">Về Moonlit</a>
                </div>
            </div>

            <div class="home-banner-graphic">
                <div class="home-banner-graphic-card">
                    <div class="home-banner-books">
                        <img
                            src="img/image.png"
                            alt="Moonlit banner"
                            style="width:100%;height:100%;object-fit:cover;border-radius:12px;"
                        >
                    </div>
                    <p class="home-banner-quote">
                        “A room without books is like a body without a soul.”
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== SÁCH MỚI NHẤT ===== -->
    <section class="home-section">
        <div class="container">
            <div class="home-section-header">
                <h2 class="home-section-title">Sách mới nhất</h2>
                <a href="shop.php" class="home-section-link">Xem tất cả</a>
            </div>

            <div class="home-grid-4">
                <?php if (!empty($latestProducts)): ?>
                    <?php foreach ($latestProducts as $p): ?>
                        <article class="product-card">
                            <a href="product-detail.php?id=<?php echo $p['ProductID']; ?>" class="product-card-link">
                                <div class="product-card-image">
                                    <?php if (!empty($p['HasImage'])): ?>
                                        <img src="product-image.php?id=<?php echo $p['ProductID']; ?>"
                                             alt="<?php echo htmlspecialchars($p['ProductName']); ?>">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">Moonlit</div>
                                    <?php endif; ?>
                                </div>

                                <div class="product-card-body">
                                    <h3 class="product-title"><?php echo htmlspecialchars($p['ProductName']); ?></h3>
                                    <p class="product-desc">
                                        <?php echo htmlspecialchars(mb_strimwidth($p['Description'] ?? '', 0, 80, '...')); ?>
                                    </p>

                                    <div class="product-price-row">
                                        <?php if (!empty($p['DiscountPrice'])): ?>
                                            <span class="product-price"><?php echo format_price($p['DiscountPrice']); ?></span>
                                            <span class="product-old-price"><?php echo format_price($p['Price']); ?></span>
                                        <?php else: ?>
                                            <span class="product-price"><?php echo format_price($p['Price']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>

                            <div class="product-card-footer">
                                <a href="product-detail.php?id=<?php echo $p['ProductID']; ?>"
                                   class="account-btn-secondary product-btn">
                                    Xem chi tiết
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="home-empty-text">Chưa có sản phẩm nào.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ===== KHUYẾN MÃI ===== -->
    <section class="home-section home-section-alt">
        <div class="container">
            <div class="home-section-header">
                <h2 class="home-section-title">Đang khuyến mãi</h2>
                <a href="shop.php?sale=1" class="home-section-link">Xem tất cả ưu đãi</a>
            </div>

            <div class="home-grid-4">
                <?php if (!empty($saleProducts)): ?>
                    <?php foreach ($saleProducts as $p): ?>
                        <article class="product-card product-card-sale">
                            <a href="product-detail.php?id=<?php echo $p['ProductID']; ?>" class="product-card-link">
                                <div class="product-card-image">
                                    <?php if (!empty($p['HasImage'])): ?>
                                        <img src="product-image.php?id=<?php echo $p['ProductID']; ?>"
                                             alt="<?php echo htmlspecialchars($p['ProductName']); ?>">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">Moonlit</div>
                                    <?php endif; ?>
                                    <span class="product-badge-sale">Sale</span>
                                </div>

                                <div class="product-card-body">
                                    <h3 class="product-title"><?php echo htmlspecialchars($p['ProductName']); ?></h3>

                                    <div class="product-price-row">
                                        <span class="product-price"><?php echo format_price($p['DiscountPrice']); ?></span>
                                        <span class="product-old-price"><?php echo format_price($p['Price']); ?></span>
                                    </div>

                                    <div class="product-meta-row">
                                        <span class="product-tag product-tag-sale">Khuyến mãi</span>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="home-empty-text">Hiện chưa có chương trình khuyến mãi nào.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ===== BÀI ĐĂNG REVIEW SÁCH ===== -->
    <section class="home-section">
        <div class="container">
            <div class="home-section-header">
                <h2 class="home-section-title">Bài review sách mới</h2>
                <a href="blog.php" class="home-section-link">Xem thêm bài viết</a>
            </div>

            <div class="home-grid-3">
                <?php if (!empty($reviewPosts)): ?>
                    <?php foreach ($reviewPosts as $post): ?>
                        <article class="review-card">
                            <a href="post-detail.php?id=<?php echo urlencode($post['PostID']); ?>" class="review-card-link">
                                <div class="review-card-image">
                                    <?php if (!empty($post['ThumbnailUrl'])): ?>
                                        <img src="<?php echo htmlspecialchars($post['ThumbnailUrl']); ?>"
                                             alt="<?php echo htmlspecialchars($post['Title']); ?>">
                                    <?php else: ?>
                                        <div class="review-image-placeholder">Review sách</div>
                                    <?php endif; ?>
                                </div>

                                <div class="review-card-body">
                                    <h3 class="review-title"><?php echo htmlspecialchars($post['Title']); ?></h3>

                                    <p class="review-meta">
                                        <?php
                                            $author = $post['AuthorName'] ?: 'Moonlit';
                                            $date   = !empty($post['CreatedAt']) ? date('d/m/Y', strtotime($post['CreatedAt'])) : '';
                                            echo 'Bởi ' . htmlspecialchars($author) . ($date ? ' • ' . $date : '');
                                        ?>
                                    </p>

                                    <p class="review-excerpt">
                                        <?php echo htmlspecialchars(mb_strimwidth($post['Excerpt'] ?? '', 0, 120, '...')); ?>
                                    </p>
                                </div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="home-empty-text">Chưa có bài review nào. Admin hãy thêm bài trong trang quản trị.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    © 2025 Moonlit — All rights reserved.
</footer>

</body>
</html>

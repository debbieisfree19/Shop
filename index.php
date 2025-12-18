<?php
session_start();
require_once 'db_connect.php';

// Trạng thái đăng nhập
$isLoggedIn = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? '';
$currentPage = 'index.php';

// Helper nav active
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string
    {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

// Helper format giá
if (!function_exists('format_price')) {
    function format_price($number): string
    {
        if ($number === null)
            return '';
        return number_format((float) $number, 0, ',', '.') . ' đ';
    }
}

// ============================
// LẤY DỮ LIỆU TỪ DB (schema hiện tại: Product  + Image(BLOB) + Book_Post)
// ============================

// Banner
$banners = [];

try {
    $stmt = $pdo->query("
        SELECT
            BannerID,
            Title,
            ImageUrl,
            (ImageBinary IS NOT NULL AND OCTET_LENGTH(ImageBinary) > 0) AS HasBinary
        FROM Banner
        ORDER BY BannerID DESC
    ");
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}



// Sách mới nhất (4 cuốn, có xét SALE)
$latestProducts = [];
try {
    $stmt = $pdo->query("
        SELECT
            p.ProductID,
            p.ProductName,
            p.Description,
            p.CreatedDate,

            MIN(
                CASE 
                    WHEN ps.DiscountedPrice IS NOT NULL 
                    THEN ps.DiscountedPrice 
                    ELSE s.SellPrice 
                END
            ) AS MinDisplayPrice,

            MAX(s.SellPrice) AS MaxOriginalPrice,

            MAX(ps.DiscountedPrice IS NOT NULL) AS HasSale,

            (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage
        FROM Product p
        JOIN SKU s 
            ON s.ProductID = p.ProductID
        LEFT JOIN PRODUCT_SALE ps
            ON ps.SKUID = s.SKUID
            AND ps.StartDate <= NOW()
            AND (ps.EndDate IS NULL OR ps.EndDate >= NOW())
        WHERE
            p.Status = 1
            AND s.Status = 1
        GROUP BY
            p.ProductID,
            p.ProductName,
            p.Description,
            p.CreatedDate,
            p.Image
        ORDER BY p.CreatedDate DESC
        LIMIT 4
    ");
    $latestProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}



// Đang khuyến mãi (4 cuốn mới nhất có sale còn hiệu lực)
$saleProducts = [];
try {
    $stmt = $pdo->query("
        SELECT
            p.ProductID,
            p.ProductName,
            p.Description,
            p.Price,
            ps.DiscountedPrice,
            p.CreatedDate,
            (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage
        FROM PRODUCT_SALE ps
        JOIN SKU s
            ON s.SKUID = ps.SKUID
        JOIN Product p
            ON p.ProductID = s.ProductID
        WHERE
            p.Status = 1
            AND s.Status = 1
            AND ps.StartDate <= NOW()
            AND (ps.EndDate IS NULL OR ps.EndDate >= NOW())
        ORDER BY p.CreatedDate DESC
        LIMIT 4
    ");
    $saleProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

//Book of the month
$bookOfTheMonth = null;

try {
    $stmt = $pdo->query("
        SELECT
            p.ProductID,
            p.ProductName,
            p.Description,
            SUM(oi.Quantity) AS TotalSold,

            MIN(
                CASE
                    WHEN ps.DiscountedPrice IS NOT NULL
                    THEN ps.DiscountedPrice
                    ELSE s.SellPrice
                END
            ) AS MinDisplayPrice,

            MAX(s.SellPrice) AS MaxOriginalPrice,
            MAX(ps.DiscountedPrice IS NOT NULL) AS HasSale,

            (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage

        FROM Order_Items oi
        JOIN SKU s ON s.SKUID = oi.SKU_ID
        JOIN Product p ON p.ProductID = s.ProductID
        LEFT JOIN PRODUCT_SALE ps
            ON ps.SKUID = s.SKUID
            AND ps.StartDate <= NOW()
            AND (ps.EndDate IS NULL OR ps.EndDate >= NOW())

        WHERE p.Status = 1 AND s.Status = 1

        GROUP BY
            p.ProductID,
            p.ProductName,
            p.Description,
            p.Image

        ORDER BY TotalSold DESC
        LIMIT 1
    ");

    $bookOfTheMonth = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
}




?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Moonlit - Hiệu sách trực tuyến</title>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body">

    <!-- ===================== HEADER ===================== -->
    <header class="account-header site-header">
        <div class="container header-inner">
            <div class="header-left">
                <a href="index.php" class="logo-link header-logo">
                    <img src="img/image.png" alt="Moonlit logo" class="logo-img">

                </a>

                <nav class="header-menu">
                    <a href="index.php" class="header-menu-link <?php echo nav_active('index.php', $currentPage); ?>">
                        Trang chủ
                    </a>
                    <a href="shop.php" class="header-menu-link <?php echo nav_active('shop.php', $currentPage); ?>">
                        Cửa hàng
                    </a>
                    <a href="aboutus.php"
                        class="header-menu-link <?php echo nav_active('aboutus.php', $currentPage); ?>">
                        Về chúng tôi
                    </a>
                    <a href="policy.php" class="header-menu-link <?php echo nav_active('policy.php', $currentPage); ?>">
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

        <!-- ===== BANNER ===== -->
        <?php if (!empty($banners)): ?>
            <section class="home-intro-banner">
                <div class="container">
                    <div class="intro-banner-grid">

                        <!-- ===== GIỚI THIỆU MOONLIT (BÊN TRÁI) ===== -->
                        <div class="moonlit-intro-card">
                            <p class="intro-eyebrow"><strong>Hiệu sách Moonlit</strong></p>

                            <h2 class="intro-title">
                                Không gian sách dành cho mọi tâm hồn yêu đọc
                            </h2>

                            <p class="intro-desc">
                                Chúng tôi chọn lọc từng đầu sách với mong muốn mang đến
                                trải nghiệm đọc trọn vẹn – nơi mỗi cuốn sách đều có câu chuyện
                                riêng chờ bạn khám phá.
                            </p>

                            <div class="intro-actions">

                                <a href="aboutus.php" class="btn btn-outline-secondary">
                                    Về Moonlit
                                </a>
                            </div>
                        </div>

                        <!-- ===== BANNER CAROUSEL (BÊN PHẢI) ===== -->
                        <div class="banner-right-wrap">
                            <div id="carouselExampleIndicators" class="carousel slide banner-carousel"
                                data-bs-ride="carousel">

                                <!-- Indicators -->
                                <div class="carousel-indicators">
                                    <?php foreach ($banners as $i => $b): ?>
                                        <button type="button" data-bs-target="#carouselExampleIndicators"
                                            data-bs-slide-to="<?php echo $i; ?>"
                                            class="<?php echo $i === 0 ? 'active' : ''; ?>">
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Slides -->
                                <div class="carousel-inner">
                                    <?php foreach ($banners as $i => $b): ?>
                                        <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                                            <?php if (!empty($b['HasBinary'])): ?>
                                                <img src="banner-image.php?id=<?php echo $b['BannerID']; ?>"
                                                    class="d-block w-100 banner-img"
                                                    alt="<?php echo htmlspecialchars($b['Title']); ?>">
                                            <?php else: ?>
                                                <img src="<?php echo htmlspecialchars($b['ImageUrl']); ?>"
                                                    class="d-block w-100 banner-img"
                                                    alt="<?php echo htmlspecialchars($b['Title']); ?>">
                                            <?php endif; ?>

                                            <?php if (!empty($b['Title'])): ?>
                                                <div class="carousel-caption">
                                                    <h5><?php echo htmlspecialchars($b['Title']); ?></h5>
                                                    <a href="shop.php" class="btn btn-warning btn-sm mt-2">
                                                        Khám phá cửa hàng
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Controls -->
                                <button class="carousel-control-prev" type="button"
                                    data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                </button>

                                <button class="carousel-control-next" type="button"
                                    data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                </button>

                            </div>
                        </div>

                    </div>
                </div>
            </section>
        <?php endif; ?>
        <!-- ===== BOOK OF THE MONTH ===== -->
        <section class="home-section home-section-featured">
            <div class="container">
                <div class="home-section-header">
                    <h2 class="home-section-title">📚 Book of the Month</h2>
                </div>

                <?php if ($bookOfTheMonth): ?>

                    <!-- ===== CÓ DỮ LIỆU ===== -->
                    <div class="featured-book-card">
                        <div class="featured-book-image">
                            <?php if ($bookOfTheMonth['HasImage']): ?>
                                <img src="product-image.php?id=<?php echo $bookOfTheMonth['ProductID']; ?>">
                            <?php else: ?>
                                <div class="product-image-placeholder">Moonlit</div>
                            <?php endif; ?>
                        </div>

                        <div class="featured-book-info">
                            <h3><?php echo htmlspecialchars($bookOfTheMonth['ProductName']); ?></h3>

                            <p class="featured-desc">
                                <?php echo htmlspecialchars(
                                    mb_strimwidth($bookOfTheMonth['Description'] ?? '', 0, 150, '...')
                                ); ?>
                            </p>

                            <div class="product-price-row">
                                <?php if ($bookOfTheMonth['HasSale']): ?>
                                    <span class="product-price text-danger">
                                        <?php echo format_price($bookOfTheMonth['MinDisplayPrice']); ?>
                                    </span>
                                    <span class="product-old-price">
                                        <?php echo format_price($bookOfTheMonth['MaxOriginalPrice']); ?>
                                    </span>
                                    <span class="product-badge-sale">Bán chạy</span>
                                <?php else: ?>
                                    <span class="product-price">
                                        <?php echo format_price($bookOfTheMonth['MinDisplayPrice']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p class="featured-sold">
                                🔥 Đã bán: <?php echo (int) $bookOfTheMonth['TotalSold']; ?> cuốn
                            </p>

                            <a href="product-detail.php?id=<?php echo $bookOfTheMonth['ProductID']; ?>"
                                class="account-btn-save">
                                Xem chi tiết
                            </a>
                        </div>
                    </div>

                <?php else: ?>

                    <!-- ===== CHƯA CÓ DỮ LIỆU ===== -->
                    <div class="account-empty-state featured-empty">
                        <p class="account-empty-text">
                            📭 Hiện chưa có sách nào đủ dữ liệu để trở thành <strong>Book of the Month</strong>.
                        </p>
                        <p class="account-empty-subtext">
                            Hãy quay lại sau khi có đơn hàng đầu tiên nhé ✨
                        </p>
                    </div>

                <?php endif; ?>
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
                                            <?php if ($p['HasSale']): ?>
                                                <span class="product-price text-danger">
                                                    <?php echo format_price($p['MinDisplayPrice']); ?>
                                                </span>

                                                <span class="product-old-price">
                                                    <?php echo format_price($p['MaxOriginalPrice']); ?>
                                                </span>

                                                <span class="product-badge-sale">Sale</span>

                                            <?php else: ?>
                                                <?php if ($p['MinDisplayPrice'] == $p['MaxOriginalPrice']): ?>
                                                    <span class="product-price">
                                                        <?php echo format_price($p['MinDisplayPrice']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="product-price">
                                                        <?php echo format_price($p['MinDisplayPrice']); ?>
                                                        -
                                                        <?php echo format_price($p['MaxOriginalPrice']); ?>
                                                    </span>
                                                <?php endif; ?>
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
                            <article class="shop-product-card product-card-sale">
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
                                            <span
                                                class="product-price"><?php echo format_price($p['DiscountedPrice']); ?></span>
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



    </main>

    <footer class="site-footer">
        © 2025 Moonlit — All rights reserved.
    </footer>

</body>

</html>

<?php
session_start();
require_once 'db_connect.php';

$isLoggedIn      = isset($_SESSION['user_id']);
$currentUserID   = $_SESSION['user_id'] ?? null;
$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = basename($_SERVER['PHP_SELF']);

/* =====================
   CHUYÊN MỤC NỔI BẬT
===================== */
$categories = [
    'Góc độc giả nổi bật'   => 'reader_corner',
    'Review sách'          => 'book_review',
    'Tác giả Việt Nam'     => 'vietnam_authors',
    'Tin khuyến mãi'       => 'promotions',
    'Xu hướng đọc sách'    => 'reading_trends'
];

$selectedCategory = $_GET['category'] ?? '';
$categoryBlogs = [];

if ($selectedCategory && in_array($selectedCategory, $categories)) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM Blog
        WHERE Section = ?
        ORDER BY CreatedDate DESC
    ");
    $stmt->execute([$selectedCategory]);
    $categoryBlogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =====================
   BLOG CHI TIẾT
===================== */
$selectedBlogID = $_GET['blog_id'] ?? null;
$selectedBlog = null;

if ($selectedBlogID) {
    $stmt = $pdo->prepare("SELECT * FROM Blog WHERE BlogID = ?");
    $stmt->execute([$selectedBlogID]);
    $selectedBlog = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Blogs - Moonlit</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="moonlit-style.css">

<style>
.blog-hero {
    margin-bottom: 32px;
}
.blog-hero h1 {
    color: var(--color-deep-blue);
}
.blog-hero-divider {
    width: 4px;
    height: 60px;
    background: var(--color-deep-blue);
    border-radius: 2px;
}
.blog-section {
    background: #fff;
    border-radius: 16px;
    padding: 24px 28px;
    margin-bottom: 32px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
}
.blog-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
}
.blog-card {
    flex: 1 1 30%;
    background: #f8fafc;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.blog-card:hover {
    background: #eef4ff;
    transform: translateY(-2px);
}
.blog-card img {
    width: 100%;
    height: 180px;
    object-fit: cover;
}
.blog-card-content {
    padding: 16px;
}
.blog-card-content h3 {
    color: var(--color-deep-blue);
    margin-bottom: 8px;
}
.blog-card-content p {
    color: #555;
}
.read-more {
    font-weight: 600;
    color: var(--color-deep-blue);
    text-decoration: none;
}
.category-list {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}
.category-list a {
    padding: 8px 14px;
    border-radius: 12px;
    background: #f0f4ff;
    color: var(--color-deep-blue);
    text-decoration: none;
}
.category-list a.active,
.category-list a:hover {
    background: var(--color-deep-blue);
    color: #fff;
}
.blog-detail img {
    width: 100%;
    max-height: 360px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 16px;
}
.blog-detail-content {
    line-height: 1.8;
    color: #333;
}
</style>
</head>

<body class="account-body">

<header class="account-header site-header">
<div class="container header-inner">
<div class="header-left">
    <a href="index.php" class="logo-link header-logo">
        <img src="img/image.png" class="logo-img">
    </a>
    <nav class="header-menu">
        <a href="index.php" class="header-menu-link">Trang chủ</a>
        <a href="shop.php" class="header-menu-link">Cửa hàng</a>
        <a href="aboutus.php" class="header-menu-link">Về chúng tôi</a>
        <a href="return-policy.php" class="header-menu-link">Chính sách</a>
        <a href="blogs.php" class="header-menu-link nav-active">Blogs</a>
    </nav>
</div>
<div class="header-right">
    <a href="cart.php" class="account-btn-secondary">Giỏ hàng</a>
    <?php if ($isLoggedIn): ?>
        <span>Xin chào <strong><?=htmlspecialchars($currentUsername)?></strong></span>
        <a href="logout.php" class="account-btn-secondary">Đăng xuất</a>
    <?php else: ?>
        <a href="auth-login.php" class="account-btn-secondary">Tài khoản</a>
    <?php endif; ?>
</div>
</div>
</header>

<main class="account-main">
<div class="container">

<section class="blog-hero">
<div style="display:flex;gap:24px;">
    <h1>Blogs & News</h1>
    <div class="blog-hero-divider"></div>
    <p>
        Đây là nơi Moonlit chia sẻ những câu chuyện, cảm hứng đọc sách và xu hướng văn hóa đọc
        dành cho cộng đồng yêu sách. Bạn có thể tìm đọc cái bài blog do Moonlit đăng tải theo các chuyên mục bên dưới.
    </p>
</div>
</section>

<section class="blog-section">
<h2>Chuyên mục nổi bật</h2>
<br>

<div class="category-list">
<?php foreach($categories as $name => $id): ?>
    <a href="blogs.php?category=<?=$id?>" class="<?= $selectedCategory==$id?'active':'' ?>">
        <?= $name ?>
    </a>
<?php endforeach; ?>
</div>

<?php if($categoryBlogs): ?>
<div class="blog-grid">
<?php foreach($categoryBlogs as $blog): ?>
<div class="blog-card">
    <img src="<?= htmlspecialchars($blog['Thumbnail']) ?>" alt="<?= htmlspecialchars($blog['Title']) ?>">
    <div class="blog-card-content">
        <h3><?= htmlspecialchars($blog['Title']) ?></h3>
        <p><?= mb_substr(strip_tags($blog['Content']),0,120) ?>...</p>
        <a class="read-more"
           href="blogs.php?category=<?=$selectedCategory?>&blog_id=<?=$blog['BlogID']?>">
           Đọc thêm
        </a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>

<?php if($selectedBlog): ?>
<section class="blog-section blog-detail">
<h2><?= htmlspecialchars($selectedBlog['Title']) ?></h2>
<br>
<img src="<?= htmlspecialchars($selectedBlog['Thumbnail']) ?>" alt="<?= htmlspecialchars($selectedBlog['Title']) ?>">
<div class="blog-detail-content">
<?= nl2br($selectedBlog['Content']) ?>
</div>
</section>
<?php endif; ?>

</div>
</main>

<footer class="site-footer">
© 2025 Moonlit — All rights reserved.
</footer>

</body>
</html>

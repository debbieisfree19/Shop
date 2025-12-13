<?php
/**
 * MOONLIT STORE - PRODUCT DETAIL PAGE (BLOB IMAGE)
 */

session_start();
require_once 'db_connect.php';

// Trạng thái đăng nhập
$isLoggedIn      = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? '';
$currentUserId   = $_SESSION['user_id'] ?? '';

// Lấy id sản phẩm từ query string
$productKey = trim($_GET['id'] ?? '');
$skuKey     = trim($_GET['sku'] ?? '');

if ($productKey === '' && $skuKey === '') {
    header('Location: shop.php');
    exit;
}


// ============================
// Xử lý submit đánh giá
// ============================
$review_error   = '';
$review_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    if (!$isLoggedIn) {
        $review_error = 'Bạn cần đăng nhập để gửi đánh giá.';
    } else {
        $rating  = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = trim($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $review_error = 'Vui lòng chọn số sao từ 1 đến 5.';
        } elseif ($comment === '') {
            $review_error = 'Bạn hãy viết vài dòng cảm nhận nha.';
        } else {
            try {
                // Review table trong script SQL là CreatedAt (không phải CreatedDate)
                $sqlInsert = "
                    INSERT INTO Review (ProductID, UserID, Rating, Comment, CreatedAt)
                    VALUES (:productId, :userId, :rating, :comment, :createdAt)
                ";
                $stmtIns = $pdo->prepare($sqlInsert);
                $stmtIns->execute([
                    ':productId' => $product['ProductID'],
                    ':userId'    => $currentUserId,
                    ':rating'    => $rating,
                    ':comment'   => $comment,
                    ':createdAt' => date('Y-m-d H:i:s'),
                ]);

                header('Location: product-detail.php?id=' . urlencode($productId) . '&review=success');
                exit;
            } catch (Exception $e) {
                $review_error = 'Không thể lưu đánh giá. Thử lại sau nha.';
            }
        }
    }
}

if (isset($_GET['review']) && $_GET['review'] === 'success') {
    $review_success = 'Cảm ơn bạn đã chia sẻ cảm nhận 💛';
}

// ============================
// Lấy thông tin sản phẩm
// ============================
$product = null;
$error_message = '';

try {
    $where = "";
$params = [];

if ($productKey !== '') {
    $where = "p.ProductID = :key";
    $params[':key'] = $productKey;
} else {
    $where = "p.SKU = :key";
    $params[':key'] = $skuKey;
}

$sql = "
    SELECT
        p.ProductID,
        p.SKU,
        p.ProductName,
        p.Description,
        p.Price,
        p.DiscountPrice,
        p.CreatedDate,
        (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage,
        pub.PublisherName,
        GROUP_CONCAT(DISTINCT c.CategoryName SEPARATOR ', ') AS Categories
    FROM Product p
    LEFT JOIN Publisher pub ON p.PublisherID = pub.PublisherID
    LEFT JOIN Product_Categories pc ON p.ProductID = pc.ProductID
    LEFT JOIN Categories c ON pc.CategoryID = c.CategoryID
    WHERE $where
    GROUP BY
        p.ProductID, p.SKU, p.ProductName, p.Description, p.Price, p.DiscountPrice,
        p.CreatedDate, HasImage, pub.PublisherName
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$product = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$product) {
        $error_message = 'Sản phẩm không tồn tại hoặc đã bị ẩn.';
    }
} catch (Exception $e) {
    $error_message = 'Không thể tải thông tin sản phẩm: ' . $e->getMessage();
}

// ============================
// Lấy vài sản phẩm liên quan
// ============================
$relatedProducts = [];
if ($product) {
    try {
        $firstCategorySql = "
            SELECT pc.CategoryID
            FROM Product_Categories pc
            WHERE pc.ProductID = :id
            LIMIT 1
        ";
        $cateStmt = $pdo->prepare($firstCategorySql);
        $cateStmt->execute([':productId' => $product['ProductID']
]);
        $cateRow = $cateStmt->fetch(PDO::FETCH_ASSOC);

        if ($cateRow) {
            $cateId = (int)$cateRow['CategoryID'];

            $relatedSql = "
                SELECT
                    p.ProductID,
                    p.ProductName,
                    p.Price,
                    p.DiscountPrice,
                    (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage
                FROM Product p
                INNER JOIN Product_Categories pc ON p.ProductID = pc.ProductID
                WHERE pc.CategoryID = :cateId
                  AND p.ProductID <> :id
                ORDER BY p.CreatedDate DESC
                LIMIT 4
            ";

            $relStmt = $pdo->prepare($relatedSql);
            $relStmt->execute([
                ':cateId' => $cateId,
                 ':productId' => $product['ProductID']]);
            $relatedProducts = $relStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {}
}

// ============================
// Lấy review khách hàng
// ============================
$reviews = [];
if ($product) {
    try {
        // User_Account không có FullName -> dùng Email
        $reviewSql = "
            SELECT
                r.Rating,
                r.Comment,
                r.CreatedAt,
                u.Email AS DisplayName
            FROM Review r
            LEFT JOIN User_Account u ON r.UserID = u.UserID
            WHERE r.ProductID = :id
            ORDER BY r.CreatedAt DESC
            LIMIT 20
        ";
        $reviewStmt = $pdo->prepare($reviewSql);
        $reviewStmt->execute([':productId' => $product['ProductID'],
]);
        $reviews = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $product ? htmlspecialchars($product['ProductName']) . ' - Moonlit Store' : 'Sản phẩm - Moonlit Store'; ?>
    </title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                <a href="index.php" class="header-menu-link">Trang chủ</a>
                <a href="shop.php" class="header-menu-link nav-active">Cửa hàng</a>
                <a href="aboutus.php" class="header-menu-link">Về chúng tôi</a>
                <a href="return-policy.php" class="header-menu-link">Chính sách</a>
            </nav>
        </div>

        <div class="header-right">
            <form method="GET" action="shop.php" class="header-search-form">
                <input type="text" name="q" class="account-input header-search-input" placeholder="Tìm sách...">
                <button type="submit" class="account-btn-save header-search-btn">Tìm</button>
            </form>

            <a href="cart.php" class="account-btn-secondary header-cart-btn">Giỏ hàng</a>

            <div class="header-account">
                <?php if ($isLoggedIn): ?>
                    <span class="account-username">
                        Xin chào, <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
                    </span>
                    <div class="header-account-actions">
                        <a href="account-index.php" class="account-btn-secondary header-account-btn">Tài khoản</a>
                        <a href="logout.php" class="account-btn-secondary header-account-btn">Đăng xuất</a>
                    </div>
                <?php else: ?>
                    <a href="auth-login.php" class="account-btn-secondary header-account-btn">Đăng nhập</a>
                    <a href="auth-register.php" class="account-btn-secondary header-account-btn">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="account-main">
    <div class="container">

        <div class="mb-3 small">
            <a href="shop.php" class="text-decoration-none" style="color: var(--color-deep-blue);">Cửa hàng</a>
            <span class="text-secondary"> / </span>
            <?php if ($product): ?>
                <span><?php echo htmlspecialchars($product['ProductName']); ?></span>
            <?php else: ?>
                <span>Sản phẩm</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger account-alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php else: ?>

            <div class="account-card mb-3">
                <div class="product-detail-layout">

                    <!-- ẢNH SẢN PHẨM (BLOB) -->
                    <div class="product-gallery">
                        <div class="product-gallery-main">
                            <div class="shop-product-image">
                                <?php if (!empty($product['HasImage'])): ?>
                                    <img
                                        id="product-main-image"
                                        src="product-image.php?id=<?php echo urlencode($product['ProductID']); ?>"
                                        alt="<?php echo htmlspecialchars($product['ProductName']); ?>"
                                    >
                                <?php else: ?>
                                    <span class="shop-product-image-placeholder">Moonlit</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- THÔNG TIN -->
                    <div class="product-detail-info">
                        <h1 class="account-section-title mb-2">
                            <?php echo htmlspecialchars($product['ProductName']); ?>
                        </h1>

                        <?php if (!empty($product['Categories'])): ?>
                            <p class="mb-1 small text-secondary">
                                Thể loại: <?php echo htmlspecialchars($product['Categories']); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($product['PublisherName'])): ?>
                            <p class="mb-3 small text-secondary">
                                Nhà xuất bản: <?php echo htmlspecialchars($product['PublisherName']); ?>
                            </p>
                        <?php endif; ?>

                        <div class="mb-3">
                            <?php if (!empty($product['DiscountPrice'])): ?>
                                <span class="cart-price-current" style="font-size: 22px;">
                                    <?php echo number_format($product['DiscountPrice'], 0, ',', '.'); ?> đ
                                </span>
                                <span class="cart-price-old" style="font-size: 16px; margin-left: 8px;">
                                    <?php echo number_format($product['Price'], 0, ',', '.'); ?> đ
                                </span>
                            <?php else: ?>
                                <span class="cart-price-current" style="font-size: 22px;">
                                    <?php echo number_format($product['Price'], 0, ',', '.'); ?> đ
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="product-description-box">
                            <?php if (!empty($product['Description'])): ?>
                                <p class="mb-0" style="font-size: 14px;">
                                    <?php echo nl2br(htmlspecialchars($product['Description'])); ?>
                                </p>
                            <?php else: ?>
                                <p class="mb-0" style="font-size: 14px;">Cuốn sách đang chờ Moonlit viết mô tả ✨</p>
                            <?php endif; ?>
                        </div>

                        <form action="cart-add.php" method="GET" class="d-flex flex-wrap align-items-end gap-3 mt-3">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($product['ProductID']); ?>">

                            <div>
                                <label for="qty" class="account-label mb-1">Số lượng</label>
                                <input type="number" id="qty" name="qty" min="1" value="1"
                                       class="account-input" style="max-width: 90px;">
                            </div>

                            <div class="d-flex gap-2 mt-2">
                                <button type="submit" name="action" value="add_to_cart" class="account-btn-save">
                                    Thêm vào giỏ
                                </button>
                                <button type="submit" name="action" value="buy_now" class="account-btn-secondary">
                                    Mua ngay
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

            <!-- REVIEWS -->
            <div class="account-card mb-3">
                <h2 class="account-card-title mb-3">Đánh giá từ khách hàng</h2>

                <?php if (!empty($review_error)): ?>
                    <div class="alert alert-danger account-alert"><?php echo htmlspecialchars($review_error); ?></div>
                <?php endif; ?>

                <?php if (!empty($review_success)): ?>
                    <div class="alert alert-success account-alert"><?php echo htmlspecialchars($review_success); ?></div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_review">

                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="account-label">Đánh giá của bạn</label>
                                <select name="rating" class="account-input" required>
                                    <option value="">Chọn số sao</option>
                                    <option value="5">★★★★★ - Tuyệt vời</option>
                                    <option value="4">★★★★☆ - Rất tốt</option>
                                    <option value="3">★★★☆☆ - Bình thường</option>
                                    <option value="2">★★☆☆☆ - Tạm ổn</option>
                                    <option value="1">★☆☆☆☆ - Chưa ưng</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="account-label">Nhận xét</label>
                                <textarea name="comment" rows="3" class="account-input"
                                          placeholder="Chia sẻ cảm nhận..." required></textarea>
                            </div>
                        </div>

                        <div class="mt-3 flex-end">
                            <button type="submit" class="account-btn-save">Gửi đánh giá</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="mb-4" style="font-size: 14px;">
                        Bạn cần <a href="auth-login.php" style="color: var(--color-deep-blue);">đăng nhập</a> để viết đánh giá.
                    </p>
                <?php endif; ?>

                <?php if (empty($reviews)): ?>
                    <p class="mb-0" style="font-size: 14px; color: var(--color-secondary);">
                        Chưa có đánh giá nào. Viết cái đầu tiên đi ✨
                    </p>
                <?php else: ?>
                    <div class="account-orders-list">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="account-order-card" style="padding: 16px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong style="font-size: 14px;">
                                        <?php echo htmlspecialchars($rev['DisplayName'] ?? 'Khách hàng ẩn danh'); ?>
                                    </strong>
                                    <span class="small text-secondary">
                                        <?php
                                        if (!empty($rev['CreatedAt'])) echo date('d/m/Y', strtotime($rev['CreatedAt']));
                                        ?>
                                    </span>
                                </div>

                                <div class="mb-1" style="color: #FFC107; font-size: 14px;">
                                    <?php
                                    $stars = (int)($rev['Rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++) echo $i <= $stars ? '★' : '☆';
                                    ?>
                                </div>

                                <?php if (!empty($rev['Comment'])): ?>
                                    <p class="mb-0" style="font-size: 14px;">
                                        <?php echo nl2br(htmlspecialchars($rev['Comment'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RELATED -->
            <?php if (!empty($relatedProducts)): ?>
                <div class="account-card">
                    <h2 class="account-card-title mb-3">Có thể bà cũng thích</h2>

                    <div class="row g-3">
                        <?php foreach ($relatedProducts as $rel): ?>
                            <div class="col-6 col-md-3">
                                <div class="account-card h-100 d-flex flex-column" style="padding: 16px;">

                                    <div class="shop-product-image mb-2">
                                        <?php if (!empty($rel['HasImage'])): ?>
                                            <img
                                                src="product-image.php?id=<?php echo urlencode($rel['ProductID']); ?>"
                                                alt="<?php echo htmlspecialchars($rel['ProductName']); ?>"
                                            >
                                        <?php else: ?>
                                            <span class="shop-product-image-placeholder">Moonlit</span>
                                        <?php endif; ?>
                                    </div>

                                    <h3 class="account-order-item-name text-truncate mb-1"
                                        title="<?php echo htmlspecialchars($rel['ProductName']); ?>">
                                        <?php echo htmlspecialchars($rel['ProductName']); ?>
                                    </h3>

                                    <p class="fw-bold mb-2" style="color: #DC3545;">
                                        <?php
                                        $priceShow = !empty($rel['DiscountPrice']) ? $rel['DiscountPrice'] : $rel['Price'];
                                        echo number_format($priceShow, 0, ',', '.'); ?> đ
                                    </p>

                                    <div class="mt-auto d-grid gap-1">
                                        <a href="product-detail.php?id=<?php echo urlencode($rel['ProductID']); ?>"
                                           class="account-btn-secondary text-center text-decoration-none">
                                            Xem chi tiết
                                        </a>
                                        <a href="cart-add.php?id=<?php echo urlencode($rel['ProductID']); ?>&qty=1"
                                           class="account-btn-save text-center text-decoration-none">
                                            Thêm vào giỏ
                                        </a>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<footer class="site-footer">
    © <?php echo date('Y'); ?> Moonlit Store. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

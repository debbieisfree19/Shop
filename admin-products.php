<?php
/**
 * MOONLIT STORE - ADMIN PRODUCT MANAGEMENT
 * - Thêm sách mới
 * - Upload hình (LONGBLOB)
 * - Gán danh mục
 * - Xóa sách
 */


require_once 'db_connect.php';
ob_start();
// ====== Check quyền admin ======
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'Admin')) {
    // header("Location: auth_login.php");
    // exit;
}

$currentUsername = $_SESSION['username'] ?? 'Admin';

$success_message = '';
$error_message = '';

// ====== Hàm sinh ProductID dạng P00001 ======
function generateProductID(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(ProductID, 2) AS UNSIGNED)) AS max_id FROM Product");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($row['max_id'] ?? 0) + 1;
    return 'P' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}
// ====== Hàm sinh Category dạng C00001 ======
function generateCategoryID(PDO $pdo): string
{
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(CategoryID, 2) AS UNSIGNED)) AS max_id
        FROM Categories
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($row['max_id'] ?? 0) + 1;

    return 'C' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}
// ====== Hàm sinh Publisher dạng N00001 ======
function generatePublisherID(PDO $pdo): string
{
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(PublisherID, 2) AS UNSIGNED))
        FROM Publisher
        WHERE PublisherID LIKE 'N%'
    ");

    $next = ((int) $stmt->fetchColumn()) + 1;
    return 'N' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

require_once 'db_connect.php';
//====== Hàm sinh SKU dạng SKU001 ======
function generateSKUID(PDO $pdo): string
{
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(SKUID, 4) AS UNSIGNED)) AS max_id
        FROM SKU
        WHERE SKUID LIKE 'SKU%'
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($row['max_id'] ?? 0) + 1;

    return 'SKU' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
}
//====== Hàm sinh AUTHOR dạng A000001 ======
function generateAuthorID(PDO $pdo): string
{
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(AuthorID, 2) AS UNSIGNED))
        FROM Book_Author
        WHERE AuthorID LIKE 'A%'
    ");
    $next = ((int) $stmt->fetchColumn()) + 1;
    return 'A' . str_pad($next, 5, '0', STR_PAD_LEFT);
}

/* ===== AJAX LOAD SKU ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ajax_load_sku') {
    header('Content-Type: application/json; charset=utf-8');
    ob_clean();

    try {
        $pid = trim($_POST['product_id'] ?? '');
        if ($pid === '') {
            throw new Exception('Thiếu ProductID');
        }

        $stmt = $pdo->prepare("
            SELECT SKUID, Format, BuyPrice, SellPrice, Stock, Status
            FROM SKU
            WHERE ProductID = :pid
            ORDER BY SKUID
        ");
        $stmt->execute([':pid' => $pid]);

        echo json_encode([
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
/* ===== AJAX DELETE SKU ===== */ else if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'ajax_delete_sku'
) {
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $skuid = trim($_POST['skuid'] ?? '');
        if ($skuid === '') {
            throw new Exception('Thiếu SKUID');
        }

        // Lấy ProductID
        $stmt = $pdo->prepare("
            SELECT ProductID FROM SKU WHERE SKUID = :skuid
        ");
        $stmt->execute([':skuid' => $skuid]);
        $productId = $stmt->fetchColumn();

        if (!$productId) {
            throw new Exception('SKU không tồn tại');
        }

        //KIỂM TRA SKU ĐÃ TỪNG BÁN CHƯA
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM order_items
            WHERE SKU_ID = :skuid
        ");
        $stmt->execute([':skuid' => $skuid]);
        $soldCount = (int) $stmt->fetchColumn();

        $pdo->beginTransaction();

        if ($soldCount > 0) {
            // ===== ĐÃ BÁN → KHÓA SKU =====
            $pdo->prepare("
                UPDATE SKU
                SET Status = 0
                WHERE SKUID = :skuid
            ")->execute([':skuid' => $skuid]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'mode' => 'locked',
                'message' => 'SKU đã có đơn hàng → chuyển sang Ngừng bán'
            ]);
            exit;
        }

        // ===== CHƯA BÁN → XÓA CỨNG =====

        // Xóa sale nếu có
        $pdo->prepare("
            DELETE FROM PRODUCT_SALE
            WHERE SKUID = :skuid
        ")->execute([':skuid' => $skuid]);

        // Xóa SKU
        $pdo->prepare("
            DELETE FROM SKU
            WHERE SKUID = :skuid
        ")->execute([':skuid' => $skuid]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'mode' => 'deleted'
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}


//Xử lý load trang sau mỗi lần thêm
// ===== AJAX thêm category  =====
else if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'ajax_add_category'
) {
    if (ob_get_length())
        ob_clean();

    try {
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['category_desc'] ?? '');

        if ($categoryName === '') {
            throw new Exception('Tên danh mục không được để trống');
        }

        $categoryId = generateCategoryID($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO Categories (CategoryID, CategoryName, Description)
            VALUES (:id, :name, :desc)
        ");
        $stmt->execute([
            ':id' => $categoryId,
            ':name' => $categoryName,
            ':desc' => $description ?: null
        ]);

        echo json_encode([
            'success' => true,
            'id' => $categoryId,
            'name' => $categoryName
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
// ===== AJAX thêm publisher =====
else if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'ajax_add_publisher'
) {
    if (ob_get_length())
        ob_clean();

    try {
        $publisherName = trim($_POST['publisher_name'] ?? '');

        if ($publisherName === '') {
            throw new Exception('Tên NXB không được để trống');
        }

        $publisherId = generatePublisherID($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO publisher (PublisherID, PublisherName)
            VALUES (:id, :name)
        ");
        $stmt->execute([
            ':id' => $publisherId,
            ':name' => $publisherName
        ]);

        echo json_encode([
            'success' => true,
            'id' => $publisherId,
            'name' => $publisherName
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
} else if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'ajax_add_author'
) {
    if (ob_get_length())
        ob_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $authorName = trim($_POST['author_name'] ?? '');
        $summary = trim($_POST['summary'] ?? '');

        if ($authorName === '') {
            throw new Exception('Tên tác giả không được để trống');
        }

        $authorId = generateAuthorID($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO Book_Author (AuthorID, AuthorName, Summary)
            VALUES (:id, :name, :summary)
        ");
        $stmt->execute([
            ':id' => $authorId,
            ':name' => $authorName,
            ':summary' => $summary ?: null
        ]);

        echo json_encode([
            'success' => true,
            'id' => $authorId,
            'name' => $authorName
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ====== Xử lý submit form ======
else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        try {
            $productId = $_POST['product_id'] ?? '';
            if ($productId === '') {
                throw new Exception('Thiếu ProductID');
            }

            $pdo->beginTransaction();

            // Kiểm tra product đã từng bán chưa
            $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM order_items oi
            JOIN SKU s ON oi.SKU_ID = s.SKUID
            WHERE s.ProductID = :pid
        ");
            $stmt->execute([':pid' => $productId]);
            $soldCount = (int) $stmt->fetchColumn();

            if ($soldCount === 0) {
                // ===== CHƯA BÁN → XÓA CỨNG =====

                // Xóa sale
                $pdo->prepare("
                DELETE ps
                FROM PRODUCT_SALE ps
                JOIN SKU s ON ps.SKUID = s.SKUID
                WHERE s.ProductID = :pid
            ")->execute([':pid' => $productId]);

                // Xóa SKU
                $pdo->prepare("
                DELETE FROM SKU WHERE ProductID = :pid
            ")->execute([':pid' => $productId]);

                // Xóa Product
                $pdo->prepare("
                DELETE FROM Product WHERE ProductID = :pid
            ")->execute([':pid' => $productId]);

                $success_message = 'Đã xóa sản phẩm (chưa từng bán)';
            } else {
                // ===== ĐÃ BÁN → SOFT DELETE =====
                $pdo->prepare("
                UPDATE SKU SET Status = 0 WHERE ProductID = :pid
            ")->execute([':pid' => $productId]);

                $pdo->prepare("
                UPDATE Product SET Status = 0 WHERE ProductID = :pid
            ")->execute([':pid' => $productId]);

                $success_message = 'Sản phẩm đã bán → chuyển sang Ngừng bán';
            }

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }
    } else if ($action === 'add') {

        try {
            $pdo->beginTransaction();
            $productId = generateProductID($pdo);
            $skuId = generateSKUID($pdo);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $salePrice = trim($_POST['sale_price'] ?? '');
            $publisherId = $_POST['publisher_id'] ?? '';
            $categoryId = $_POST['category_id'] ?? '';
            $authorId = $_POST['author_id'] ?? null;

            // xử lý image
            $imageData = null;
            if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                $imageData = file_get_contents($_FILES['image']['tmp_name']);
            }

            /* Product */
            $stmt = $pdo->prepare("
                INSERT INTO Product
                (ProductID, ProductName, Description, Price, Image, PublisherID, AuthorID, CreatedDate, Status)
                VALUES (:id, :name, :desc, :price, :image, :publisher, :author, NOW(), 1)
            ");
            $stmt->execute([
                ':id' => $productId,
                ':name' => $name,
                ':desc' => $description,
                ':price' => $price,
                ':image' => $imageData,
                ':publisher' => $publisherId ?: null,
                ':author' => $authorId ?: null
            ]);


            /* Category */
            if ($categoryId) {
                $pdo->prepare("
                                INSERT INTO Product_Categories (ProductID, CategoryID)
                                VALUES (:pid, :cid)
                            ")->execute([
                            ':pid' => $productId,
                            ':cid' => $categoryId
                        ]);
            }

            /* SKU */
            $format = trim($_POST['sku_format'] ?? '');
            if ($format === '') {
                throw new Exception('Thiếu đặc tính SKU');
            }

            $pdo->prepare("
                    INSERT INTO SKU
                    (SKUID, ProductID, Format, BuyPrice, SellPrice, Stock, Status)
                    VALUES (:skuid, :pid, :format, :buy, :sell, 50, 1)
                ")->execute([
                        ':skuid' => $skuId,
                        ':pid' => $productId,
                        ':format' => $format,
                        ':buy' => $price,
                        ':sell' => $price
                    ]);


            /* Sale */
            if ($salePrice) {
                $pdo->prepare("
                INSERT INTO PRODUCT_SALE
                (ProductSaleID, SKUID, DiscountedPrice, StartDate, EndDate)
                VALUES (:id, :skuid, :price, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
            ")->execute([
                            ':id' => 'PS' . substr(uniqid(), -4),
                            ':skuid' => $skuId,
                            ':price' => $salePrice
                        ]);
            }

            $pdo->commit();
            if (isset($_GET['success'])) {
                $success_message = 'Đã thêm sản phẩm';
            }
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }

    } else if ($action === 'update') {
        try {
            $pdo->beginTransaction();

            $productId = $_POST['product_id'];
            $name = $_POST['name'];
            $price = $_POST['price'];
            $salePrice = $_POST['sale_price'] ?? null;
            $publisherId = $_POST['publisher_id'] ?? null;
            $categoryId = $_POST['category_id'] ?? null;

            // Update Product
            $pdo->prepare("
            UPDATE Product
            SET ProductName = :name,
                Price = :price,
                PublisherID = :publisher
            WHERE ProductID = :id
        ")->execute([
                        ':name' => $name,
                        ':price' => $price,
                        ':publisher' => $publisherId ?: null,
                        ':id' => $productId
                    ]);

            // Update Category
            $pdo->prepare("DELETE FROM Product_Categories WHERE ProductID = :id")
                ->execute([':id' => $productId]);

            if ($categoryId) {
                $pdo->prepare("
                INSERT INTO Product_Categories (ProductID, CategoryID)
                VALUES (:pid, :cid)
            ")->execute([
                            ':pid' => $productId,
                            ':cid' => $categoryId
                        ]);
            }

            // Update SKU price
            $pdo->prepare("
            UPDATE SKU SET SellPrice = :price
            WHERE ProductID = :pid
        ")->execute([
                        ':price' => $price,
                        ':pid' => $productId
                    ]);

            // Update Sale
            $pdo->prepare("
            DELETE ps FROM PRODUCT_SALE ps
            JOIN SKU s ON ps.SKUID = s.SKUID
            WHERE s.ProductID = :pid
        ")->execute([':pid' => $productId]);

            if ($salePrice) {
                $pdo->prepare("
                INSERT INTO PRODUCT_SALE
                (ProductSaleID, SKUID, DiscountedPrice, StartDate, EndDate)
                SELECT CONCAT('PS', SUBSTRING(UUID(),1,4)), SKUID, :price, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)
                FROM SKU WHERE ProductID = :pid
            ")->execute([
                            ':price' => $salePrice,
                            ':pid' => $productId
                        ]);
            }

            $pdo->commit();
            $success_message = 'Đã cập nhật sản phẩm';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }
    } else if ($action === 'add_sku') {
        try {
            $skuId = generateSKUID($pdo);
            $buyPrice = $_POST['buy_price'] ?? null;
            $sellPrice = $_POST['sell_price'] ?? null;
            $stock = $_POST['stock'] ?? 0;
            $format = trim($_POST['format'] ?? '');
            if ($format === '' || $buyPrice === null || $sellPrice === null) {
                throw new Exception('Thiếu thông tin SKU');
            }
            $pdo->prepare("
             INSERT INTO SKU
            (SKUID, ProductID, Format, BuyPrice, SellPrice, Stock, Status)
            VALUES (:id, :pid, :format, :buy, :sell, :stock, 1)
        ")->execute([
                        ':id' => $skuId,
                        ':pid' => $_POST['product_id'],
                        ':format' => $format,
                        ':buy' => $buyPrice,
                        ':sell' => $sellPrice,
                        ':stock' => $stock
                    ]);

            $success_message = 'Đã thêm SKU mới';

            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = $e->getMessage();
        }
    }
}


$pubStmt = $pdo->query("
    SELECT PublisherID, PublisherName
    FROM publisher
    ORDER BY PublisherName
");
$publishers = $pubStmt->fetchAll(PDO::FETCH_ASSOC);

$cateStmt = $pdo->query("
    SELECT CategoryID, CategoryName 
    FROM categories 
    ORDER BY CategoryName
");
$categories = $cateStmt->fetchAll(PDO::FETCH_ASSOC);

$authorStmt = $pdo->query("
    SELECT AuthorID, AuthorName
    FROM Book_Author
    ORDER BY AuthorName
");
$authors = $authorStmt->fetchAll(PDO::FETCH_ASSOC);


// ====== Lấy danh sách sản phẩm để hiển thị ======
$products = [];
try {
    $sql = "
       SELECT
    p.ProductID,
    p.ProductName,
    p.Price,
    p.CreatedDate,
    p.Status AS ProductStatus,
    pub.PublisherName,
    ba.AuthorName,
    GROUP_CONCAT(DISTINCT c.CategoryName) AS Categories,
    sku.SellPrice,
    ps.DiscountedPrice
FROM Product p
JOIN SKU sku ON p.ProductID = sku.ProductID
LEFT JOIN PRODUCT_SALE ps
       ON ps.SKUID = sku.SKUID
      AND NOW() BETWEEN ps.StartDate AND ps.EndDate
LEFT JOIN Publisher pub ON p.PublisherID = pub.PublisherID
LEFT JOIN Book_Author ba ON p.AuthorID = ba.AuthorID
LEFT JOIN Product_Categories pc ON p.ProductID = pc.ProductID
LEFT JOIN Categories c ON pc.CategoryID = c.CategoryID
GROUP BY p.ProductID;



    ";
    $stmt = $pdo->query($sql);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Exception $e) {
    $error_message = 'Không thể tải danh sách sản phẩm: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Quản lý sản phẩm | Moonlit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body admin-page">
    <h2 class="account-section-title">Danh sách sản phẩm</h2>
    <main class="account-main">
        <?php if ($success_message): ?>
            <div class="alert alert-success account-alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger account-alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        <!-- FORM THÊM NHÀ XUẤT BẢN-->
        <div class="account-card mb-4">
            <h2 class="account-card-title">Thêm nhà xuất bản</h2>

            <form id="publisherForm" class="row g-3">
                <input type="hidden" name="action" value="ajax_add_publisher">

                <div class="col-md-8">
                    <label class="account-label">Tên NXB *</label>
                    <input type="text" name="publisher_name" class="account-input w-100"
                        placeholder="VD: Kim Đồng, Nhã Nam, Trẻ..." required>
                </div>

                <div class="col-md-4 flex-end">
                    <button type="submit" class="account-btn-save">
                        Thêm NXB
                    </button>
                </div>
            </form>
        </div>

        <!--FORM THÊM DANH MỤC-->
        <div class="account-card mb-4">
            <h2 class="account-card-title">Thêm danh mục</h2>

            <form id="categoryForm" class="row g-3">
                <input type="hidden" name="action" value="ajax_add_category">

                <div class="col-md-6">
                    <label class="account-label">Tên danh mục *</label>
                    <input type="text" name="category_name" class="account-input w-100" required
                        placeholder="VD: Văn học, Kinh tế, Thiếu nhi">
                </div>

                <div class="col-md-6">
                    <label class="account-label">Mô tả</label>
                    <input type="text" name="category_desc" class="account-input w-100"
                        placeholder="Mô tả ngắn cho danh mục">
                </div>

                <div class="col-12 flex-end">
                    <button type="submit" class="account-btn-save">
                        Thêm danh mục
                    </button>
                </div>
            </form>
        </div>
        <!-- FORM THÊM TÁC GIẢ -->
        <div class="account-card mb-4">
            <h2 class="account-card-title">Thêm tác giả</h2>

            <form id="authorForm" class="row g-3">
                <input type="hidden" name="action" value="ajax_add_author">

                <div class="col-md-6">
                    <label class="account-label">Tên tác giả *</label>
                    <input type="text" name="author_name" class="account-input w-100" required>
                </div>

                <div class="col-md-6">
                    <label class="account-label">Giới thiệu ngắn</label>
                    <input type="text" name="summary" class="account-input w-100">
                </div>

                <div class="col-12 flex-end">
                    <button class="account-btn-save">Thêm tác giả</button>
                </div>
            </form>
        </div>

        <!-- FORM THÊM SẢN PHẨM -->
        <div class="account-card mb-4">
            <h2 class="account-card-title">Thêm sách mới</h2>

            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="action" value="add">

                <div class="col-md-6">
                    <label class="account-label" for="name">Tên sách *</label>
                    <input type="text" class="account-input w-100" id="name" name="name" required>
                </div>

                <div class="col-md-3">
                    <label class="account-label" for="price">Giá (VND) *</label>
                    <input type="number" class="account-input w-100" id="price" name="price" min="0" step="1000"
                        required>
                </div>

                <div class="col-md-3">
                    <label class="account-label" for="sale_price">Giá khuyến mãi (nếu có)</label>
                    <input type="number" id="sale_price" name="sale_price" class="account-input w-100" step="1000"
                        min="0" placeholder="VD: 80000">
                </div>

                <div class="col-md-3">
                    <label class="account-label" for="publisher_id">Nhà xuất bản</label>
                    <select id="publisher_id" name="publisher_id" class="account-input w-100">
                        <option value="">-- Chọn NXB --</option>
                        <?php foreach ($publishers as $pub): ?>
                            <option value="<?php echo htmlspecialchars($pub['PublisherID']); ?>">
                                <?php echo htmlspecialchars($pub['PublisherName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="account-label">Tác giả</label>
                    <select name="author_id" class="account-input w-100">
                        <option value="">-- Chọn tác giả --</option>
                        <?php foreach ($authors as $a): ?>
                            <option value="<?= $a['AuthorID'] ?>">
                                <?= htmlspecialchars($a['AuthorName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="account-label" for="category_id">Danh mục chính</label>
                    <select id="category_id" name="category_id" class="account-input w-100">
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $cate): ?>
                            <option value="<?php echo htmlspecialchars($cate['CategoryID']); ?>">
                                <?php echo htmlspecialchars($cate['CategoryName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="account-label">Đặc tính / Format *</label>
                    <input type="text" name="sku_format" class="account-input w-100"
                        placeholder="VD: Hardcover / Paperback / Bản đặc biệt" required>
                </div>
                <div class="col-md-4">
                    <label class="account-label" for="image">Ảnh bìa (JPEG/PNG)</label>
                    <input type="file" class="account-input w-100" id="image" name="image" accept="image/*">
                </div>

                <div class="col-12">
                    <label class="account-label" for="description">Mô tả</label>
                    <textarea id="description" name="description" rows="4" class="account-input w-100"></textarea>
                </div>

                <div class="col-12 flex-end">
                    <button type="submit" class="account-btn-save" value="add">
                        Thêm sản phẩm
                    </button>
                </div>
            </form>
        </div>

        <!-- DANH SÁCH SẢN PHẨM -->
        <div class="account-card">
            <h2 class="account-card-title">Danh sách sách hiện có</h2>

            <?php if (empty($products)): ?>
                <div class="account-empty-state">
                    <p class="account-empty-text">
                        Chưa có sản phẩm nào. Thêm sách mới ở form phía trên nha!
                    </p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên sách</th>
                                <th>Tác giả</th>
                                <th>Danh mục</th>
                                <th>NXB</th>
                                <th>Giá</th>
                                <th>Ngày tạo</th>
                                <th>Trạng thái</th>
                                <th class="text-end">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['ProductID']); ?></td>
                                    <td><?php echo htmlspecialchars($p['ProductName']); ?></td>
                                    <td><?= htmlspecialchars($p['AuthorName'] ?? '') ?></td>
                                    <td><?php echo htmlspecialchars($p['Categories'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($p['PublisherName'] ?? ''); ?></td>
                                    <td>
                                        <?php if (!empty($p['DiscountedPrice'])): ?>
                                            <span class="cart-price-current">
                                                <?php echo number_format($p['DiscountedPrice'], 0, ',', '.'); ?> đ
                                            </span>
                                            <span class="cart-price-old">
                                                <?php echo number_format($p['Price'], 0, ',', '.'); ?> đ
                                            </span>
                                        <?php else: ?>
                                            <?php echo number_format($p['Price'], 0, ',', '.'); ?> đ
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($p['CreatedDate']); ?></td>
                                    <td>
                                        <?php if ($p['ProductStatus'] == 1): ?>
                                            <span class="badge bg-success">Đang bán</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Ngừng bán</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id"
                                                value="<?php echo htmlspecialchars($p['ProductID']); ?>">
                                            <button type="button" class="btn btn-sm btn-outline-primary admin-btn-small"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-id="<?= $p['ProductID'] ?>"
                                                data-name="<?= htmlspecialchars($p['ProductName']) ?>"
                                                data-price="<?= $p['Price'] ?>"
                                                data-publisher="<?= htmlspecialchars($p['PublisherName'] ?? '') ?>">
                                                Sửa
                                            </button>
                                            <button type="submit" class="btn btn-sm btn-outline-danger admin-btn-small"
                                                onclick="return confirm('Xóa sản phẩm này?');">
                                                Xóa
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal"
                                                data-bs-target="#skuModal" data-product="<?= $p['ProductID'] ?>">
                                                Thêm SKU
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                data-bs-toggle="modal" data-bs-target="#skuListModal"
                                                data-product="<?= $p['ProductID'] ?>">
                                                Xem SKU
                                            </button>

                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" id="edit_product_id">

                    <div class="modal-header">
                        <h5 class="modal-title">Sửa sản phẩm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body row g-3">

                        <div class="col-md-6">
                            <label class="account-label">Tên sách</label>
                            <input type="text" name="name" id="edit_name" class="account-input w-100" required>
                        </div>

                        <div class="col-md-3">
                            <label class="account-label">Giá</label>
                            <input type="number" name="price" id="edit_price" class="account-input w-100" required>
                        </div>

                        <div class="col-md-3">
                            <label class="account-label">Giá KM</label>
                            <input type="number" name="sale_price" id="edit_sale_price" class="account-input w-100">
                        </div>

                        <div class="col-md-6">
                            <label class="account-label">Nhà xuất bản</label>
                            <select name="publisher_id" id="edit_publisher" class="account-input w-100">
                                <option value="">-- Chọn NXB --</option>
                                <?php foreach ($publishers as $pub): ?>
                                    <option value="<?= $pub['PublisherID'] ?>">
                                        <?= htmlspecialchars($pub['PublisherName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="account-label">Danh mục</label>
                            <select name="category_id" id="edit_category" class="account-input w-100">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $cate): ?>
                                    <option value="<?= $cate['CategoryID'] ?>">
                                        <?= htmlspecialchars($cate['CategoryName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                    </div>
                </form>

            </div>
        </div>
        <div class="modal fade" id="skuModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <input type="hidden" name="action" value="add_sku">
                    <input type="hidden" name="product_id" id="sku_product_id">

                    <div class="modal-header">
                        <h5 class="modal-title">Thêm đặc tính (SKU)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body row g-3">
                        <div class="col-12">
                            <label class="account-label">Đặc tính / Format *</label>
                            <input type="text" name="format" class="account-input w-100" required>
                        </div>

                        <div class="col-md-6">
                            <label class="account-label">Giá mua *</label>
                            <input type="number" name="buy_price" class="account-input w-100" min="0" step="1000"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="account-label">Giá bán *</label>
                            <input type="number" name="sell_price" class="account-input w-100" min="0" step="1000"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label class="account-label">Tồn kho</label>
                            <input type="number" name="stock" class="account-input w-100" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary">Thêm SKU</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal fade" id="skuListModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Danh sách SKU</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div id="skuListContent">
                            <p class="text-muted">Đang tải SKU...</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
            document.getElementById('categoryForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('admin-products.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.message);
                            return;
                        }

                        // thêm option vào select
                        const select = document.getElementById('category_id');
                        const opt = document.createElement('option');
                        opt.value = data.id;
                        opt.textContent = data.name;
                        opt.selected = true;
                        select.appendChild(opt);

                        this.reset();
                    })
                    .catch(err => {
                        alert('Lỗi khi thêm danh mục');
                        console.error(err);
                    });
            });
            document.getElementById('publisherForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('admin-products.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.message);
                            return;
                        }

                        const select = document.getElementById('publisher_id');
                        const opt = document.createElement('option');
                        opt.value = data.id;
                        opt.textContent = data.name;
                        opt.selected = true;
                        select.appendChild(opt);

                        this.reset();
                    })
                    .catch(err => {
                        alert('Lỗi khi thêm nhà xuất bản');
                        console.error(err);
                    });
            });
            const editModal = document.getElementById('editModal');
            editModal.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;

                document.getElementById('edit_product_id').value = btn.dataset.id;
                document.getElementById('edit_name').value = btn.dataset.name;
                document.getElementById('edit_price').value = btn.dataset.price;
            });
            document.getElementById('skuModal')
                .addEventListener('show.bs.modal', e => {
                    document.getElementById('sku_product_id').value =
                        e.relatedTarget.dataset.product;
                });
            const skuListModal = document.getElementById('skuListModal');

            skuListModal.addEventListener('show.bs.modal', function (e) {
                const productId = e.relatedTarget.dataset.product;
                const box = document.getElementById('skuListContent');

                box.innerHTML = '<p class="text-muted">Đang tải...</p>';

                const fd = new FormData();
                fd.append('action', 'ajax_load_sku');
                fd.append('product_id', productId);

                fetch('admin-products.php', {
                    method: 'POST',
                    body: fd
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!res.success || res.data.length === 0) {
                            box.innerHTML = '<p class="text-muted">Sản phẩm chưa có SKU</p>';
                            return;
                        }

                        let html = `
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>SKUID</th>
              <th>Đặc tính</th>
              <th>Giá mua</th>
              <th>Giá bán</th>
              <th>Tồn kho</th>
              <th>Trạng thái</th>
              <th class="text-center">Xóa</th>
            </tr>
          </thead>
          <tbody>
        `;

                        res.data.forEach(sku => {
                            html += `
            <tr>
              <td>${sku.SKUID}</td>
              <td>${sku.Format}</td>
              <td>${Number(sku.BuyPrice).toLocaleString()} đ</td>
              <td>${Number(sku.SellPrice).toLocaleString()} đ</td>
              <td>${sku.Stock}</td>
              <td>${sku.Status == 1 ? 'Đang bán' : 'Ẩn'}</td>
              <td class="text-center">
              <button class="btn btn-sm btn-outline-danger"
                onclick="deleteSKU('${sku.SKUID}', '${productId}')">
                Xóa
              </button>
                </td>
            </tr>
            `;
                        });

                        html += '</tbody></table>';
                        box.innerHTML = html;
                    })
                    .catch(() => {
                        box.innerHTML = '<p class="text-danger">Lỗi tải SKU</p>';
                    });
            });
            function deleteSKU(skuid, productId) {
                if (!confirm('Xóa SKU này?')) return;

                const fd = new FormData();
                fd.append('action', 'ajax_delete_sku');
                fd.append('skuid', skuid);

                fetch('admin-products.php', {
                    method: 'POST',
                    body: fd
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!res.success) {
                            alert(res.message || 'Xóa SKU thất bại');
                            return;
                        }

                        // reload lại danh sách SKU
                        const box = document.getElementById('skuListContent');
                        box.innerHTML = '<p class="text-muted">Đang tải...</p>';

                        const reloadFd = new FormData();
                        reloadFd.append('action', 'ajax_load_sku');
                        reloadFd.append('product_id', productId);

                        return fetch('admin-products.php', {
                            method: 'POST',
                            body: reloadFd
                        });
                    })
                    .then(res => res ? res.json() : null)
                    .then(res => {
                        if (!res) return;

                        if (!res.success || res.data.length === 0) {
                            document.getElementById('skuListContent').innerHTML =
                                '<p class="text-muted">Sản phẩm chưa có SKU</p>';
                            return;
                        }

                        let html = `
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>SKUID</th>
                  <th>Đặc tính</th>
                  <th>Giá</th>
                  <th>Tồn kho</th>
                  <th>Trạng thái</th>
                  <th class="text-center">Xóa</th>
                </tr>
              </thead>
              <tbody>
            `;

                        res.data.forEach(sku => {
                            html += `
                <tr>
                  <td>${sku.SKUID}</td>
                  <td>${sku.Format}</td>
                  <td>${Number(sku.SellPrice).toLocaleString()} đ</td>
                  <td>${sku.Stock}</td>
                  <td>${sku.Status == 1 ? 'Đang bán' : 'Ẩn'}</td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-outline-danger"
                        onclick="deleteSKU('${sku.SKUID}', '${productId}')">
                        Xóa
                    </button>
                  </td>
                </tr>
                `;
                        });

                        html += '</tbody></table>';
                        document.getElementById('skuListContent').innerHTML = html;
                    })
                    .catch(() => {
                        alert('Lỗi khi xóa SKU');
                    });
            }
            document.getElementById('authorForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch('admin-products.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.message);
                            return;
                        }

                        // thêm option vào select tác giả
                        const select = document.querySelector('select[name="author_id"]');
                        const opt = document.createElement('option');
                        opt.value = data.id;
                        opt.textContent = data.name;
                        opt.selected = true;
                        select.appendChild(opt);

                        this.reset();
                    })
                    .catch(err => {
                        alert('Lỗi khi thêm tác giả');
                        console.error(err);
                    });
            });
        </script>

    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">

</body >

</html >

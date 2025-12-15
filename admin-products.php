<?php
/**
 * MOONLIT STORE - ADMIN PRODUCT MANAGEMENT
 * - Thêm sách mới
 * - Upload hình (LONGBLOB)
 * - Gán danh mục
 * - Xóa sách
 */


require_once 'db_connect.php';

// ====== Check quyền admin (tạm comment nếu đang test) ======
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    // header("Location: auth_login.php");
    // exit;
}

$currentUsername = $_SESSION['username'] ?? 'Admin';

$success_message = '';
$error_message   = '';

// ====== Hàm sinh ProductID dạng P00001 ======
function generateProductID(PDO $pdo): string {
    $stmt   = $pdo->query("SELECT MAX(CAST(SUBSTRING(ProductID, 2) AS UNSIGNED)) AS max_id FROM Product");
    $row    = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($row['max_id'] ?? 0) + 1;
    return 'P' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

// ====== Lấy danh sách NXB & Danh mục cho combobox ======
$publishers = [];
$categories = [];

try {
    $pubStmt    = $pdo->query("SELECT PublisherID, PublisherName FROM Publisher ORDER BY PublisherName");
    $publishers = $pubStmt->fetchAll(PDO::FETCH_ASSOC);

    $cateStmt   = $pdo->query("SELECT CategoryID, CategoryName FROM Categories ORDER BY CategoryName");
    $categories = $cateStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Không thể tải danh sách NXB / Danh mục: ' . $e->getMessage();
}

// ====== Xử lý submit form ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        // Xóa sản phẩm
        $productId = $_POST['product_id'] ?? '';

        if ($productId !== '') {
            try {
                // Xóa ở bảng Product_Categories trước
                $stmt = $pdo->prepare("DELETE FROM Product_Categories WHERE ProductID = :id");
                $stmt->execute([':id' => $productId]);

                // Xóa ở Product
                $stmt = $pdo->prepare("DELETE FROM Product WHERE ProductID = :id");
                $stmt->execute([':id' => $productId]);

                $success_message = "Đã xóa sản phẩm $productId.";
            } catch (Exception $e) {
                $error_message = 'Không thể xóa sản phẩm: ' . $e->getMessage();
            }
        }
    } else {
        // Thêm mới sản phẩm
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price       = trim($_POST['price'] ?? '');
        $salePrice   = trim($_POST['sale_price'] ?? '');
        $publisherId = $_POST['publisher_id'] ?? '';
        $categoryId  = $_POST['category_id'] ?? '';

        if ($name === '' || $price === '') {
            $error_message = 'Tên sách và giá là bắt buộc.';
        } elseif (!is_numeric($price) || $price <= 0) {
            $error_message = 'Giá phải là số dương.';
        } elseif ($salePrice !== '' && (!is_numeric($salePrice) || $salePrice <= 0)) {
            $error_message = 'Giá khuyến mãi phải là số dương.';
        } elseif ($salePrice !== '' && $salePrice >= $price) {
            $error_message = 'Giá khuyến mãi phải nhỏ hơn giá gốc.';
        } else {
            $productId = generateProductID($pdo);

            // Xử lý image (tùy chọn)
            $imageData = null;
            if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                $imageData = file_get_contents($_FILES['image']['tmp_name']);
            }

            // Chuẩn hóa salePrice
            $discountPrice = ($salePrice !== '') ? (float)$salePrice : null;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO Product (
                        ProductID,
                        ProductName,
                        Description,
                        PublisherID,
                        Image,
                        Price,
                        DiscountPrice,
                        Status,
                        CreatedDate
                    )
                    VALUES (
                        :id,
                        :name,
                        :description,
                        :publisher,
                        :image,
                        :price,
                        :discount,
                        1,
                        NOW()
                    )
                ");

                $stmt->bindValue(':id', $productId);
                $stmt->bindValue(':name', $name);
                $stmt->bindValue(':description', $description);

                if ($publisherId !== '') {
                    $stmt->bindValue(':publisher', $publisherId);
                } else {
                    $stmt->bindValue(':publisher', null, PDO::PARAM_NULL);
                }

                $stmt->bindValue(':price', $price);
                if ($discountPrice !== null) {
                    $stmt->bindValue(':discount', $discountPrice);
                } else {
                    $stmt->bindValue(':discount', null, PDO::PARAM_NULL);
                }

                // Image là LONGBLOB
                if ($imageData !== null) {
                    $stmt->bindParam(':image', $imageData, PDO::PARAM_LOB);
                } else {
                    $stmt->bindValue(':image', null, PDO::PARAM_NULL);
                }

                $stmt->execute();

                // Gán danh mục nếu có chọn
                if ($categoryId !== '') {
                    $cateStmt = $pdo->prepare("
                        INSERT INTO Product_Categories (ProductID, CategoryID)
                        VALUES (:pid, :cid)
                    ");
                    $cateStmt->execute([
                        ':pid' => $productId,
                        ':cid' => $categoryId,
                    ]);
                }

                $success_message = "Đã thêm sản phẩm mới: $name (ID: $productId)";
            } catch (Exception $e) {
                $error_message = 'Không thể thêm sản phẩm: ' . $e->getMessage();
            }
        }
    }
}

// ====== Lấy danh sách sản phẩm để hiển thị ======
$products = [];
try {
    $sql = "
        SELECT
            p.ProductID,
            p.ProductName,
            p.Price,
            p.DiscountPrice,
            p.CreatedDate,
            p.Status,
            pub.PublisherName,
            GROUP_CONCAT(DISTINCT c.CategoryName SEPARATOR ', ') AS Categories
        FROM Product p
        LEFT JOIN Publisher pub ON p.PublisherID = pub.PublisherID
        LEFT JOIN Product_Categories pc ON p.ProductID = pc.ProductID
        LEFT JOIN Categories c ON pc.CategoryID = c.CategoryID
        GROUP BY
            p.ProductID,
            p.ProductName,
            p.Price,
            p.DiscountPrice,
            p.CreatedDate,
            p.Status,
            pub.PublisherName
        ORDER BY p.CreatedDate DESC
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

    <!-- Bootstrap trước, Moonlit CSS sau để không mất màu beige -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="moonlit-style.css">
</head>
<body class="account-body admin-page">



<main class="account-main">
    <div class="account-card mb-3">

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

        <!-- FORM THÊM SẢN PHẨM -->
        <div class="account-card mb-4">
            <h2 class="account-card-title">Thêm sách mới</h2>

            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="action" value="create">

                <div class="col-md-6">
                    <label class="account-label" for="name">Tên sách *</label>
                    <input
                        type="text"
                        class="account-input w-100"
                        id="name"
                        name="name"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="account-label" for="price">Giá (VND) *</label>
                    <input
                        type="number"
                        class="account-input w-100"
                        id="price"
                        name="price"
                        min="0"
                        step="1000"
                        required
                    >
                </div>

                <div class="col-md-3">
                    <label class="account-label" for="sale_price">Giá khuyến mãi (nếu có)</label>
                    <input
                        type="number"
                        id="sale_price"
                        name="sale_price"
                        class="account-input w-100"
                        step="1000"
                        min="0"
                        placeholder="VD: 80000"
                    >
                </div>

                <div class="col-md-3">
                    <label class="account-label" for="publisher_id">Nhà xuất bản</label>
                    <select
                        id="publisher_id"
                        name="publisher_id"
                        class="account-input w-100"
                    >
                        <option value="">-- Chọn NXB --</option>
                        <?php foreach ($publishers as $pub): ?>
                            <option value="<?php echo htmlspecialchars($pub['PublisherID']); ?>">
                                <?php echo htmlspecialchars($pub['PublisherName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="account-label" for="category_id">Danh mục chính</label>
                    <select
                        id="category_id"
                        name="category_id"
                        class="account-input w-100"
                    >
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $cate): ?>
                            <option value="<?php echo htmlspecialchars($cate['CategoryID']); ?>">
                                <?php echo htmlspecialchars($cate['CategoryName']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="account-label" for="image">Ảnh bìa (JPEG/PNG)</label>
                    <input
                        type="file"
                        class="account-input w-100"
                        id="image"
                        name="image"
                        accept="image/*"
                    >
                </div>

                <div class="col-12">
                    <label class="account-label" for="description">Mô tả</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="account-input w-100"
                    ></textarea>
                </div>

                <div class="col-12 flex-end">
                    <button type="submit" class="account-btn-save">
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
                            <th>Danh mục</th>
                            <th>NXB</th>
                            <th>Giá</th>
                            <th>Ngày tạo</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['ProductID']); ?></td>
                                <td><?php echo htmlspecialchars($p['ProductName']); ?></td>
                                <td><?php echo htmlspecialchars($p['Categories'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($p['PublisherName'] ?? ''); ?></td>
                                <td>
                                    <?php if (!empty($p['DiscountPrice'])): ?>
                                        <span class="cart-price-current">
                                            <?php echo number_format($p['DiscountPrice'], 0, ',', '.'); ?> đ
                                        </span>
                                        <span class="cart-price-old">
                                            <?php echo number_format($p['Price'], 0, ',', '.'); ?> đ
                                        </span>
                                    <?php else: ?>
                                        <?php echo number_format($p['Price'], 0, ',', '.'); ?> đ
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($p['CreatedDate']); ?></td>
                                <td class="text-end">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="product_id"
                                               value="<?php echo htmlspecialchars($p['ProductID']); ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger admin-btn-small"
                                            onclick="return confirm('Xóa sản phẩm này?');"
                                        >
                                            Xóa
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

    </div>
</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>
</body>
</html>

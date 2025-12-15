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
// ====== Hàm sinh Publisher dạng PUBL00001 ======
function generatePublisherID(PDO $pdo): string
{
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(PublisherID, 2) AS UNSIGNED)) AS max_id
        FROM publisher
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = ($row['max_id'] ?? 0) + 1;

    return 'N' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
}

//Xử lý load trang sau mỗi lần thêm
// ===== AJAX thêm category  =====
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'ajax_add_category'
) {
    header('Content-Type: application/json; charset=utf-8');
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
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'ajax_add_publisher'
) {
    header('Content-Type: application/json; charset=utf-8');
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
}

// ====== Xử lý submit form ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        try {
            $productId = $_POST['product_id'] ?? '';
            if ($productId === '') {
                throw new Exception('Thiếu ProductID');
            }

            $pdo->beginTransaction();

            // Xoá sale
            $pdo->prepare("
            DELETE ps
            FROM PRODUCT_SALE ps
            JOIN SKU s ON ps.SKUID = s.SKUID
            WHERE s.ProductID = :pid
        ")->execute([':pid' => $productId]);

            // Xoá SKU
            $pdo->prepare("DELETE FROM SKU WHERE ProductID = :pid")
                ->execute([':pid' => $productId]);

            // Xoá Product (cascade category)
            $pdo->prepare("DELETE FROM Product WHERE ProductID = :pid")
                ->execute([':pid' => $productId]);

            $pdo->commit();
            $success_message = 'Đã xoá sản phẩm';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = $e->getMessage();
        }
    } else if ($action === 'add') {
        try {
            $pdo->beginTransaction();
            $productId = generateProductID($pdo);
            $skuId = 'S' . substr(uniqid(), -5);


            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = trim($_POST['price'] ?? '');
            $salePrice = trim($_POST['sale_price'] ?? '');
            $publisherId = $_POST['publisher_id'] ?? '';
            $categoryId = $_POST['category_id'] ?? '';

            // xử lý image
            $imageData = null;
            if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
                $imageData = file_get_contents($_FILES['image']['tmp_name']);
            }

            /* Product */
            $stmt = $pdo->prepare("
                        INSERT INTO Product
                        (ProductID, ProductName, Description, Price, Image, PublisherID, CreatedDate, Status)
                        VALUES (:id, :name, :desc, :price, :image, :publisher, NOW(), 1)
                    ");
            $stmt->execute([
                ':id' => $productId,
                ':name' => $name,
                ':desc' => $description,
                ':price' => $price,
                ':image' => $imageData,
                ':publisher' => $publisherId ?: null
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
            $pdo->prepare("
                                INSERT INTO SKU
                                (SKUID, ProductID, Format, BuyPrice, SellPrice, Stock, Status)
                                VALUES (:skuid, :pid, 'Paperback', :buy, :sell, 0, 1)
                            ")->execute([
                        ':skuid' => $skuId,
                        ':pid' => $productId,
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
            $success_message = 'Đã thêm sản phẩm';

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

// ====== Lấy danh sách sản phẩm để hiển thị ======
$products = [];
try {
    $sql = "
       SELECT
    p.ProductID,
    p.ProductName,
    p.Price,
    p.CreatedDate,
    pub.PublisherName,
    GROUP_CONCAT(DISTINCT c.CategoryName) AS Categories,
    sku.SellPrice,
    ps.DiscountedPrice
FROM Product p
JOIN SKU sku ON p.ProductID = sku.ProductID
LEFT JOIN PRODUCT_SALE ps
       ON ps.SKUID = sku.SKUID
      AND NOW() BETWEEN ps.StartDate AND ps.EndDate
LEFT JOIN Publisher pub ON p.PublisherID = pub.PublisherID
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

        <script>
            document.getElementById('categoryForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);

                fetch(window.location.href, {
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

                fetch(window.location.href, {
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
        </script>

    </main>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
    </script>
</body>

</html>

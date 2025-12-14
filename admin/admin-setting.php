<?php
require_once 'db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ===== UPLOAD BANNER =====
    if ($action === 'upload_banner') {

        $title = trim($_POST['banner_title'] ?? '');

        if (
            !isset($_FILES['banner_image']) ||
            $_FILES['banner_image']['error'] !== UPLOAD_ERR_OK
        ) {
            $error_message = 'Vui lòng chọn ảnh banner.';
        } else {

            $imageData = file_get_contents($_FILES['banner_image']['tmp_name']);

            $stmt = $pdo->prepare("
                INSERT INTO Banner (Title, ImageBinary)
                VALUES (:title, :img)
            ");

            $stmt->execute([
                ':title' => $title !== '' ? $title : null,
                ':img'   => $imageData
            ]);

            $success_message = 'Upload banner thành công!';
        }
    }

    // ===== DELETE BANNER =====
    if ($action === 'delete_banner') {
        $bannerId = $_POST['banner_id'] ?? '';

        if ($bannerId !== '') {
            $stmt = $pdo->prepare("DELETE FROM Banner WHERE BannerID = ?");
            $stmt->execute([$bannerId]);

            $success_message = 'Đã xóa banner.';
        }
    }
}
?>
<?php
$stmt = $pdo->query("SELECT BannerID, Title FROM Banner ORDER BY BannerID DESC");
$bannerList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if ($success_message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="action" value="upload_banner">

    <div class="col-md-6">
        <label class="account-label">Tiêu đề banner</label>
        <input type="text"
               name="banner_title"
               class="account-input w-100"
               placeholder="Kệ sách Moonlit">
    </div>

    <div class="col-md-6">
        <label class="account-label">Ảnh banner *</label>
        <input type="file"
               name="banner_image"
               class="account-input w-100"
               accept="image/*"
               required>
    </div>

    <div class="col-12 d-flex justify-content-end">
        <button type="submit" class="account-btn-save">
            Upload banner
        </button>
    </div>
</form>
<hr>

<h3 class="mt-4">Banner hiện có</h3>

<?php if (empty($bannerList)): ?>
    <p class="text-muted">Chưa có banner nào.</p>
<?php else: ?>
    <div class="row">
        <?php foreach ($bannerList as $b): ?>
            <div class="col-md-4 mb-3">
                <div class="account-card">
                    <img
                        src="banner-image.php?id=<?php echo (int)$b['BannerID']; ?>"
                        style="width:100%;height:160px;object-fit:cover;border-radius:8px;"
                    >

                    <p class="mt-2 fw-bold">
                        <?php echo htmlspecialchars($b['Title'] ?? '(Không có tiêu đề)'); ?>
                    </p>

                    <form method="POST"
                          onsubmit="return confirm('Bạn có chắc muốn xóa banner này không?');">
                        <input type="hidden" name="action" value="delete_banner">
                        <input type="hidden" name="banner_id"
                               value="<?php echo (int)$b['BannerID']; ?>">

                        <button class="btn btn-sm btn-outline-danger w-100">
                            ❌ Xóa banner
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>


<?php
/**
 * MOONLIT STORE - ADMIN DASHBOARD
 */

session_start();
require_once 'db_connect.php';

// ==== CHECK QUYỀN ADMIN ====
// Sau này dùng thật thì bỏ comment để chỉ admin mới vào được
// if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
//     header('Location: shop.php');
//     exit;
// }

$currentUsername = $_SESSION['username'] ?? 'Admin';

// Tab hiện tại
$tab = $_POST['tab'] ?? ($_GET['tab'] ?? 'overview');

$error_message = '';
$success_message = '';

/**
 * Helper: escape
 */
function h($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}




?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Moonlit Store</title>

    <!-- CSS Moonlit -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="moonlit-style.css">


</head>

<body class="account-body admin-page">

    <!-- ===================== HEADER (giống vibe index/cart) ===================== -->
    <header class="account-header site-header">
        <div class="container header-inner">
            <div class="header-left">
                <a href="shop.php" class="logo-link">
                    <span class="account-logo">Moonlit</span>
                </a>
                <span class="account-username" style="color: var(--color-text-light); font-size: 13px;">
                    Admin • Bảng điều khiển thương mại điện tử
                </span>
            </div>

            <div class="header-right">
                <span class="account-username d-none d-sm-inline">
                    Xin chào, <strong><?php echo h($currentUsername); ?></strong>
                </span>
                <a href="index.php" class="account-btn-secondary header-account-btn">
                    Xem cửa hàng
                </a>
                <a href="logout.php" class="account-btn-secondary header-account-btn">
                    Đăng xuất
                </a>
            </div>
        </div>
    </header>

    <!-- ===================== MAIN ===================== -->
    <main class="account-main">
        <div class="container">

            <h1 class="account-section-title mb-3">
                Bảng điều khiển Admin
            </h1>

            <?php if ($success_message): ?>
                <div class="alert alert-success account-alert">
                    <?php echo h($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger account-alert">
                    <?php echo h($error_message); ?>
                </div>
            <?php endif; ?>

        <!-- NAV TAB -->
<nav class="admin-tabs">
    <a
        href="?tab=overview"
        class="admin-tab-link <?php echo $tab === 'overview' ? 'admin-tab-link-active' : ''; ?>"
    >
        Tổng quan
    </a>
    <a
        href="?tab=orders"
        class="admin-tab-link <?php echo $tab === 'orders' ? 'admin-tab-link-active' : ''; ?>"
    >
        Đơn hàng
    </a>
    <a
        href="?tab=products"
        class="admin-tab-link <?php echo $tab === 'products' ? 'admin-tab-link-active' : ''; ?>"
    >
        Sản phẩm
    </a>
    <a
        href="?tab=posts"
        class="admin-tab-link <?php echo $tab === 'posts' ? 'admin-tab-link-active' : ''; ?>"
    >
        Bài đăng
    </a>
    <a
        href="?tab=customers"
        class="admin-tab-link <?php echo $tab === 'customers' ? 'admin-tab-link-active' : ''; ?>"
    >
        Khách hàng
    </a>
    <a
        href="?tab=marketing"
        class="admin-tab-link <?php echo $tab === 'marketing' ? 'admin-tab-link-active' : ''; ?>"
    >
        Voucher
    </a>
    
</nav>


        <!-- ================= OVERVIEW ================= -->
        <?php if ($tab === 'overview'): ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <div class="account-card">
                        <div class="account-card-title">Doanh thu</div>
                        <p class="fw-bold" style="font-size: 20px;">
                            <?php echo number_format($totalRevenue, 0, ',', '.'); ?> đ
                        </p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="account-card">
                        <div class="account-card-title">Số đơn hàng</div>
                        <p class="fw-bold" style="font-size: 20px;">
                            <?php echo (int)$totalOrders; ?>
                        </p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="account-card">
                        <div class="account-card-title">Giá trị TB/đơn</div>
                        <p class="fw-bold" style="font-size: 20px;">
                            <?php echo number_format($avgOrder, 0, ',', '.'); ?> đ
                        </p>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="account-card">
                        <div class="account-card-title">Khách hàng</div>
                        <p class="fw-bold" style="font-size: 20px;">
                            <?php echo (int)$totalCustomers; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Đơn theo trạng thái -->
            <div class="account-card mb-3">
                <h2 class="account-card-title">Đơn hàng theo trạng thái</h2>
                <div class="row">
                    <?php foreach ($orderStatuses as $code => $label): ?>
                        <div class="col-md-4 mb-2">
                            <div class="d-flex justify-content-between">
                                <span><?php echo h($label); ?></span>
                                <strong>
                                    <?php echo $orderStatusCounts[$code] ?? 0; ?>
                                </strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 10 sản phẩm mới -->
            <div class="account-card">
                <h2 class="account-card-title">Sản phẩm mới nhất</h2>
                <?php if (empty($latestProducts)): ?>
                    <p class="account-empty-text mb-0">
                        Chưa có sản phẩm nào.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên sách</th>
                                <th>Giá</th>
                                <th>Ngày tạo</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($latestProducts as $p): ?>
                                <tr>
                                    <td><?php echo h($p['ProductID']); ?></td>
                                    <td><?php echo h($p['ProductName']); ?></td>
                                    <td><?php echo number_format($p['Price'], 0, ',', '.'); ?> đ</td>
                                    <td><?php echo h($p['CreatedDate']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ================= ORDERS ================= -->
        <?php if ($tab === 'orders'): ?>
            <?php include 'admin-orders.php'; ?>
        <?php endif; ?>

        <!-- ================= PRODUCTS ================= -->
        <?php if ($tab === 'products'): ?>
            <div class="account-card mb-3">
                <h2 class="account-card-title">
                    Quản lý sản phẩm
                </h2>
                <p>Bấm nút bên dưới để mở trang chi tiết:</p>
                <a href="admin-products.php" class="account-btn-save">
                    Mở trang quản lý sản phẩm
                </a>
            </div>

            <div class="account-card">
                <h2 class="account-card-title">Sản phẩm mới nhất</h2>
                <?php if (empty($latestProducts)): ?>
                    <p class="account-empty-text mb-0">Chưa có sản phẩm nào.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên sách</th>
                                <th>Giá</th>
                                <th>Ngày tạo</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($latestProducts as $p): ?>
                                <tr>
                                    <td><?php echo h($p['ProductID']); ?></td>
                                    <td><?php echo h($p['ProductName']); ?></td>
                                    <td><?php echo number_format($p['Price'], 0, ',', '.'); ?> đ</td>
                                    <td><?php echo h($p['CreatedDate']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ================= POSTS ================= -->
        <?php if ($tab === 'posts'): ?>
            <div class="account-card mb-3">
                <h2 class="account-card-title">Tạo bài đăng mới</h2>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="create_post">

                    <div class="col-md-6">
                        <label class="account-label" for="post_title">Tiêu đề *</label>
                        <input
                            type="text"
                            id="post_title"
                            name="post_title"
                            class="account-input w-100"
                            required
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="account-label" for="post_thumbnail">Link ảnh (Thumbnail)</label>
                        <input
                            type="text"
                            id="post_thumbnail"
                            name="post_thumbnail"
                            class="account-input w-100"
                            placeholder="https://..."
                        >
                    </div>

                    <div class="col-md-12">
                        <label class="account-label" for="post_content">Nội dung bài đăng *</label>
                        <textarea
                            id="post_content"
                            name="post_content"
                            rows="6"
                            class="account-input w-100"
                            required
                        ></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="account-label" for="post_status">Trạng thái</label>
                        <select id="post_status" name="post_status" class="account-input w-100">
                            <option value="draft">Nháp</option>
                            <option value="published">Đã xuất bản</option>
                        </select>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="account-btn-save">
                            Lưu bài đăng
                        </button>
                    </div>
                </form>
            </div>

            <div class="account-card">
                <h2 class="account-card-title">Danh sách bài đăng</h2>

                <?php if (empty($posts)): ?>
                    <p class="account-empty-text mb-0">
                        Chưa có bài đăng nào.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Ảnh</th>
                                <th>Tiêu đề</th>
                                <th>Ngày đăng</th>
                                <th>Trạng thái</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td style="width: 80px;">
                                        <?php if (!empty($post['ThumbnailUrl'])): ?>
                                            <img
                                                src="<?php echo h($post['ThumbnailUrl']); ?>"
                                                alt="<?php echo h($post['Title']); ?>"
                                                style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;"
                                            >
                                        <?php else: ?>
                                            <span class="text-muted small">No image</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($post['Title']); ?></td>
                                    <td><?php echo h($post['CreatedAt']); ?></td>
                                    <td><?php echo h($post['Status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- ================= CUSTOMERS ================= -->
        <?php if ($tab === 'customers'): ?>
            <div class="account-card mb-3">
                <h2 class="account-card-title">Khách hàng</h2>
                <?php if (empty($customers)): ?>
                    <p class="account-empty-text mb-0">
                        Chưa có khách hàng nào.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ tên</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Điểm</th>
                                <th>Ngày tạo</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($customers as $c): ?>
                                <tr>
                                    <td><?php echo h($c['UserID']); ?></td>
                                    <td><?php echo h($c['FullName']); ?></td>
                                    <td><?php echo h($c['Email']); ?></td>
                                    <td><?php echo h($c['Phone']); ?></td>
                                    <td><?php echo h($c['Role']); ?></td>
                                    <td><?php echo (int)$c['Points']; ?></td>
                                    <td><?php echo h($c['CreatedDate']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="account-card">
                <h2 class="account-card-title">Quản lý chat</h2>
                <p>
                    Phần chat hiện chưa có bảng riêng trong database.  
                    Khi bà có bảng lưu hội thoại (ví dụ Conversation, Message), mình sẽ gắn vào phần này sau.
                </p>
            </div>
        <?php endif; ?>

        <!-- ================= MARKETING ================= -->
        <?php if ($tab === 'marketing'): ?>
            <?php include 'admin-voucher.php'; ?>
        <?php endif; ?>

    </div>
</main>

    <!-- ===================== FOOTER ===================== -->
    <footer class="site-footer">
        © <?php echo date('Y'); ?> Moonlit — All rights reserved.
    </footer>

</body>

</html>

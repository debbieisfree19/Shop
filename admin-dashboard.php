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
                <a href="?tab=overview"
                    class="admin-tab-link <?php echo $tab === 'overview' ? 'admin-tab-link-active' : ''; ?>">
                    Tổng quan
                </a>
                <a href="?tab=orders"
                    class="admin-tab-link <?php echo $tab === 'orders' ? 'admin-tab-link-active' : ''; ?>">
                    Đơn hàng
                </a>
                <a href="?tab=products"
                    class="admin-tab-link <?php echo $tab === 'products' ? 'admin-tab-link-active' : ''; ?>">
                    Sản phẩm
                </a>
                <a href="?tab=posts"
                    class="admin-tab-link <?php echo $tab === 'posts' ? 'admin-tab-link-active' : ''; ?>">
                    Bài đăng
                </a>
                <a href="?tab=customers"
                    class="admin-tab-link <?php echo $tab === 'customers' ? 'admin-tab-link-active' : ''; ?>">
                    Khách hàng
                </a>
                <a href="?tab=marketing"
                    class="admin-tab-link <?php echo $tab === 'marketing' ? 'admin-tab-link-active' : ''; ?>">
                    Kênh marketing
                </a>

                <a href="?tab=setting"
                    class="admin-tab-link <?php echo $tab === 'setting' ? 'admin-tab-link-active' : ''; ?>">
                    Cài đặt chung
                </a>


            </nav>
            <?php
            // ================= TAB ROUTER =================
            $allowedTabs = [
                'overview',
                'orders',
                'products',
                'posts',
                'customers',
                'marketing',
                'setting'
            ];

            if (!in_array($tab, $allowedTabs)) {
                $tab = 'overview';
            }

            $tabFile = __DIR__ . "/admin/admin-$tab.php";

            if (file_exists($tabFile)) {
                require $tabFile;
            } else {
                echo '<div class="alert alert-danger">Tab không tồn tại</div>';
            }
            ?>

        </div>
    </main>

    <!-- ===================== FOOTER ===================== -->
    <footer class="site-footer">
        © <?php echo date('Y'); ?> Moonlit — All rights reserved.
    </footer>

</body>

</html>

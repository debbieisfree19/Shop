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
$tab = $_GET['tab'] ?? 'overview';

$error_message   = '';
$success_message = '';

/**
 * Helper: escape
 */
function h($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Lấy danh sách trạng thái đơn để dùng chung
 */
$orderStatuses = [
    'PENDING'      => 'Chờ xác nhận',
    'CONFIRMED'    => 'Đã xác nhận / Chờ lấy hàng',
    'SHIPPING'     => 'Đang giao',
    'COMPLETED'    => 'Đã giao',
    'RETURNED'     => 'Trả hàng / Hoàn tiền',
    'CANCELLED'    => 'Đã hủy',
];

// ============================
// XỬ LÝ FORM (POST)
// ============================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Cập nhật trạng thái đơn hàng
    if ($action === 'update_order_status') {
        $orderId    = $_POST['order_id'] ?? '';
        $newStatus  = $_POST['status'] ?? '';

        if ($orderId === '' || $newStatus === '') {
            $error_message = 'Thiếu thông tin cập nhật đơn hàng.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE `Order`
                    SET Status = :status
                    WHERE OrderID = :id
                ");
                $stmt->execute([
                    ':status' => $newStatus,
                    ':id'     => $orderId,
                ]);
                $success_message = "Đã cập nhật trạng thái đơn $orderId.";
                $tab = 'orders';
            } catch (Exception $e) {
                $error_message = 'Lỗi cập nhật đơn hàng: ' . $e->getMessage();
            }
        }
    }

    // Thêm voucher
    if ($action === 'create_voucher') {
        $code          = trim($_POST['code'] ?? '');
        $description   = trim($_POST['description'] ?? '');
        $discountType  = $_POST['discount_type'] ?? 'PERCENT';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $minOrder      = (float)($_POST['min_order'] ?? 0);
        $maxDiscount   = (float)($_POST['max_discount'] ?? 0);
        $usageLimit    = (int)($_POST['usage_limit'] ?? 0);
        $startDate     = $_POST['start_date'] ?? '';
        $endDate       = $_POST['end_date'] ?? '';

        if ($code === '' || $discountValue <= 0) {
            $error_message = 'Mã giảm giá và giá trị giảm là bắt buộc.';
            $tab = 'marketing';
        } else {
            // Tạo VoucherID kiểu V00001
            try {
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(VoucherID, 2) AS UNSIGNED)) AS max_id FROM Voucher");
                $row  = $stmt->fetch(PDO::FETCH_ASSOC);
                $next = ($row['max_id'] ?? 0) + 1;
                $voucherId = 'V' . str_pad($next, 5, '0', STR_PAD_LEFT);
            } catch (Exception $e) {
                $voucherId = 'V00001';
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO Voucher (
                        VoucherID,
                        Code,
                        Description,
                        DiscountType,
                        DiscountValue,
                        MinOrder,
                        MaxDiscount,
                        StartDate,
                        EndDate,
                        UsageLimit,
                        UsedCount,
                        Status
                    ) VALUES (
                        :id,
                        :code,
                        :description,
                        :dtype,
                        :dvalue,
                        :minOrder,
                        :maxDiscount,
                        :startDate,
                        :endDate,
                        :usageLimit,
                        0,
                        1
                    )
                ");

                $stmt->execute([
                    ':id'          => $voucherId,
                    ':code'        => $code,
                    ':description' => $description,
                    ':dtype'       => $discountType,
                    ':dvalue'      => $discountValue,
                    ':minOrder'    => $minOrder,
                    ':maxDiscount' => $maxDiscount,
                    ':startDate'   => $startDate !== '' ? $startDate : null,
                    ':endDate'     => $endDate !== '' ? $endDate : null,
                    ':usageLimit'  => $usageLimit > 0 ? $usageLimit : null,
                ]);

                $success_message = "Đã tạo mã giảm giá $code (ID: $voucherId).";
                $tab = 'marketing';
            } catch (Exception $e) {
                $error_message = 'Không thể tạo mã giảm giá: ' . $e->getMessage();
                $tab = 'marketing';
            }
        }
    }

    // Tạo bài đăng mới (quản lý bài viết / review sách)
    if ($action === 'create_post') {
        $postTitle   = trim($_POST['post_title'] ?? '');
        $postThumb   = trim($_POST['post_thumbnail'] ?? '');
        $postContent = trim($_POST['post_content'] ?? '');
        $postStatus  = $_POST['post_status'] ?? 'draft';

        if ($postTitle === '' || $postContent === '') {
            $error_message = 'Tiêu đề và nội dung bài đăng là bắt buộc.';
            $tab = 'posts';
        } else {
            // Tạo PostID kiểu P00001
            try {
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(PostID, 2) AS UNSIGNED)) AS max_id FROM Book_Post");
                $row  = $stmt->fetch(PDO::FETCH_ASSOC);
                $next = ($row['max_id'] ?? 0) + 1;
                $postId = 'P' . str_pad($next, 5, '0', STR_PAD_LEFT);
            } catch (Exception $e) {
                $postId = 'P00001';
            }

            // Tạo excerpt từ nội dung
            $excerpt = mb_substr($postContent, 0, 150) . (mb_strlen($postContent) > 150 ? '...' : '');

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO Book_Post (
                        PostID,
                        Title,
                        Excerpt,
                        Content,
                        ThumbnailUrl,
                        CreatedAt,
                        AuthorName,
                        Status
                    ) VALUES (
                        :id,
                        :title,
                        :excerpt,
                        :content,
                        :thumb,
                        NOW(),
                        :author,
                        :status
                    )
                ");

                $stmt->execute([
                    ':id'      => $postId,
                    ':title'   => $postTitle,
                    ':excerpt' => $excerpt,
                    ':content' => $postContent,
                    ':thumb'   => $postThumb,
                    ':author'  => $currentUsername,
                    ':status'  => $postStatus,
                ]);

                $success_message = "Đã tạo bài đăng mới: $postTitle.";
                $tab = 'posts';
            } catch (Exception $e) {
                $error_message = 'Không thể tạo bài đăng: ' . $e->getMessage();
                $tab = 'posts';
            }
        }
    }
}

// ============================
// TỔNG QUAN & ANALYTICS
// ============================

// Tổng doanh thu, tổng đơn, AOV
$totalRevenue = 0;
$totalOrders  = 0;
$avgOrder     = 0;

try {
    $stmt = $pdo->query("
        SELECT
            COALESCE(SUM(TotalAmount), 0) AS revenue,
            COUNT(*) AS orders
        FROM `Order`
        WHERE Status NOT IN ('CANCELLED')
    ");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $totalRevenue = (float)$row['revenue'];
        $totalOrders  = (int)$row['orders'];
        $avgOrder     = $totalOrders > 0 ? ($totalRevenue / $totalOrders) : 0;
    }
} catch (Exception $e) {
    // ignore analytics error
}

// Đếm đơn theo trạng thái
$orderStatusCounts = [];
try {
    $stmt = $pdo->query("
        SELECT Status, COUNT(*) AS cnt
        FROM `Order`
        GROUP BY Status
    ");
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $orderStatusCounts[$r['Status']] = (int)$r['cnt'];
    }
} catch (Exception $e) {
    // ignore
}

// Đếm sản phẩm & khách hàng
$totalProducts  = 0;
$totalCustomers = 0;

try {
    $totalProducts  = (int)$pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM User_Account")->fetchColumn();
} catch (Exception $e) {
    // ignore
}

// Doanh thu theo ngày (7 ngày gần nhất)
$dailyStats = [];
try {
    $stmt = $pdo->query("
        SELECT
            DATE(CreatedDate) AS order_date,
            COUNT(*) AS orders,
            SUM(TotalAmount) AS revenue
        FROM `Order`
        WHERE Status NOT IN ('CANCELLED')
        GROUP BY DATE(CreatedDate)
        ORDER BY order_date DESC
        LIMIT 7
    ");
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // ignore
}

// ============================
// DATA CHO TỪNG TAB
// ============================

// Đơn hàng
$orders = [];
if ($tab === 'orders') {
    $filterStatus = $_GET['status'] ?? 'ALL';

    try {
        $params = [];
        $sql = "
            SELECT
                o.OrderID,
                o.UserID,
                o.TotalAmount,
                o.Status,
                o.PaymentMethod,
                o.ShippingAddress,
                o.CreatedDate,
                o.DateReceived,
                u.FullName,
                u.Email
            FROM `Order` o
            LEFT JOIN User_Account u ON o.UserID = u.UserID
            WHERE 1 = 1
        ";

        if ($filterStatus !== 'ALL' && $filterStatus !== '') {
            $sql .= " AND o.Status = :st";
            $params[':st'] = $filterStatus;
        }

        $sql .= " ORDER BY o.CreatedDate DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = 'Không thể tải danh sách đơn hàng: ' . $e->getMessage();
    }
}

// Sản phẩm (liệt kê nhanh)
$latestProducts = [];
if ($tab === 'products' || $tab === 'overview') {
    try {
        $stmt = $pdo->query("
            SELECT ProductID, ProductName, Price, CreatedDate
            FROM Product
            ORDER BY CreatedDate DESC
            LIMIT 10
        ");
        $latestProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // ignore
    }
}

// Khách hàng
$customers = [];
if ($tab === 'customers') {
    try {
        $stmt = $pdo->query("
            SELECT
                UserID,
                FullName,
                Email,
                Phone,
                Role,
                CreatedDate,
                Status,
                Points
            FROM User_Account
            ORDER BY CreatedDate DESC
            LIMIT 50
        ");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = 'Không thể tải danh sách khách hàng: ' . $e->getMessage();
    }
}

// Marketing: voucher
$vouchers = [];
if ($tab === 'marketing') {
    try {
        $stmt = $pdo->query("
            SELECT
                VoucherID,
                Code,
                Description,
                DiscountType,
                DiscountValue,
                MinOrder,
                MaxDiscount,
                StartDate,
                EndDate,
                UsageLimit,
                UsedCount,
                Status
            FROM Voucher
            ORDER BY CreatedDate DESC
        ");
        $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // nếu không có cột CreatedDate trong bảng Voucher thì xóa ORDER BY CreatedDate
    }
}

// Bài đăng (review sách / blog)
$posts = [];
if ($tab === 'posts') {
    try {
        $stmt = $pdo->query("
            SELECT
                PostID,
                Title,
                ThumbnailUrl,
                CreatedAt,
                Status
            FROM Book_Post
            ORDER BY CreatedAt DESC
            LIMIT 50
        ");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // nếu chưa có bảng Book_Post thì tạm bỏ qua
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Moonlit Store</title>

    <!-- CSS Moonlit -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
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
            <a href="shop.php" class="account-btn-secondary header-account-btn">
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
        Kênh marketing
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
            <div class="account-card mb-3">
                <h2 class="account-card-title">Tạo mã giảm giá mới</h2>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="create_voucher">

                    <div class="col-md-3">
                        <label class="account-label" for="code">Mã *</label>
                        <input
                            type="text"
                            id="code"
                            name="code"
                            class="account-input w-100"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="discount_type">Loại giảm</label>
                        <select id="discount_type" name="discount_type" class="account-input w-100">
                            <option value="PERCENT">Phần trăm (%)</option>
                            <option value="AMOUNT">Số tiền (VND)</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="discount_value">Giá trị giảm *</label>
                        <input
                            type="number"
                            id="discount_value"
                            name="discount_value"
                            class="account-input w-100"
                            step="0.01"
                            min="0"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="min_order">Đơn tối thiểu</label>
                        <input
                            type="number"
                            id="min_order"
                            name="min_order"
                            class="account-input w-100"
                            step="0.01"
                            min="0"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="max_discount">Giảm tối đa (VND)</label>
                        <input
                            type="number"
                            id="max_discount"
                            name="max_discount"
                            class="account-input w-100"
                            step="0.01"
                            min="0"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="usage_limit">Giới hạn lượt dùng</label>
                        <input
                            type="number"
                            id="usage_limit"
                            name="usage_limit"
                            class="account-input w-100"
                            min="0"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="start_date">Ngày bắt đầu</label>
                        <input
                            type="datetime-local"
                            id="start_date"
                            name="start_date"
                            class="account-input w-100"
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="account-label" for="end_date">Ngày kết thúc</label>
                        <input
                            type="datetime-local"
                            id="end_date"
                            name="end_date"
                            class="account-input w-100"
                        >
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="account-btn-save">
                            Tạo mã giảm giá
                        </button>
                    </div>
                </form>
            </div>

            <div class="account-card">
                <h2 class="account-card-title">Danh sách mã giảm giá</h2>
                <?php if (empty($vouchers)): ?>
                    <p class="account-empty-text mb-0">
                        Chưa có mã giảm giá nào.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Mô tả</th>
                                <th>Loại</th>
                                <th>Giá trị</th>
                                <th>Đơn tối thiểu</th>
                                <th>Giảm tối đa</th>
                                <th>Thời gian</th>
                                <th>Dùng / Giới hạn</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($vouchers as $v): ?>
                                <tr>
                                    <td><?php echo h($v['Code']); ?></td>
                                    <td><?php echo h($v['Description']); ?></td>
                                    <td><?php echo h($v['DiscountType']); ?></td>
                                    <td><?php echo number_format($v['DiscountValue'], 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($v['MinOrder'] ?? 0, 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($v['MaxDiscount'] ?? 0, 0, ',', '.'); ?></td>
                                    <td>
                                        <?php echo h($v['StartDate']); ?>
                                        <br>→ <?php echo h($v['EndDate']); ?>
                                    </td>
                                    <td>
                                        <?php echo (int)$v['UsedCount']; ?> /
                                        <?php echo $v['UsageLimit'] !== null ? (int)$v['UsageLimit'] : '∞'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- ===================== FOOTER ===================== -->
<footer class="site-footer">
    © <?php echo date('Y'); ?> Moonlit — All rights reserved.
</footer>

</body>
</html>

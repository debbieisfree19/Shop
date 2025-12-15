<?php
// Kết nối Database (giả sử bạn đã có file này)
require_once 'db_connect.php';

// Hàm helper để render an toàn (chống XSS)
if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}


$message = '';
$error = '';

// ============================================================================
// 1. CHỨC NĂNG TỰ ĐỘNG CẬP NHẬT TRẠNG THÁI (AUTO STATUS UPDATE)
// Logic: Nếu đến ngày bắt đầu (StartDate <= NOW) mà Status vẫn là 0 -> Update lên 1
// ============================================================================
try {
    $stmtUpdate = $pdo->prepare("
        UPDATE Voucher 
        SET Status = 1 
        WHERE Status = 0 
        AND StartDate <= NOW() 
        AND (EndDate IS NULL OR EndDate > NOW())
    ");
    $stmtUpdate->execute();
    // Có thể thông báo số dòng đã update nếu cần: $stmtUpdate->rowCount();
} catch (Exception $e) {
    // Log error
}

// ============================================================================
// 2. XỬ LÝ TẠO VOUCHER MỚI
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_voucher') {
    try {
        // Tạo Voucher ID tự động (V00001...)
        $stmtId = $pdo->query("SELECT MAX(CAST(SUBSTRING(VoucherID, 2) AS UNSIGNED)) as max_id FROM Voucher");
        $next_id = ($stmtId->fetch()['max_id'] ?? 0) + 1;
        $voucher_id = 'V' . str_pad($next_id, 5, '0', STR_PAD_LEFT);

        // Lấy dữ liệu từ form
        $voucher_name = $_POST['VoucherName'];
        $code = $_POST['Code'];
        $desc = $_POST['Description'];
        $type = $_POST['DiscountType']; // PERCENT hoặc AMOUNT
        $value = $_POST['DiscountValue'];
        $min_order = $_POST['MinOrder'];
        $max_discount = $_POST['MaxDiscount'];
        $start_date = $_POST['StartDate'];
        $end_date = !empty($_POST['EndDate']) ? $_POST['EndDate'] : NULL;
        $usage_limit = $_POST['UsageLimit'];
        $status = $_POST['Status']; // 0 hoặc 1
        $rank = $_POST['RankRequirement']; 
        
        // Logic điểm: Nếu rank là None (Chung) thì lấy điểm từ form, ngược lại là 0
        $point = ($rank === 'None') ? $_POST['VoucherPoint'] : 0;

        $sql = "INSERT INTO Voucher (
            VoucherID, VoucherName, Code, Description, DiscountType, DiscountValue, 
            MinOrder, MaxDiscount, StartDate, EndDate, UsageLimit, UsedCount, 
            VoucherPoint, Status, RankRequirement
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, 0, 
            ?, ?, ?
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $voucher_id, $voucher_name, $code, $desc, $type, $value,
            $min_order, $max_discount, $start_date, $end_date, $usage_limit,
            $point, $status, $rank
        ]);

        $message = "Tạo voucher thành công! Mã: " . $code;
    } catch (Exception $e) {
        $error = "Lỗi tạo voucher: " . $e->getMessage();
    }
}

// ============================================================================
// 3. XỬ LÝ LỌC VÀ HIỂN THỊ DANH SÁCH (FILTER & LIST)
// ============================================================================
$filter_status = $_GET['status'] ?? '';
$filter_rank = $_GET['rank'] ?? '';

$sqlList = "SELECT * FROM Voucher WHERE 1=1";
$params = [];

if ($filter_status !== '') {
    $sqlList .= " AND Status = ?";
    $params[] = $filter_status;
}

if ($filter_rank !== '') {
    $sqlList .= " AND RankRequirement = ?";
    $params[] = $filter_rank;
}

$sqlList .= " ORDER BY StartDate DESC"; // Mới nhất lên đầu

$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($params);
$vouchers = $stmtList->fetchAll();

// Mảng map Rank sang tiếng Việt để hiển thị
$rankMap = [
    'None' => 'Chung',
    'Free' => 'Miễn phí',
    'Bronze' => 'Đồng',
    'Silver' => 'Bạc',
    'Gold' => 'Vàng',
    'Platinum' => 'Bạch kim'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản Lý Voucher</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header { background-color: #f8f9fa; font-weight: bold; }
        .status-badge-1 { background-color: #198754; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;}
        .status-badge-0 { background-color: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8em;}
    </style>
</head>
<body class="bg-light p-4">
<div class="container-fluid">

    <?php if ($message): ?>
        <div class="alert alert-success"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header text-primary">
                    <i class="fas fa-plus-circle"></i> Tạo Voucher Mới
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="create_voucher">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hạng áp dụng</label>
                            <select class="form-select" name="RankRequirement" id="rankSelect" required onchange="togglePointInput()">
                                <option value="None">Chung (Cần đổi điểm)</option>
                                <option value="Free">Miễn phí (Tặng)</option>
                                <option value="Bronze">Đồng</option>
                                <option value="Silver">Bạc</option>
                                <option value="Gold">Vàng</option>
                                <option value="Platinum">Bạch kim</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Tên Voucher</label>
                                <input type="text" class="form-control" name="VoucherName" required placeholder="VD: Giảm giá hè">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Mã Code (Unique)</label>
                                <input type="text" class="form-control" name="Code" required placeholder="VD: SUMMER2024">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="Description" rows="2"></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Loại giảm giá</label>
                                <select class="form-select" name="DiscountType">
                                    <option value="PERCENT">Phần trăm (%)</option>
                                    <option value="AMOUNT">Số tiền (VND)</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Giá trị giảm</label>
                                <input type="number" class="form-control" name="DiscountValue" required placeholder="VD: 10 hoặc 50000">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Đơn tối thiểu</label>
                                <input type="number" class="form-control" name="MinOrder" value="0">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Giảm tối đa</label>
                                <input type="number" class="form-control" name="MaxDiscount" value="0" placeholder="0 = KGH">
                            </div>
                        </div>

                        <div class="mb-3" id="pointContainer">
                            <label class="form-label fw-bold text-danger">Điểm cần đổi</label>
                            <input type="number" class="form-control" name="VoucherPoint" value="0">
                            <small class="text-muted">Chỉ nhập khi Hạng là "Chung"</small>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Giới hạn số lượng</label>
                                <input type="number" class="form-control" name="UsageLimit" value="100" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Trạng thái ban đầu</label>
                                <select class="form-select" name="Status">
                                    <option value="1">Đang áp dụng (1)</option>
                                    <option value="0">Ngừng/Chờ (0)</option>
                                </select>
                                <small class="text-muted" style="font-size: 10px;">Nếu chọn 0, hệ thống sẽ tự bật khi đến Ngày bắt đầu.</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Ngày bắt đầu</label>
                                <input type="datetime-local" class="form-control" name="StartDate" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Ngày hết hạn</label>
                                <input type="datetime-local" class="form-control" name="EndDate">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Lưu Voucher</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list"></i> Danh Sách Voucher</span>
                    
                    <form method="GET" class="d-flex gap-2">
                        <select name="rank" class="form-select form-select-sm" style="width: 150px;">
                            <option value="">-- Tất cả hạng --</option>
                            <?php foreach ($rankMap as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filter_rank === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="form-select form-select-sm" style="width: 150px;">
                            <option value="">-- Trạng thái --</option>
                            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Đang áp dụng</option>
                            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Ngừng áp dụng</option>
                        </select>
                        
                        <button type="submit" class="btn btn-sm btn-secondary">Lọc</button>
                        <a href="admin-voucher-manager.php" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0" style="font-size: 0.9rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th>Mã</th>
                                    <th>Tên/Code</th>
                                    <th>Giảm giá</th>
                                    <th>Hạng</th>
                                    <th>Thời gian</th>
                                    <th>SL/Đã dùng</th>
                                    <th>Điểm</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vouchers)): ?>
                                    <tr><td colspan="8" class="text-center p-3">Không có voucher nào.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($vouchers as $v): ?>
                                        <tr>
                                            <td><?= h($v['VoucherID']) ?></td>
                                            <td>
                                                <strong><?= h($v['Code']) ?></strong><br>
                                                <small><?= h($v['VoucherName']) ?></small>
                                            </td>
                                            <td>
                                                <?php 
                                                    if ($v['DiscountType'] == 'PERCENT') echo number_format($v['DiscountValue'], 0) . '%';
                                                    else echo number_format($v['DiscountValue'], 0) . 'đ';
                                                ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?= isset($rankMap[$v['RankRequirement']]) ? $rankMap[$v['RankRequirement']] : $v['RankRequirement'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    Start: <?= date('d/m/y H:i', strtotime($v['StartDate'])) ?><br>
                                                    End: <?= $v['EndDate'] ? date('d/m/y H:i', strtotime($v['EndDate'])) : '∞' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?= $v['UsedCount'] ?> / <?= $v['UsageLimit'] ?>
                                            </td>
                                            <td>
                                                <?php if($v['RankRequirement'] == 'None'): ?>
                                                    <strong><?= number_format($v['VoucherPoint']) ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge-<?= $v['Status'] ?>">
                                                    <?= $v['Status'] == 1 ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function togglePointInput() {
        var rankSelect = document.getElementById('rankSelect');
        var pointContainer = document.getElementById('pointContainer');
        var pointInput = pointContainer.querySelector('input');

        // Logic: Nếu chọn 'None' (Chung) thì hiện ô nhập điểm, ngược lại thì ẩn
        if (rankSelect.value === 'None') {
            pointContainer.style.display = 'block';
            pointInput.disabled = false;
        } else {
            pointContainer.style.display = 'none';
            pointInput.value = 0; // Reset về 0
            pointInput.disabled = true; // Disable để không gửi lên server (hoặc gửi 0)
        }
    }

    // Chạy hàm 1 lần khi load trang để set đúng trạng thái ban đầu
    window.onload = togglePointInput;
</script>

</body>
</html>
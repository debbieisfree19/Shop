<?php
// admin-voucher-manager.php

// 1. KẾT NỐI DB & HELPER
if (!isset($pdo)) {
    require_once 'db_connect.php';
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
$error = '';

// ============================================================================
// 1. XỬ LÝ CẬP NHẬT TRẠNG THÁI NHANH (DROPDOWN)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status_quick') {
    $v_id = $_POST['voucher_id'];
    $new_status = (int)$_POST['new_status']; 

    try {
        $stmt = $pdo->prepare("UPDATE Voucher SET Status = :status WHERE VoucherID = :id");
        $stmt->bindValue(':status', $new_status, PDO::PARAM_INT);
        $stmt->bindValue(':id', $v_id);
        $stmt->execute();
        
        echo "<script>window.location.href = '?tab=marketing';</script>";
        exit;
    } catch (Exception $e) {
        $error = "Lỗi cập nhật trạng thái: " . $e->getMessage();
    }
}

// ============================================================================
// 2. XỬ LÝ TẠO VOUCHER MỚI
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_voucher') {
    try {
        $stmtId = $pdo->query("SELECT MAX(CAST(SUBSTRING(VoucherID, 2) AS UNSIGNED)) as max_id FROM Voucher");
        $next_id = ($stmtId->fetch()['max_id'] ?? 0) + 1;
        $voucher_id = 'V' . str_pad($next_id, 5, '0', STR_PAD_LEFT);

        // ... (Giữ nguyên logic lấy dữ liệu form) ...
        $voucher_name = $_POST['VoucherName'];
        $code = $_POST['Code'];
        $desc = $_POST['Description'];
        $type = $_POST['DiscountType'];
        $value = $_POST['DiscountValue'];
        $min_order = $_POST['MinOrder'];
        $max_discount = $_POST['MaxDiscount'];
        $start_date = str_replace('T', ' ', $_POST['StartDate']);
        $end_date = !empty($_POST['EndDate']) ? str_replace('T', ' ', $_POST['EndDate']) : NULL;
        $usage_limit = $_POST['UsageLimit'];
        $status = (int)$_POST['Status']; 
        $rank = $_POST['RankRequirement'];
        $point = ($rank === 'None') ? $_POST['VoucherPoint'] : 0;

        $sql = "INSERT INTO Voucher (
            VoucherID, VoucherName, Code, Description, DiscountType, DiscountValue, 
            MinOrder, MaxDiscount, StartDate, EndDate, UsageLimit, UsedCount, 
            VoucherPoint, Status, RankRequirement
        ) VALUES (
            :id, :name, :code, :desc, :type, :val, 
            :min, :max, :start, :end, :limit, 0, 
            :point, :status, :rank
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $voucher_id);
        $stmt->bindValue(':name', $voucher_name);
        $stmt->bindValue(':code', $code);
        $stmt->bindValue(':desc', $desc);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':val', $value);
        $stmt->bindValue(':min', $min_order);
        $stmt->bindValue(':max', $max_discount);
        $stmt->bindValue(':start', $start_date);
        $stmt->bindValue(':end', $end_date);
        $stmt->bindValue(':limit', $usage_limit);
        $stmt->bindValue(':point', $point);
        $stmt->bindValue(':status', $status, PDO::PARAM_INT); 
        $stmt->bindValue(':rank', $rank);
        
        $stmt->execute();

        $message = "Tạo voucher thành công! Mã: " . $code;
        echo "<script>window.location.href = '?tab=marketing';</script>";
        exit;
        
    } catch (Exception $e) {
        $error = "Lỗi tạo voucher: " . $e->getMessage();
    }
}

// ============================================================================
// 3. XỬ LÝ LỌC & PHÂN TRANG (PAGINATION)
// ============================================================================
$filter_status = $_GET['status'] ?? '';
$filter_rank = $_GET['rank'] ?? '';

// [PHÂN TRANG] 1. Xác định trang hiện tại và số item mỗi trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 7; // Số voucher mỗi trang (Bạn có thể sửa số này)
$offset = ($page - 1) * $limit;

// Điều kiện lọc chung
$whereClause = "WHERE 1=1";
$params = [];

if ($filter_status !== '') {
    $whereClause .= " AND Status = ?";
    $params[] = $filter_status;
}

if ($filter_rank !== '') {
    $whereClause .= " AND RankRequirement = ?";
    $params[] = $filter_rank;
}

// [PHÂN TRANG] 2. Đếm tổng số bản ghi (để tính tổng số trang)
$sqlCount = "SELECT COUNT(*) FROM Voucher " . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_rows = $stmtCount->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// [PHÂN TRANG] 3. Lấy dữ liệu với LIMIT và OFFSET
$sqlList = "SELECT * FROM Voucher " . $whereClause . " ORDER BY StartDate DESC LIMIT $limit OFFSET $offset";
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($params);
$vouchers = $stmtList->fetchAll();

$rankMap = [
    'None' => 'Chung', 'Free' => 'Miễn phí', 'Bronze' => 'Đồng',
    'Silver' => 'Bạc', 'Gold' => 'Vàng', 'Platinum' => 'Bạch kim'
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        .card-header { background-color: #f8f9fa; font-weight: bold; }
        
        /* CSS cho Badge Rank */
        .rank-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: 600; border: 1px solid #ccc; }
        .rank-Gold { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .rank-Silver { background-color: #e2e3e5; color: #41464b; border-color: #d6d8db; }
        .rank-Bronze { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
        .rank-Platinum { background-color: #cff4fc; color: #055160; border-color: #b6effb; }
        .rank-None { background-color: #f8f9fa; color: #212529; }
        
        /* CSS cho Dropdown Status trong bảng */
        .status-select { font-size: 0.85rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; border: 1px solid #ced4da; cursor: pointer; }
        .status-active { color: #198754; border-color: #198754; }
        .status-inactive { color: #dc3545; border-color: #dc3545; }

        /* Pagination CSS */
        .pagination { margin-bottom: 0; }
        .page-link { color: #333; }
        .page-item.active .page-link { background-color: #0d6efd; border-color: #0d6efd; color: white; }
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
                <div class="card-header text-primary"><i class="fas fa-plus-circle"></i> Tạo Voucher Mới</div>
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
                                    <option value="1">Active (1)</option>
                                    <option value="0">Inactive (0)</option>
                                </select>
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
                        <input type="hidden" name="tab" value="marketing">

                        <select name="rank" class="form-select form-select-sm" style="width: 150px;">
                            <option value="">-- Tất cả hạng --</option>
                            <?php foreach ($rankMap as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $filter_rank === $key ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="form-select form-select-sm" style="width: 150px;">
                            <option value="">-- Trạng thái --</option>
                            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        
                        <button type="submit" class="btn btn-sm btn-secondary">Lọc</button>
                        <a href="?tab=marketing" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                                    <th>Trạng thái</th> 
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
                                                <strong class="text-primary"><?= h($v['Code']) ?></strong><br>
                                                <small><?= h($v['VoucherName']) ?></small>
                                                <div class="mt-1 small text-muted" style="font-size: 0.8em;">
                                                    <?php if ($v['MinOrder'] > 0): ?>
                                                        <div>Đơn tối thiểu: <?= number_format($v['MinOrder'], 0, ',', '.') ?>đ</div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($v['MaxDiscount'] > 0): ?>
                                                        <div>Giảm tối đa: <?= number_format($v['MaxDiscount'], 0, ',', '.') ?>đ</div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                    if ($v['DiscountType'] == 'PERCENT') echo number_format($v['DiscountValue'], 0) . '%';
                                                    else echo number_format($v['DiscountValue'], 0) . 'đ';
                                                ?>
                                            </td>
                                            <td>
                                                <span class="rank-badge rank-<?= $v['RankRequirement'] ?>">
                                                    <?= isset($rankMap[$v['RankRequirement']]) ? $rankMap[$v['RankRequirement']] : $v['RankRequirement'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    Start: <?= date('d/m/y H:i', strtotime($v['StartDate'])) ?><br>
                                                    End: <?= $v['EndDate'] ? date('d/m/y H:i', strtotime($v['EndDate'])) : '∞' ?>
                                                </small>
                                            </td>
                                            <td><?= $v['UsedCount'] ?> / <?= $v['UsageLimit'] ?></td>
                                            <td>
                                                <?php if($v['RankRequirement'] == 'None'): ?>
                                                    <strong><?= number_format($v['VoucherPoint']) ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_status_quick">
                                                    <input type="hidden" name="voucher_id" value="<?= h($v['VoucherID']) ?>">
                                                    
                                                    <?php 
                                                        $currentStatus = (int)$v['Status']; 
                                                        $dropdownClass = ($currentStatus === 1) ? 'status-active' : 'status-inactive';
                                                    ?>

                                                    <select name="new_status" class="form-select form-select-sm status-select <?= $dropdownClass ?>" 
                                                            style="width: 110px;" 
                                                            onchange="this.form.submit()">
                                                        <option value="1" <?= $currentStatus === 1 ? 'selected' : '' ?> style="color: #198754; font-weight: bold;">Active</option>
                                                        <option value="0" <?= $currentStatus === 0 ? 'selected' : '' ?> style="color: #dc3545; font-weight: bold;">Inactive</option>
                                                    </select>
                                                </form>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Hiển thị <?= count($vouchers) ?> / <?= $total_rows ?> voucher
                    </small>
                    
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm m-0">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=marketing&page=<?= $page - 1 ?>&rank=<?= h($filter_rank) ?>&status=<?= h($filter_status) ?>">Trước</a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=marketing&page=<?= $i ?>&rank=<?= h($filter_rank) ?>&status=<?= h($filter_status) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=marketing&page=<?= $page + 1 ?>&rank=<?= h($filter_rank) ?>&status=<?= h($filter_status) ?>">Sau</a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
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
        if (rankSelect.value === 'None') {
            pointContainer.style.display = 'block';
            pointInput.disabled = false;
        } else {
            pointContainer.style.display = 'none';
            pointInput.value = 0; 
            pointInput.disabled = true;
        }
    }
    window.onload = togglePointInput;
</script>

</body>
</html>
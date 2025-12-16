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

// --- LOGIC TỰ ĐỘNG CẬP NHẬT TRẠNG THÁI THEO THỜI GIAN (GIỮ NGUYÊN) ---
try {
    $now = new DateTime('now', new DateTimeZone('Asia/Ho_Chi_Minh'));
    $nowStr = $now->format('Y-m-d H:i:s');

    // Kích hoạt voucher đến giờ
    $stmtActivate = $pdo->prepare("UPDATE Voucher SET Status = 1 WHERE Status = 0 AND StartDate <= :now");
    $stmtActivate->bindValue(':now', $nowStr);
    $stmtActivate->execute();
} catch (Exception $e) {}

try {
    // Hết hạn voucher
    $stmtExpire = $pdo->prepare("
        UPDATE Voucher
        SET Status = 0
        WHERE Status = 1
          AND EndDate IS NOT NULL
          AND EndDate < :now
    ");
    $stmtExpire->bindValue(':now', $nowStr);
    $stmtExpire->execute();
} catch (Exception $e) {}

try {
    // Voucher set ngày chạy ở tương lai thì phải inactive
    $stmtBeforeStartDate = $pdo->prepare("
        UPDATE Voucher
        SET Status = 0
        WHERE StartDate > :now AND StartDate IS NOT NULL
    ");
    $stmtBeforeStartDate->bindValue(':now', $nowStr);
    $stmtBeforeStartDate->execute();
} catch (Exception $e) {}

// ============================================================================
// [THAY ĐỔI] 1. XỬ LÝ XÓA VOUCHER (CHỈ KHI STATUS = 0)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_voucher') {
    $v_id = $_POST['voucher_id'];
    try {
        // Kiểm tra xem voucher có đang inactive không
        $checkStmt = $pdo->prepare("SELECT Status FROM Voucher WHERE VoucherID = :id");
        $checkStmt->execute([':id' => $v_id]);
        $vStatus = $checkStmt->fetchColumn();

        if ($vStatus === 0 || $vStatus === '0') {
            $delStmt = $pdo->prepare("DELETE FROM Voucher WHERE VoucherID = :id");
            $delStmt->execute([':id' => $v_id]);
            $message = "Đã xóa voucher thành công!";
        } else {
            $error = "Chỉ có thể xóa Voucher đang ngưng hoạt động (Inactive)!";
        }
    } catch (Exception $e) {
        $error = "Lỗi xóa: " . $e->getMessage();
    }
}

// ============================================================================
// [THAY ĐỔI] 2. XỬ LÝ TẠO MỚI HOẶC CẬP NHẬT VOUCHER
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'create_voucher' || $_POST['action'] === 'update_voucher')) {
    try {
        // Lấy dữ liệu form chung
        $voucher_name = $_POST['VoucherName'];
        $code = $_POST['Code'];
        $desc = $_POST['Description'];
        $type = $_POST['DiscountType'];
        $value = $_POST['DiscountValue'];
        $min_order = $_POST['MinOrder'];
        $max_discount = $_POST['MaxDiscount'];
        $usage_limit = $_POST['UsageLimit'];
        $rank = $_POST['RankRequirement'];
        $point = ($rank === 'None') ? $_POST['VoucherPoint'] : 0;

        // Xử lý thời gian
        $tz = new DateTimeZone('Asia/Ho_Chi_Minh');
        $startRaw = $_POST['StartDate'];
        $startDt = new DateTime($startRaw, $tz);
        $start_date = $startDt->format('Y-m-d H:i:s');

        $end_date = null;
        if (!empty($_POST['EndDate'])) {
            $endRaw = $_POST['EndDate'];
            $endDt = new DateTime($endRaw, $tz);
            $end_date = $endDt->format('Y-m-d H:i:s');
        }

        // Tính toán trạng thái dựa trên thời gian (Logic tự động)
        $now = new DateTime('now', $tz);
        if ($startDt > $now) {
            $status = 0; // Chưa đến ngày -> Inactive
        } else {
            // Nếu có ngày kết thúc và đã qua ngày kết thúc -> Inactive, ngược lại Active
            if ($end_date && $endDt < $now) {
                $status = 0;
            } else {
                $status = 1;
            }
        }

        if ($_POST['action'] === 'create_voucher') {
            // --- LOGIC TẠO MỚI ---
            $stmtId = $pdo->query("SELECT MAX(CAST(SUBSTRING(VoucherID, 2) AS UNSIGNED)) as max_id FROM Voucher");
            $next_id = ($stmtId->fetch()['max_id'] ?? 0) + 1;
            $voucher_id = 'V' . str_pad($next_id, 5, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO Voucher (
                VoucherID, VoucherName, Code, Description, DiscountType, DiscountValue, 
                MinOrder, MaxDiscount, StartDate, EndDate, UsageLimit, UsedCount, 
                VoucherPoint, Status, RankRequirement
            ) VALUES (
                :id, :name, :code, :desc, :type, :val, 
                :min, :max, :start, :end, :limit, 0, 
                :point, :status, :rank
            )";
            $params = [
                ':id' => $voucher_id, ':name' => $voucher_name, ':code' => $code, ':desc' => $desc,
                ':type' => $type, ':val' => $value, ':min' => $min_order, ':max' => $max_discount,
                ':start' => $start_date, ':end' => $end_date, ':limit' => $usage_limit,
                ':point' => $point, ':status' => $status, ':rank' => $rank
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = "Tạo voucher thành công! Mã: " . $code;

        } else {
            // --- LOGIC CẬP NHẬT ---
            $voucher_id = $_POST['voucher_id']; // ID lấy từ hidden field
            
            $sql = "UPDATE Voucher SET 
                VoucherName = :name, Code = :code, Description = :desc, DiscountType = :type, 
                DiscountValue = :val, MinOrder = :min, MaxDiscount = :max, 
                StartDate = :start, EndDate = :end, UsageLimit = :limit, 
                VoucherPoint = :point, Status = :status, RankRequirement = :rank
                WHERE VoucherID = :id";
            
            $params = [
                ':name' => $voucher_name, ':code' => $code, ':desc' => $desc,
                ':type' => $type, ':val' => $value, ':min' => $min_order, ':max' => $max_discount,
                ':start' => $start_date, ':end' => $end_date, ':limit' => $usage_limit,
                ':point' => $point, ':status' => $status, ':rank' => $rank, ':id' => $voucher_id
            ];
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $message = "Cập nhật voucher thành công!";
        }
        
        // Refresh trang để xóa query param edit_id nếu có
        echo "<script>window.location.href = '?tab=voucher';</script>";
        exit;

    } catch (Exception $e) {
        $error = "Lỗi xử lý: " . $e->getMessage();
    }
}

// ============================================================================
// [THAY ĐỔI] 3. LẤY DỮ LIỆU ĐỂ EDIT (NẾU CÓ PARAM edit_id)
// ============================================================================
$editData = null;
if (isset($_GET['edit_id'])) {
    $stmtEdit = $pdo->prepare("SELECT * FROM Voucher WHERE VoucherID = ?");
    $stmtEdit->execute([$_GET['edit_id']]);
    $editData = $stmtEdit->fetch();
}

// ============================================================================
// 4. XỬ LÝ LỌC & PHÂN TRANG (GIỮ NGUYÊN)
// ============================================================================
$filter_status = $_GET['status'] ?? '';
$filter_rank = $_GET['rank'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 7; 
$offset = ($page - 1) * $limit;

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

$sqlCount = "SELECT COUNT(*) FROM Voucher " . $whereClause;
$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->execute($params);
$total_rows = $stmtCount->fetchColumn();
$total_pages = ceil($total_rows / $limit);

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .card-header { background-color: #f8f9fa; font-weight: bold; }
        
        /* CSS cho Badge Rank */
        .rank-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: 600; border: 1px solid #ccc; white-space: nowrap; }
        .rank-Gold { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
        .rank-Silver { background-color: #e2e3e5; color: #41464b; border-color: #d6d8db; }
        .rank-Bronze { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
        .rank-Platinum { background-color: #cff4fc; color: #055160; border-color: #b6effb; }
        .rank-None { background-color: #f8f9fa; color: #212529; }
        
        /* CSS Status Badge */
        .badge-status { padding: 5px 10px; border-radius: 20px; font-size: 0.8em; font-weight: 600; }
        .bg-active { background-color: #d1e7dd; color: #0f5132; }
        .bg-inactive { background-color: #f8d7da; color: #842029; }

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
                <div class="card-header text-primary">
                    <?php if ($editData): ?>
                        <i class="fas fa-edit"></i> Chỉnh sửa Voucher: <?= h($editData['Code']) ?>
                    <?php else: ?>
                        <i class="fas fa-plus-circle"></i> Tạo Voucher Mới
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="<?= $editData ? 'update_voucher' : 'create_voucher' ?>">
                        
                        <?php if ($editData): ?>
                            <input type="hidden" name="voucher_id" value="<?= h($editData['VoucherID']) ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Hạng áp dụng</label>
                            <select class="form-select" name="RankRequirement" id="rankSelect" required onchange="togglePointInput()">
                                <?php 
                                    $currentRank = $editData['RankRequirement'] ?? 'None';
                                    foreach ($rankMap as $rKey => $rLabel) {
                                        $selected = ($currentRank === $rKey) ? 'selected' : '';
                                        echo "<option value='$rKey' $selected>$rLabel</option>";
                                    }
                                ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Tên Voucher</label>
                                <input type="text" class="form-control" name="VoucherName" required 
                                       placeholder="VD: Giảm giá hè" value="<?= h($editData['VoucherName'] ?? '') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Mã Code</label>
                                <input type="text" class="form-control" name="Code" required 
                                       placeholder="VD: SUMMER2024" value="<?= h($editData['Code'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="Description" rows="2"><?= h($editData['Description'] ?? '') ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Loại giảm giá</label>
                                <select class="form-select" name="DiscountType">
                                    <?php $dType = $editData['DiscountType'] ?? 'PERCENT'; ?>
                                    <option value="PERCENT" <?= $dType == 'PERCENT' ? 'selected' : '' ?>>Phần trăm (%)</option>
                                    <option value="AMOUNT" <?= $dType == 'AMOUNT' ? 'selected' : '' ?>>Số tiền (VND)</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Giá trị giảm</label>
                                <input type="number" class="form-control" name="DiscountValue" required 
                                       placeholder="VD: 10 hoặc 50000" value="<?= h($editData['DiscountValue'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Đơn tối thiểu</label>
                                <input type="number" class="form-control" name="MinOrder" 
                                       value="<?= h($editData['MinOrder'] ?? '0') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Giảm tối đa</label>
                                <input type="number" class="form-control" name="MaxDiscount" 
                                       value="<?= h($editData['MaxDiscount'] ?? '0') ?>" placeholder="0 = KGH">
                            </div>
                        </div>
                        <div class="mb-3" id="pointContainer">
                            <label class="form-label fw-bold text-danger">Điểm cần đổi</label>
                            <input type="number" class="form-control" name="VoucherPoint" 
                                   value="<?= h($editData['VoucherPoint'] ?? '0') ?>">
                            <small class="text-muted">Chỉ nhập khi Hạng là "Chung"</small>
                        </div>
                        <div class="mb-3">
                                <label class="form-label">Giới hạn số lượng</label>
                                <input type="number" class="form-control" name="UsageLimit" 
                                       value="<?= h($editData['UsageLimit'] ?? '100') ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Ngày bắt đầu</label>
                                <?php 
                                    $sDate = isset($editData['StartDate']) ? date('Y-m-d\TH:i', strtotime($editData['StartDate'])) : '';
                                ?>
                                <input type="datetime-local" class="form-control" name="StartDate" required value="<?= $sDate ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Ngày hết hạn</label>
                                <?php 
                                    $eDate = isset($editData['EndDate']) ? date('Y-m-d\TH:i', strtotime($editData['EndDate'])) : '';
                                ?>
                                <input type="datetime-local" class="form-control" name="EndDate" value="<?= $eDate ?>">
                            </div>
                        </div>
                        
                        <?php if ($editData): ?>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-warning w-50 fw-bold">Cập nhật</button>
                                <a href="?tab=voucher" class="btn btn-outline-secondary w-50">Hủy / Tạo mới</a>
                            </div>
                        <?php else: ?>
                            <button type="submit" class="btn btn-primary w-100">Lưu Voucher</button>
                        <?php endif; ?>
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
                        <a href="?tab=voucher" class="btn btn-sm btn-outline-secondary">Reset</a>
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
                                    <th class="text-center">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vouchers)): ?>
                                    <tr><td colspan="9" class="text-center p-3">Không có voucher nào.</td></tr>
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
                                                <?php if ($v['Status'] == 1): ?>
                                                    <span class="badge-status bg-active">Active</span>
                                                <?php else: ?>
                                                    <span class="badge-status bg-inactive">Inactive</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-2">
                                                    <a href="?tab=voucher&edit_id=<?= h($v['VoucherID']) ?>" 
                                                       class="btn btn-sm btn-outline-primary" title="Sửa">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <?php if ($v['Status'] == 0): ?>
                                                        <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn xóa voucher này không?');">
                                                            <input type="hidden" name="action" value="delete_voucher">
                                                            <input type="hidden" name="voucher_id" value="<?= h($v['VoucherID']) ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
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
                                <a class="page-link" href="?tab=voucher&page=<?= $page - 1 ?>&rank=<?= h($filter_rank) ?>&status=<?= h($filter_status) ?>">Trước</a>
                            </li>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=voucher&page=<?= $i ?>&rank=<?= h($filter_rank) ?>&status=<?= h($filter_status) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=voucher&page=<?= $page + 1 ?>&rank=<?= h($filter_rank) ?>&status=<?= h($filter_status) ?>">Sau</a>
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
            // Không reset về 0 ở đây nếu đang edit, nhưng khi submit logic PHP sẽ xử lý.
            // Để UI sạch sẽ:
            pointInput.disabled = true;
        }
    }
    window.onload = togglePointInput;
</script>

</body>
</html>

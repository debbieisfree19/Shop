<?php
// admin-orders.php

// 1. KẾT NỐI DB & HELPER
if (!isset($pdo)) {
    require_once 'db_connect.php';
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

// 2. DANH SÁCH TRẠNG THÁI (Dùng cho hiển thị và filter)
// Trạng thái chính của đơn hàng
$orderStatuses = [
    'ALL'           => 'Tất cả',
    'Chờ xác nhận'  => 'Chờ xác nhận',
    'Đã xác nhận'   => 'Đã xác nhận',
    'Đang giao'     => 'Đang giao',
    'Đã giao'       => 'Đã giao',
    'Đã nhận'       => 'Đã nhận',
    'Bị hủy'        => 'Bị hủy',
    'Trả hàng'      => 'Trả hàng',
    'Đã hoàn tiền'  => 'Đã hoàn tiền'
];

// Danh sách trạng thái được phép cập nhật thủ công cho đơn hàng chính
$allowedUpdateStatuses = [
    'Đã xác nhận' => 'Đã xác nhận',
    'Đang giao'   => 'Đang giao',
    'Đã giao'     => 'Đã giao'
];

// Trạng thái chi tiết của quy trình trả hàng (Dùng cho filter và hiển thị)
$returnStatuses = [
    'RET_PENDING'   => 'Chờ xác nhận',
    'RET_CONFIRMED' => 'Đã xác nhận',
    'RET_PICKUP'    => 'Đang tới lấy',
    'RET_RETURNING' => 'Đang trả về',
    'RET_CHECKING'  => 'Kiểm hàng',
];

// Danh sách trạng thái được phép cập nhật trong quy trình trả hàng
$allowedReturnUpdateStatuses = [
    'Đã xác nhận'  => 'Đã xác nhận',
    'Đang tới lấy' => 'Đang tới lấy',
    'Đang trả về'  => 'Đang trả về',
    'Kiểm hàng'    => 'Kiểm hàng',
    'Chấp thuận'   => 'Chấp thuận'
];

// 3. XỬ LÝ POST: CẬP NHẬT TRẠNG THÁI
$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Cập nhật trạng thái chính của đơn hàng
    if ($action === 'update_order_status') {
        $orderId = $_POST['order_id'] ?? '';
        $newStatus = $_POST['status'] ?? '';

        if ($orderId && $newStatus) {
            try {
                $stmt = $pdo->prepare("UPDATE `Order` SET Status = ? WHERE OrderID = ?");
                $stmt->execute([$newStatus, $orderId]);
                $message = "Đã cập nhật đơn $orderId thành: $newStatus";
                $msg_type = 'success';
            } catch (Exception $e) {
                $message = "Lỗi: " . $e->getMessage();
                $msg_type = 'danger';
            }
        }
    }

    // Cập nhật trạng thái quy trình TRẢ HÀNG
    if ($action === 'update_return_status') {
        $returnId = $_POST['return_id'] ?? '';
        $newReturnStatus = $_POST['return_status'] ?? '';
        
        if ($returnId && $newReturnStatus) {
            try {
                $pdo->beginTransaction();

                // 1. Cập nhật bảng Returns_Order
                $stmt = $pdo->prepare("UPDATE Returns_Order SET Status = ? WHERE ReturnID = ?");
                $stmt->execute([$newReturnStatus, $returnId]);
                
                // 2. [LOGIC MỚI] Nếu chọn "Chấp thuận" -> Tự động đổi Order Status thành "Đã hoàn tiền"
                if ($newReturnStatus === 'Chấp thuận') {
                    // Lấy OrderID từ ReturnID
                    $stmtGetOrder = $pdo->prepare("SELECT OrderID FROM Returns_Order WHERE ReturnID = ?");
                    $stmtGetOrder->execute([$returnId]);
                    $oid = $stmtGetOrder->fetchColumn();
                    
                    if ($oid) {
                        $pdo->prepare("UPDATE `Order` SET Status = 'Đã hoàn tiền' WHERE OrderID = ?")->execute([$oid]);
                    }
                }
                
                $pdo->commit();
                $message = "Đã cập nhật yêu cầu trả hàng $returnId thành: $newReturnStatus";
                $msg_type = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Lỗi: " . $e->getMessage();
                $msg_type = 'danger';
            }
        }
    }
}

// 4. LẤY DỮ LIỆU ĐƠN HÀNG (GET) & PHÂN TRANG
$filter       = $_GET['status'] ?? 'ALL';
$returnFilter = $_GET['return_filter'] ?? '';
$searchId     = isset($_GET['search_id']) ? trim($_GET['search_id']) : '';

// --- CẤU HÌNH PHÂN TRANG ---
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Số đơn hàng hiển thị trên 1 trang (bạn có thể sửa số này)
$page  = max($page, 1); // Đảm bảo page luôn >= 1
$offset = ($page - 1) * $limit;

// --- XÂY DỰNG WHERE CLAUSE (Dùng chung cho cả đếm tổng và lấy dữ liệu) ---
$whereSql = " WHERE 1=1 ";
$params = [];

// Lọc theo Mã Đơn Hàng
if (!empty($searchId)) {
    $whereSql .= " AND o.OrderID LIKE ?";
    $params[] = "%$searchId%";
}

// Lọc theo Trạng thái
if ($filter !== 'ALL') {
    if ($filter === 'Trả hàng' && !empty($returnFilter)) {
        $realStatus = $returnStatuses[$returnFilter] ?? '';
        if ($realStatus) {
            $whereSql .= " AND ro.Status = ?";
            $params[] = $realStatus;
        } else {
            $whereSql .= " AND o.Status = 'Trả hàng'";
        }
    } else {
        $whereSql .= " AND o.Status = ?";
        $params[] = $filter;
    }
}

// --- BƯỚC A: ĐẾM TỔNG SỐ ĐƠN (Để tính số trang) ---
// Chúng ta cần query riêng để đếm tổng số dòng thỏa mãn điều kiện lọc
$countSql = "
    SELECT COUNT(DISTINCT o.OrderID) 
    FROM `Order` o
    LEFT JOIN User_Account u ON o.UserID = u.UserID
    LEFT JOIN Returns_Order ro ON o.OrderID = ro.OrderID
    $whereSql
";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalOrders = $stmtCount->fetchColumn();
$totalPages  = ceil($totalOrders / $limit);

// --- BƯỚC B: LẤY DỮ LIỆU CHO TRANG HIỆN TẠI ---
$sql = "
    SELECT 
        o.OrderID, o.UserID, o.TotalAmount, o.TotalAmountAfterVoucher, o.Status, 
        o.PaymentMethod, o.ShippingCity, o.ShippingDistrict, o.ShippingWard, 
        o.ShippingStreet, o.ShippingNumber, o.CreatedDate, o.DateReceived, o.Note,
        so.ShippingID, c.CarrierName, c.ShippingPrice,
        u.FullName as CustomerName, u.Phone as CustomerPhone, u.Email as CustomerEmail,
        v.DiscountValue, v.DiscountType,
        ro.ReturnID, ro.Status as ReturnStatus, ro.TotalRefund, ro.CreatedDate as ReturnDate
    FROM `Order` o
    LEFT JOIN User_Account u ON o.UserID = u.UserID
    LEFT JOIN Shipping_Order so ON o.OrderID = so.OrderID
    LEFT JOIN Carrier c ON so.CarrierID = c.CarrierID
    LEFT JOIN Returns_Order ro ON o.OrderID = ro.OrderID
    LEFT JOIN User_Voucher uv ON o.OrderID = uv.OrderID
    LEFT JOIN Voucher v ON uv.VoucherID = v.VoucherID
    $whereSql
    ORDER BY o.CreatedDate DESC
    LIMIT $limit OFFSET $offset
";

// Thực thi query chính
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách sản phẩm & chi tiết trả hàng
$orderIds = array_column($orders, 'OrderID');
$orderItems = [];
$returnItems = [];
$returnImages = [];

if (!empty($orderIds)) {
    $inQuery = implode(',', array_fill(0, count($orderIds), '?'));
    $stmtItems = $pdo->prepare("
        SELECT oi.*, p.ProductName, p.Image, s.Format, s.ISBN 
        FROM Order_Items oi
        LEFT JOIN SKU s ON oi.SKU_ID = s.SKUID
        LEFT JOIN Product p ON s.ProductID = p.ProductID
        WHERE oi.OrderID IN ($inQuery)
    ");
    $stmtItems->execute($orderIds);
    while ($row = $stmtItems->fetch(PDO::FETCH_ASSOC)) {
        $orderItems[$row['OrderID']][] = $row;
    }

    $returnIds = array_filter(array_column($orders, 'ReturnID'));
    if (!empty($returnIds)) {
        $inReturn = implode(',', array_fill(0, count($returnIds), '?'));
        
        $stmtRetItems = $pdo->prepare("
            SELECT ri.*, p.ProductName 
            FROM Return_Items ri
            JOIN Order_Items oi ON ri.OrderItemID = oi.OrderItemID
            LEFT JOIN SKU s ON oi.SKU_ID = s.SKUID
            LEFT JOIN Product p ON s.ProductID = p.ProductID
            WHERE ri.ReturnID IN ($inReturn)
        ");
        $stmtRetItems->execute(array_values($returnIds));
        while ($row = $stmtRetItems->fetch(PDO::FETCH_ASSOC)) {
            $returnItems[$row['ReturnID']][] = $row;
        }

        $stmtRetImgs = $pdo->prepare("
            SELECT img.ImageURL, ri.ReturnID 
            FROM Return_Images img
            JOIN Return_Items ri ON img.ReturnItemID = ri.ReturnItemID
            WHERE ri.ReturnID IN ($inReturn)
        ");
        $stmtRetImgs->execute(array_values($returnIds));
        while ($row = $stmtRetImgs->fetch(PDO::FETCH_ASSOC)) {
            $returnImages[$row['ReturnID']][] = $row['ImageURL'];
        }
    }
}
?>

<div class="account-card mb-3" data-page-id="admin-orders">
    <h2 class="account-card-title">Quản lý Đơn hàng</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $msg_type; ?>"><?php echo h($message); ?></div>
    <?php endif; ?>

    <form method="GET" class="row g-2 mb-3 align-items-center" id="filterForm">
        <input type="hidden" name="tab" value="orders">
        
        <div class="col-md-auto">
            <label class="form-label fw-bold m-0">Mã đơn:</label>
        </div>
        <div class="col-md-2">
            <input type="text" name="search_id" class="form-control" placeholder="Nhập ID..." value="<?php echo h($searchId); ?>">
        </div>

        <div class="col-md-auto">
            <label class="form-label fw-bold m-0">Trạng thái:</label>
        </div>
        
        <div class="col-md-3">
            <select name="status" id="mainStatus" class="form-select">
                <?php foreach ($orderStatuses as $key => $val): ?>
                    <option value="<?php echo $key; ?>" <?php echo $filter === $key ? 'selected' : ''; ?>>
                        <?php echo $val; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3 account-orders-hidden" id="subStatusContainer">
            <select name="return_filter" class="form-select">
                <option value="">-- Chọn tiến độ trả hàng --</option>
                <?php 
                $subFilter = $_GET['return_filter'] ?? '';
                foreach ($returnStatuses as $key => $val): ?>
                    <option value="<?php echo $key; ?>" <?php echo $subFilter === $key ? 'selected' : ''; ?>>
                        <?php echo $val; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary btn-sm">Lọc</button>
        </div>
    </form>

    <?php if (empty($orders)): ?>
        <div class="text-center p-4 text-muted border rounded bg-light">Không tìm thấy đơn hàng nào.</div>
    <?php else: ?>
        
        <div class="accordion" id="ordersAccordion">
            <?php foreach ($orders as $index => $o): ?>
                <?php 
                    $items = $orderItems[$o['OrderID']] ?? [];
                    $isReturned = !empty($o['ReturnID']);
                    $isCancelled = ($o['Status'] === 'Bị hủy');
                    $finalTotal = $o['TotalAmountAfterVoucher'] > 0 ? $o['TotalAmountAfterVoucher'] : $o['TotalAmount'];
                    
                    $collapseId = "collapseOrder" . $o['OrderID'];
                    $headingId = "headingOrder" . $o['OrderID'];
                    
                    // Màu sắc trạng thái
                    $badgeClass = 'bg-primary';
                    if($o['Status']=='Bị hủy') $badgeClass='bg-danger';
                    if($o['Status']=='Đã hoàn tiền') $badgeClass='bg-info text-dark';
                    if($o['Status']=='Đã giao' || $o['Status']=='Đã nhận') $badgeClass='bg-success';
                    if($o['Status']=='Trả hàng') $badgeClass='bg-warning text-dark';
                ?>

                <div class="accordion-item mb-2 border rounded shadow-sm">
                    <h2 class="accordion-header" id="<?php echo $headingId; ?>">
                        <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapseId; ?>">
                            <div class="d-flex w-100 justify-content-between align-items-center me-3">
                                <div>
                                    <strong>#<?php echo h($o['OrderID']); ?></strong>
                                    <span class="ms-2 badge <?php echo $badgeClass; ?>"><?php echo h($o['Status']); ?></span>
                                    <small class="text-muted ms-2"> 
                                        <i class="far fa-clock"></i> Ngày đặt: <?php echo date('d/m/Y H:i', strtotime($o['CreatedDate'])); ?>
                                        <?php if (!empty($o['DateReceived'])): ?>
                                            <span class="mx-1">-</span> 
                                            <i class="fas fa-check-circle text-success"></i> 
                                            Nhận: <?php echo date('d/m/Y H:i', strtotime($o['DateReceived'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-primary"><?php echo number_format($finalTotal, 0, ',', '.'); ?> đ</strong>
                                    <br><small class="text-muted"><?php echo h($o['CustomerName']); ?></small>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $headingId; ?>" data-bs-parent="#ordersAccordion">
                        <div class="accordion-body bg-light">
                            <div class="row">
                                <div class="col-md-4 border-end">
                                    <h6 class="text-uppercase text-muted small fw-bold">Khách hàng</h6>
                                    <p class="mb-1">
                                        <strong><?php echo h($o['CustomerName']); ?></strong>
                                        <span class="text-muted small ms-1">(#<?php echo h($o['UserID'] ?? 'Guest'); ?>)</span> 
                                    </p>
                                    <p class="mb-1"><i class="fas fa-phone small"></i> <?php echo h($o['CustomerPhone']); ?></p>
                                    <p class="mb-3"><i class="fas fa-envelope small"></i> <?php echo h($o['CustomerEmail']); ?></p>

                                    <h6 class="text-uppercase text-muted small fw-bold">Giao nhận</h6>
                                    <p class="mb-1">
                                        <?php 
                                            $addrParts = array_filter([$o['ShippingNumber'], $o['ShippingStreet'], $o['ShippingWard'], $o['ShippingDistrict'], $o['ShippingCity']]);
                                            echo h(implode(', ', $addrParts)); 
                                        ?>
                                    </p>
                                    <p class="mb-1"><strong>ĐVVC:</strong> <?php echo h($o['CarrierName'] ?? '-'); ?></p>
                                    <?php if($o['ShippingID']): ?>
                                        <p class="mb-1"><strong>Mã vận đơn:</strong> <span class="badge bg-secondary"><?php echo h($o['ShippingID']); ?></span></p>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-5 border-end">
                                    <h6 class="text-uppercase text-muted small fw-bold">Sản phẩm</h6>
                                    <div class="list-group list-group-flush mb-3">
                                        <?php foreach ($items as $it): ?>
                                            <div class="list-group-item bg-transparent px-0 py-2 d-flex">
                                                <div class="me-3 border rounded overflow-hidden d-flex align-items-center justify-content-center bg-white admin-orders-product-image-box">
                                                    <?php if (!empty($it['Image'])): ?>
                                                        <img src="data:image/jpeg;base64,<?php echo base64_encode($it['Image']); ?>" alt="<?php echo h($it['ProductName']); ?>" class="admin-orders-product-image">
                                                    <?php else: ?>
                                                        <i class="fas fa-image text-muted"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold mb-1"><?php echo h($it['ProductName']); ?></div>
                                                    <div class="small text-muted mb-1">
                                                        <?php if (!empty($it['Format'])): ?><span>Định dạng: <?php echo h($it['Format']); ?></span><?php endif; ?>
                                                        <?php if (!empty($it['ISBN'])): ?><span class="mx-1">|</span><span>ISBN: <?php echo h($it['ISBN']); ?></span><?php endif; ?>
                                                    </div>
                                                    <div class="small">
                                                        SL: <strong><?php echo $it['Quantity']; ?></strong> x 
                                                        <?php 
                                                        if (!empty($it['DiscountedPrice']) && $it['DiscountedPrice'] < $it['UnitPrice']) {
                                                            echo '<strong class="text-danger">' . number_format($it['DiscountedPrice'], 0, ',', '.') . ' đ</strong>';
                                                            echo ' <del class="text-muted ms-1 admin-orders-old-price">' . number_format($it['UnitPrice'], 0, ',', '.') . ' đ</del>';
                                                        } else {
                                                            echo number_format($it['UnitPrice'], 0, ',', '.') . ' đ';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between small">
                                        <span>Tạm tính:</span>
                                        <span><?php echo number_format($o['TotalAmount'], 0, ',', '.'); ?> đ</span>
                                    </div>
                                    <div class="d-flex justify-content-between small">
                                        <span>Phí vận chuyển:</span>
                                        <span><?php echo !empty($o['ShippingPrice']) ? number_format($o['ShippingPrice'], 0, ',', '.') . ' đ' : '0 đ'; ?></span>
                                    </div>
                                    <?php if (!empty($o['DiscountValue'])): ?>
                                        <div class="d-flex justify-content-between small text-danger">
                                            <span>Voucher:</span>
                                            <span>
                                                -<?php 
                                                    if (strcasecmp($o['DiscountType'] ?? '', 'PERCENT') == 0 || ($o['DiscountType'] ?? '') == '%') {
                                                        echo number_format($o['DiscountValue'], 0) . '%';
                                                    } else {
                                                        echo number_format($o['DiscountValue'], 0, ',', '.') . ' đ';
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1">
                                        <span>Tổng cộng:</span>
                                        <span class="text-primary"><?php echo number_format($finalTotal, 0, ',', '.'); ?> đ</span>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    
                                    <?php 
                                    // 1. ĐƠN BỊ HỦY -> HIỆN THÔNG BÁO, KHÔNG CÓ NÚT
                                    if ($isCancelled): ?>
                                        <div class="alert alert-danger p-2 small mt-2">
                                            <strong><i class="fas fa-ban"></i> Đơn đã hủy</strong><br>
                                            <?php echo h($o['Note'] ?? 'N/A'); ?>
                                        </div>

                                    <?php 
                                    // 2. ĐƠN ĐÃ HOÀN TIỀN -> HIỆN THÔNG BÁO, KHÔNG CÓ NÚT
                                    elseif ($o['Status'] === 'Đã hoàn tiền'): ?>
                                        <div class="alert alert-info p-2 small mt-2">
                                            <strong><i class="fas fa-check-double"></i> Đã hoàn tiền</strong><br>
                                            <?php if (!empty($o['TotalRefund'])): ?>
                                                Số tiền đã hoàn: <span class="text-danger fw-bold"><?php echo number_format($o['TotalRefund'], 0, ',', '.'); ?> đ</span>
                                            <?php endif; ?>
                                        </div>

                                    <?php 
                                    // 3. ĐƠN TRẢ HÀNG -> HIỆN FORM CẬP NHẬT RETURN STATUS
                                    elseif ($isReturned): 
                                        $retItems = $returnItems[$o['ReturnID']] ?? [];
                                        $retImgs = $returnImages[$o['ReturnID']] ?? [];
                                    ?>
                                        <div class="card border-warning mt-2">
                                            <div class="card-header bg-warning bg-opacity-25 py-1 px-2">
                                                <small class="fw-bold text-dark"><i class="fas fa-undo"></i> Xử lý Trả hàng</small>
                                            </div>
                                            <div class="card-body p-2 small">
                                                <div class="mb-2">
                                                    <strong>Sản phẩm:</strong>
                                                    <ul class="ps-3 mb-1">
                                                        <?php foreach ($retItems as $ri): ?>
                                                            <li><?php echo h($ri['ProductName']); ?> <br><em class="text-muted"><?php echo h($ri['Reason']); ?></em></li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                                <div class="mb-2"><strong>Hoàn:</strong> <span class="text-danger fw-bold"><?php echo number_format($o['TotalRefund'], 0, ',', '.'); ?> đ</span></div>
                                                
                                                <?php if(!empty($retImgs)): ?>
                                                    <div class="mb-2">
                                                        <strong>Ảnh:</strong><br>
                                                        <?php foreach($retImgs as $img): ?>
                                                            <a href="<?php echo h($img); ?>" target="_blank"><img src="<?php echo h($img); ?>" width="35" height="35" class="border rounded me-1"></a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <form method="POST" class="mt-2 pt-2 border-top">
                                                    <input type="hidden" name="action" value="update_return_status">
                                                    <input type="hidden" name="return_id" value="<?php echo h($o['ReturnID']); ?>">
                                                    <div class="mb-1">
                                                        <select name="return_status" class="form-select form-select-sm border-warning">
                                                            <?php foreach ($allowedReturnUpdateStatuses as $key => $val): ?>
                                                                <option value="<?php echo $key; ?>" <?php echo $o['ReturnStatus'] === $key ? 'selected' : ''; ?>><?php echo $val; ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <button class="btn btn-warning btn-sm w-100">Duyệt trả hàng</button>
                                                </form>
                                            </div>
                                        </div>

                                    <?php 
                                    // 4. ĐƠN THƯỜNG -> HIỆN FORM CẬP NHẬT ORDER STATUS (Chỉ hiện nếu chưa hoàn thành/hủy/trả)
                                    elseif ($o['Status'] !== 'Đã nhận'): // Đã nhận thì hết quy trình (trừ khi khách bấm trả hàng)
                                    ?>
                                        <h6 class="text-uppercase text-muted small fw-bold">Cập nhật trạng thái</h6>
                                        <form method="POST" class="mb-3">
                                            <input type="hidden" name="action" value="update_order_status">
                                            <input type="hidden" name="order_id" value="<?php echo h($o['OrderID']); ?>">
                                            <div class="mb-2">
                                                <select name="status" class="form-select form-select-sm">
                                                    <?php foreach ($allowedUpdateStatuses as $key => $val): ?>
                                                        <option value="<?php echo $key; ?>" <?php echo $o['Status'] === $key ? 'selected' : ''; ?>>
                                                            <?php echo $val; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <button class="btn btn-primary btn-sm w-100">Cập nhật đơn</button>
                                        </form>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($orders) && $totalPages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                
                <?php
                // Tạo query string để giữ lại các bộ lọc hiện tại (status, search_id, return_filter)
                $queryParams = $_GET;
                unset($queryParams['page']); // Xóa page hiện tại để thay bằng page mới
                $queryString = http_build_query($queryParams);
                ?>

                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?<?php echo $queryString; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="text-center text-muted small mt-2">
            Hiển thị trang <?php echo $page; ?> / <?php echo $totalPages; ?> (Tổng <?php echo $totalOrders; ?> đơn hàng)
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="moonlit.js"></script>
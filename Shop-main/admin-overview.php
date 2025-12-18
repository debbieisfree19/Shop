<?php
$orderStatuses = [
    'Chờ xác nhận' => 'Chờ xác nhận',
    'Đã xác nhận'  => 'Đã xác nhận / Chờ lấy hàng',
    'Đang giao'    => 'Đang giao',
    'Đã giao'      => 'Đã giao',
    'Trả hàng'     => 'Trả hàng / Hoàn tiền',
    'Bị hủy'       => 'Đã hủy',
];

$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(TotalAmount),0) revenue,
        COUNT(*) orders
    FROM `Order`
    WHERE Status != 'Bị hủy'
");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$totalRevenue = (float)$row['revenue'];
$totalOrders  = (int)$row['orders'];
$avgOrder     = $totalOrders ? $totalRevenue / $totalOrders : 0;

$orderStatusCounts = [];
$stmt = $pdo->query("
    SELECT Status, COUNT(*) cnt
    FROM `Order`
    GROUP BY Status
");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $orderStatusCounts[$r['Status']] = (int)$r['cnt'];
}

$latestProducts = $pdo->query("
    SELECT ProductID, ProductName, Price, CreatedDate
    FROM Product
    ORDER BY CreatedDate DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$totalCustomers = (int)$pdo->query("
    SELECT COUNT(*) FROM User_Account where Role ='customer'
")->fetchColumn();
?>
<h2 class="account-section-title">Overview</h2>
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
                <?php echo (int) $totalOrders; ?>
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
                <?php echo (int) $totalCustomers; ?>
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

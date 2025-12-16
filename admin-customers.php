<?php
require_once 'db_connect.php';

// ================= LOAD VOUCHER CỦA USER =================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'load_voucher') {

    $userId = $_GET['user_id'] ?? '';
    if ($userId === '') {
        echo '<p class="text-danger">Thiếu UserID</p>';
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT v.VoucherName, v.Code, v.DiscountType, v.DiscountValue, v.EndDate, uv.DateReceived
        FROM User_Voucher uv
        JOIN Voucher v ON uv.VoucherID = v.VoucherID
        WHERE uv.UserID = ?
        ORDER BY uv.DateReceived DESC
    ");
    $stmt->execute([$userId]);
    $vouchers = $stmt->fetchAll();

    if (empty($vouchers)) {
        echo "<p class='text-muted'>Khách chưa có voucher nào.</p>";
        exit;
    }

    echo "<ul class='list-group'>";
    foreach ($vouchers as $v) {
        echo "
        <li class='list-group-item'>
            <strong>{$v['VoucherName']}</strong>
            <div>Mã: {$v['Code']}</div>
            <div>Nhận: " . date('d/m/Y', strtotime($v['DateReceived'])) . "</div>
            <div>Hết hạn: " . ($v['EndDate'] ? date('d/m/Y', strtotime($v['EndDate'])) : 'Không') . "</div>
        </li>";
    }
    echo "</ul>";
    exit;
}
// ================= GÁN VOUCHER =================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['ajax'])
    && $_POST['ajax'] === 'assign_voucher'
) {
    $uid = $_POST['user_id'] ?? '';
    $vid = $_POST['voucher_id'] ?? '';

    if ($uid === '' || $vid === '') {
        echo json_encode(['success' => false, 'message' => 'Thiếu dữ liệu']);
        exit;
    }

    $check = $pdo->prepare("
        SELECT COUNT(*) FROM User_Voucher
        WHERE UserID = ? AND VoucherID = ?
    ");
    $check->execute([$uid, $vid]);

    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Khách đã có voucher này']);
        exit;
    }

    $newId = generateVoucherId($pdo);

    $pdo->prepare("
        INSERT INTO User_Voucher (ID, UserID, VoucherID, DateReceived)
        VALUES (?, ?, ?, NOW())
    ")->execute([$newId, $uid, $vid]);

    $pdo->prepare("
        UPDATE Voucher SET UsedCount = UsedCount + 1
        WHERE VoucherID = ?
    ")->execute([$vid]);

    echo json_encode(['success' => true]);
    exit;
}


// Lấy danh sách customer + điểm + rank
$sql = "
SELECT
    u.UserID,
    u.Username,
    u.FullName,
    u.Email,
    u.Phone,
    u.Points,

    COALESCE(SUM(o.TotalAmount), 0)
    - COALESCE(SUM(CASE WHEN ro.Status = 'Chấp thuận' THEN ro.TotalRefund ELSE 0 END), 0)
        AS TotalSpent,

    CASE
        WHEN (COALESCE(SUM(o.TotalAmount), 0)
              - COALESCE(SUM(CASE WHEN ro.Status = 'Chấp thuận' THEN ro.TotalRefund ELSE 0 END), 0)) < 100000
            THEN 'Member'
        WHEN (COALESCE(SUM(o.TotalAmount), 0)
              - COALESCE(SUM(CASE WHEN ro.Status = 'Chấp thuận' THEN ro.TotalRefund ELSE 0 END), 0)) < 200000
            THEN 'Bronze'
        WHEN (COALESCE(SUM(o.TotalAmount), 0)
              - COALESCE(SUM(CASE WHEN ro.Status = 'Chấp thuận' THEN ro.TotalRefund ELSE 0 END), 0)) < 300000
            THEN 'Silver'
        WHEN (COALESCE(SUM(o.TotalAmount), 0)
              - COALESCE(SUM(CASE WHEN ro.Status = 'Chấp thuận' THEN ro.TotalRefund ELSE 0 END), 0)) < 400000
            THEN 'Gold'
        ELSE 'Platinum'
    END AS RankName

FROM User_Account u
LEFT JOIN `Order` o
       ON u.UserID = o.UserID
      AND o.Status IN ('Đã nhận', 'Trả hàng')
LEFT JOIN Returns_Order ro
       ON o.OrderID = ro.OrderID

WHERE u.Role = 'Customer'

GROUP BY
    u.UserID,
    u.Username,
    u.FullName,
    u.Email,
    u.Phone,
    u.Points

ORDER BY TotalSpent DESC
";

$stmt = $pdo->query($sql);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="account-card">
        <h2 class="account-card-title">Danh sách khách hàng</h2>

        <?php if (empty($customers)): ?>
            <p>Chưa có khách hàng nào.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>UserID</th>
                            <th>Khách hàng</th>
                            <th>Email</th>
                            <th>Điện thoại</th>
                            <th>Điểm</th>
                            <th>Tổng chi</th>
                            <th>Hạng</th>
                            <th>Voucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['UserID']) ?></td>
                                <td><?= htmlspecialchars($c['Username']) ?></td>
                                <td><?= htmlspecialchars($c['Email']) ?></td>
                                <td><?= htmlspecialchars($c['Phone']) ?></td>
                                <td><?= $c['Points'] ?></td>
                                <td><?= number_format($c['TotalSpent'], 0, ',', '.') ?> đ</td>
                                <td>
                                    <span class="badge badge-rank <?= strtolower($c['RankName']) ?>">
                                        <?= $c['RankName'] ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#voucherModal" data-userid="<?= $c['UserID'] ?>"
                                        data-username="<?= htmlspecialchars($c['Username']) ?>">
                                        🎟 Xem / Gán
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <div class="modal fade" id="voucherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        Voucher của <span id="modalUsername"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <!-- danh sách voucher -->
                    <div id="voucherList">
                        <p class="text-muted">Đang tải...</p>
                    </div>

                    <hr>

                    <!-- thêm voucher -->
                    <form id="addVoucherForm">
                        <input type="hidden" name="user_id" id="voucherUserId">

                        <label class="form-label">Chọn voucher để gán</label>
                        <select name="voucher_id" class="form-select" required>
                            <option value="">-- Chọn voucher --</option>
                            <?php
                            $vStmt = $pdo->query("
                    SELECT VoucherID, VoucherName 
                    FROM Voucher 
                    WHERE Status = 1
                    ORDER BY VoucherName
                ");
                            foreach ($vStmt as $v):
                                ?>
                                <option value="<?= $v['VoucherID'] ?>">
                                    <?= htmlspecialchars($v['VoucherName']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button class="btn btn-success mt-3">
                            ➕ Gán voucher
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        const voucherModal = document.getElementById('voucherModal');

        voucherModal.addEventListener('show.bs.modal', function (event) {

            const button = event.relatedTarget; // nút bấm
            const userId = button.getAttribute('data-userid');
            const username = button.getAttribute('data-username');

            // set tên user
            document.getElementById('modalUsername').innerText = username;
            document.getElementById('voucherUserId').value = userId;

            // load voucher
            fetch('admin-dashboard.php?tab=customers&ajax=load_voucher&user_id=' + userId)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('voucherList').innerHTML = html;
                });
        });

        // gán voucher
        document.getElementById('addVoucherForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('ajax', 'assign_voucher');

            fetch('admin-dashboard.php?tab=customers', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert(data.message);
                        return;
                    }

                    // reload danh sách voucher
                    const uid = document.getElementById('voucherUserId').value;
                    fetch('admin-dashboard.php?tab=customers&ajax=load_voucher&user_id=' + uid)
                        .then(res => res.text())
                        .then(html => {
                            document.getElementById('voucherList').innerHTML = html;
                        });
                });
        });

    </script>
</body>
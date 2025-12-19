<?php
/**
 * MOONLIT STORE - CHECKOUT PAGE (DB CART + VOUCHER + CARRIER)
 * - Load cart từ DB: Cart + Cart_Items
 * - Chọn Carrier (CarrierID) -> cộng ShippingPrice
 * - Áp voucher bằng Code (bảng Voucher)
 * - Lưu Order/Order_Items/Shipping_Order/User_Voucher thật khi đặt hàng
 */

session_start();
require_once 'db_connect.php';

/* =========================
   AUTH / USER
========================= */
$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = $userId !== null;

if (!$userId) {
    header('Location: auth-login.php');
    exit;
}

$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = 'checkout.php';

if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

/* =========================
   LOAD CART ITEMS FROM DB
========================= */
$products   = [];
$error_msg  = '';
$subTotal   = 0;
$totalItems = 0;

try {
    $sql = "
        SELECT
            ci.CartItemID,
            ci.Quantity,
            ci.UnitPrice,
            ci.DiscountedPrice,
            ci.TotalPrice,

            s.SKUID,
            s.Format,

            p.ProductID,
            p.ProductName,
            (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage
        FROM Cart c
        JOIN Cart_Items ci ON c.CartID = ci.CartID
        JOIN SKU s ON ci.SKU_ID = s.SKUID
        JOIN Product p ON s.ProductID = p.ProductID
        WHERE c.UserID = :uid
        ORDER BY ci.CartItemID DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $p) {
        $subTotal   += (float)$p['TotalPrice'];
        $totalItems += (int)$p['Quantity'];
    }
} catch (Exception $e) {
    $error_msg = 'Không thể tải dữ liệu giỏ hàng: ' . $e->getMessage();
}

/* =========================
   LOAD CARRIERS
========================= */
$carriers = [];
try {
    $cStmt = $pdo->query("
        SELECT CarrierID, CarrierName, ShippingPrice
        FROM Carrier
        ORDER BY ShippingPrice ASC, CarrierName ASC
    ");
    $carriers = $cStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $carriers = [];
}

/* =========================
   HELPERS
========================= */
function now_in_range(?string $start, ?string $end): bool {
    $now = time();
    if ($start) {
        $s = strtotime($start);
        if ($s !== false && $now < $s) return false;
    }
    if ($end) {
        $e = strtotime($end);
        if ($e !== false && $now > $e) return false;
    }
    return true;
}

function gen_id6(): string {
    return strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/* =========================
   READ USER INPUT (GET/POST)
========================= */
$form_errors = [];
$success_msg = '';

$selectedCarrierId = $_POST['carrier_id'] ?? ($_GET['carrier_id'] ?? '');
$voucherCodeInput  = trim($_POST['voucher_code'] ?? ($_GET['voucher_code'] ?? ''));

$shippingFee = 0.0;
$selectedCarrier = null;

if ($selectedCarrierId !== '' && !empty($carriers)) {
    foreach ($carriers as $c) {
        if ($c['CarrierID'] === $selectedCarrierId) {
            $selectedCarrier = $c;
            $shippingFee = (float)$c['ShippingPrice'];
            break;
        }
    }
}
if (!$selectedCarrier && !empty($carriers)) {
    $selectedCarrier = $carriers[0];
    $selectedCarrierId = $carriers[0]['CarrierID'];
    $shippingFee = (float)$carriers[0]['ShippingPrice'];
}

/* =========================
   APPLY VOUCHER (by Code)
========================= */
$voucher = null;
$voucherError = '';
$discountAmount = 0.0;

if ($voucherCodeInput !== '' && $subTotal > 0) {
    try {
        $vSql = "
            SELECT
                VoucherID, VoucherName, Code, Description,
                DiscountType, DiscountValue, MinOrder, MaxDiscount,
                StartDate, EndDate, UsageLimit, UsedCount, Status
            FROM Voucher
            WHERE Code = :code
            LIMIT 1
        ";
        $vStmt = $pdo->prepare($vSql);
        $vStmt->execute([':code' => $voucherCodeInput]);
        $voucher = $vStmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            $voucherError = 'Mã voucher không tồn tại.';
        } else if ((int)$voucher['Status'] !== 1) {
            $voucherError = 'Voucher đang bị tắt.';
        } else if (!now_in_range($voucher['StartDate'] ?? null, $voucher['EndDate'] ?? null)) {
            $voucherError = 'Voucher đã hết hạn hoặc chưa tới thời gian áp dụng.';
        } else if ($voucher['UsageLimit'] !== null && $voucher['UsedCount'] !== null
                   && (int)$voucher['UsedCount'] >= (int)$voucher['UsageLimit']) {
            $voucherError = 'Voucher đã hết lượt sử dụng.';
        } else if ($voucher['MinOrder'] !== null && (float)$subTotal < (float)$voucher['MinOrder']) {
            $voucherError = 'Đơn hàng chưa đạt giá trị tối thiểu để dùng voucher.';
        } else {
            $type  = strtolower(trim($voucher['DiscountType'] ?? ''));
            $value = (float)($voucher['DiscountValue'] ?? 0);

            if ($type === 'percent' || $type === 'percentage') {
                $discountAmount = $subTotal * ($value / 100.0);
            } else {
                $discountAmount = $value;
            }

            if ($voucher['MaxDiscount'] !== null && (float)$voucher['MaxDiscount'] > 0) {
                $discountAmount = min($discountAmount, (float)$voucher['MaxDiscount']);
            }

            $discountAmount = max(0, min($discountAmount, $subTotal));
        }
    } catch (Exception $e) {
        $voucherError = 'Không thể áp voucher: ' . $e->getMessage();
    }
}

$totalAfterVoucher = max(0, $subTotal - $discountAmount);
$grandTotal = $totalAfterVoucher + $shippingFee;

/* =========================
   SUBMIT ORDER (SAVE REAL)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $note      = trim($_POST['note'] ?? '');
    $payment   = trim($_POST['payment_method'] ?? 'cod');

    if ($full_name === '') $form_errors[] = 'Vui lòng nhập họ và tên.';
    if ($phone === '')     $form_errors[] = 'Vui lòng nhập số điện thoại.';
    if ($address === '')   $form_errors[] = 'Vui lòng nhập địa chỉ nhận hàng.';
    if (empty($products))  $form_errors[] = 'Giỏ hàng trống, không thể đặt hàng.';
    if (!$selectedCarrierId) $form_errors[] = 'Vui lòng chọn đơn vị vận chuyển.';

    if ($voucherCodeInput !== '' && $voucherError !== '') {
        $form_errors[] = $voucherError;
    }

    if (empty($form_errors)) {
        try {
            $pdo->beginTransaction();

            // 0) re-check cart still exists (chống bấm 2 lần / tab khác xóa)
            $cartCheck = $pdo->prepare("
                SELECT COUNT(*) 
                FROM Cart c 
                JOIN Cart_Items ci ON c.CartID = ci.CartID
                WHERE c.UserID = :uid
            ");
            $cartCheck->execute([':uid' => $userId]);
            $cartCount = (int)$cartCheck->fetchColumn();
            if ($cartCount <= 0) {
                throw new Exception('Giỏ hàng đã trống (có thể bạn vừa đặt ở tab khác).');
            }

            // 1) insert Order
            $orderId = gen_id6();

            // Bảng Order bắt buộc các field shipping -> tạm nhét address vào ShippingStreet
            $shippingCity     = '';
            $shippingDistrict = '';
            $shippingWard     = '';
            $shippingStreet   = $address;
            $shippingNumber   = '';

            $insOrder = $pdo->prepare("
                INSERT INTO `Order` (
                    OrderID, UserID,
                    TotalAmount, TotalAmountAfterVoucher,
                    Status, PaymentMethod,
                    ShippingCity, ShippingDistrict, ShippingWard, ShippingStreet, ShippingNumber,
                    CreatedDate, DateReceived, Note
                ) VALUES (
                    :oid, :uid,
                    :total, :afterVoucher,
                    :status, :pay,
                    :city, :district, :ward, :street, :num,
                    NOW(), NULL, :note
                )
            ");
            $insOrder->execute([
                ':oid'          => $orderId,
                ':uid'          => $userId,
                ':total'        => $subTotal,
                ':afterVoucher' => $totalAfterVoucher,
                ':status'       => 'Pending',
                ':pay'          => $payment,
                ':city'         => $shippingCity,
                ':district'     => $shippingDistrict,
                ':ward'         => $shippingWard,
                ':street'       => $shippingStreet,
                ':num'          => $shippingNumber,
                ':note'         => $note,
            ]);

            // 2) insert Order_Items (copy từ cart)
            $insItem = $pdo->prepare("
                INSERT INTO Order_Items (OrderID, SKU_ID, Quantity, UnitPrice, DiscountedPrice, TotalPrice)
                VALUES (:oid, :skuid, :qty, :u, :d, :t)
            ");

            foreach ($products as $p) {
                $insItem->execute([
                    ':oid'   => $orderId,
                    ':skuid' => $p['SKUID'],
                    ':qty'   => (int)$p['Quantity'],
                    ':u'     => (float)$p['UnitPrice'],
                    ':d'     => (float)$p['DiscountedPrice'],
                    ':t'     => (float)$p['TotalPrice'],
                ]);
            }

            // 3) insert Shipping_Order (gắn Carrier)
            $shippingId = gen_id6();
            $insShip = $pdo->prepare("
                INSERT INTO Shipping_Order (
                    ShippingID, OrderID, ReturnID, CarrierID,
                    TrackingNumber, Status, ShippedDate, DeliveredDate
                ) VALUES (
                    :sid, :oid, NULL, :cid,
                    NULL, :status, NULL, NULL
                )
            ");
            $insShip->execute([
                ':sid'    => $shippingId,
                ':oid'    => $orderId,
                ':cid'    => $selectedCarrierId,
                ':status' => 'Pending'
            ]);

            // 4) nếu có voucher hợp lệ -> lưu User_Voucher + tăng UsedCount
            if ($voucher && $voucherError === '' && !empty($voucher['VoucherID'])) {
                $uvId = gen_id6();
                $pdo->prepare("
                    INSERT INTO User_Voucher (ID, UserID, VoucherID, OrderID, DateReceived)
                    VALUES (:id, :uid, :vid, :oid, NOW())
                ")->execute([
                    ':id'  => $uvId,
                    ':uid' => $userId,
                    ':vid' => $voucher['VoucherID'],
                    ':oid' => $orderId
                ]);

                $pdo->prepare("
                    UPDATE Voucher
                    SET UsedCount = IFNULL(UsedCount, 0) + 1
                    WHERE VoucherID = :vid
                ")->execute([':vid' => $voucher['VoucherID']]);
            }

            // 5) clear cart items
            $pdo->prepare("
                DELETE ci FROM Cart_Items ci
                JOIN Cart c ON ci.CartID = c.CartID
                WHERE c.UserID = :uid
            ")->execute([':uid' => $userId]);

            $pdo->commit();

            $success_msg = "Đặt hàng thành công! Mã đơn: <strong>" . htmlspecialchars($orderId) . "</strong> ✨";

            // reset view
            $products = [];
            $subTotal = 0;
            $totalItems = 0;
            $discountAmount = 0;
            $shippingFee = 0;
            $totalAfterVoucher = 0;
            $grandTotal = 0;
            $voucherCodeInput = '';
            $selectedCarrierId = '';

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $form_errors[] = 'Có lỗi khi xử lý đặt hàng: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán - Moonlit Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body">

<header class="account-header site-header">
    <div class="container header-inner">
        <div class="header-left">
            <a href="index.php" class="logo-link header-logo">
                <img src="img/image.png" alt="Moonlit logo" class="logo-img">
            </a>

            <nav class="header-menu">
                <a href="index.php" class="header-menu-link <?php echo nav_active('index.php', $currentPage); ?>">Trang chủ</a>
                <a href="shop.php" class="header-menu-link <?php echo nav_active('shop.php', $currentPage); ?>">Cửa hàng</a>
                <a href="aboutus.php" class="header-menu-link <?php echo nav_active('aboutus.php', $currentPage); ?>">Về chúng tôi</a>
                <a href="return-policy.php" class="header-menu-link <?php echo nav_active('return-policy.php', $currentPage); ?>">Chính sách</a>
            </nav>
        </div>

        <div class="header-right">
            <form method="GET" action="shop.php" class="header-search-form">
                <input type="text" name="q" class="account-input header-search-input" placeholder="Tìm sách...">
                <button type="submit" class="account-btn-save header-search-btn">Tìm</button>
            </form>

            <a href="cart.php" class="account-btn-secondary header-cart-btn">Giỏ hàng</a>

            <div class="header-account">
                <span class="account-username">
                    Xin chào, <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
                </span>
                <div class="header-account-actions">
                    <a href="account-index.php" class="account-btn-secondary header-account-btn">Tài khoản</a>
                    <a href="logout.php" class="account-btn-secondary header-account-btn">Đăng xuất</a>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="account-main checkout-main">
    <div class="container">

        <section class="checkout-header">
            <h1 class="account-section-title">Thanh toán</h1>
            <p class="account-section-subtitle">Điền thông tin nhận hàng, chọn vận chuyển, áp voucher và kiểm tra lại đơn sách nha ✨</p>
        </section>

        <?php if (!empty($error_msg)): ?>
            <div class="account-alert account-alert-error"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <?php if (!empty($form_errors)): ?>
            <div class="account-alert account-alert-error">
                <?php foreach ($form_errors as $err): ?>
                    <div><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_msg)): ?>
            <div class="account-alert account-alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
            <div class="account-card cart-empty-card">
                <p class="account-empty-text">
                    Giỏ hàng của bạn đang trống hoặc đơn đã được đặt.
                    Hãy quay lại <a href="shop.php" class="auth-link">Cửa hàng Moonlit</a> để chọn thêm sách nhé!
                </p>
            </div>
        <?php else: ?>

            <div class="checkout-layout">

                <!-- LEFT: FORM -->
                <section class="checkout-form-section">
                    <div class="account-card checkout-form-card">
                        <h2 class="checkout-section-title">Thông tin nhận hàng</h2>

                        <form method="POST" class="checkout-form">
                            <div class="checkout-form-grid">
                                <div class="checkout-field">
                                    <label for="full_name" class="account-label">Họ và tên *</label>
                                    <input type="text" id="full_name" name="full_name" class="account-input"
                                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? $currentUsername); ?>" required>
                                </div>

                                <div class="checkout-field">
                                    <label for="email" class="account-label">Email</label>
                                    <input type="email" id="email" name="email" class="account-input"
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>

                                <div class="checkout-field">
                                    <label for="phone" class="account-label">Số điện thoại *</label>
                                    <input type="text" id="phone" name="phone" class="account-input"
                                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                                </div>

                                <div class="checkout-field checkout-field-full">
                                    <label for="address" class="account-label">Địa chỉ nhận hàng *</label>
                                    <textarea id="address" name="address" class="account-input checkout-textarea" rows="3" required><?php
                                        echo htmlspecialchars($_POST['address'] ?? '');
                                    ?></textarea>
                                </div>

                                <div class="checkout-field checkout-field-full">
                                    <label for="note" class="account-label">Ghi chú cho đơn hàng</label>
                                    <textarea id="note" name="note" class="account-input checkout-textarea" rows="3"><?php
                                        echo htmlspecialchars($_POST['note'] ?? '');
                                    ?></textarea>
                                </div>

                                <!-- SHIPPING -->
                                <div class="checkout-field checkout-field-full">
                                    <label class="account-label mb-1">Đơn vị vận chuyển</label>
                                    <?php if (empty($carriers)): ?>
                                        <div class="small text-secondary">Chưa có dữ liệu Carrier. Thêm vài hãng ship trong bảng Carrier nha.</div>
                                        <input type="hidden" name="carrier_id" value="">
                                    <?php else: ?>
                                        <select name="carrier_id" class="account-input" required>
                                            <?php foreach ($carriers as $c): ?>
                                                <option value="<?php echo htmlspecialchars($c['CarrierID']); ?>"
                                                    <?php echo ($selectedCarrierId === $c['CarrierID']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($c['CarrierName']) . ' — ' . number_format((float)$c['ShippingPrice'], 0, ',', '.') . ' đ'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>

                                <!-- VOUCHER -->
                                <div class="checkout-field checkout-field-full">
                                    <label class="account-label mb-1">Voucher</label>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <input type="text" name="voucher_code" class="account-input"
                                               placeholder="Nhập mã (VD: MOON10)"
                                               value="<?php echo htmlspecialchars($voucherCodeInput); ?>"
                                               style="flex: 1; min-width: 220px;">
                                        <button type="submit" name="apply_voucher" value="1" class="account-btn-secondary">
                                            Áp dụng
                                        </button>
                                    </div>
                                    <?php if ($voucherCodeInput !== ''): ?>
                                        <?php if ($voucherError !== ''): ?>
                                            <div class="small text-danger mt-1"><?php echo htmlspecialchars($voucherError); ?></div>
                                        <?php else: ?>
                                            <div class="small text-success mt-1">
                                                Đã áp voucher: <strong><?php echo htmlspecialchars($voucher['VoucherName'] ?? $voucherCodeInput); ?></strong>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- PAYMENT -->
                                <div class="checkout-field checkout-field-full">
                                    <span class="account-label">Phương thức thanh toán</span>
                                    <div class="checkout-payment-options">
                                        <label class="checkout-radio-option">
                                            <input type="radio" name="payment_method" value="cod"
                                                <?php echo (($_POST['payment_method'] ?? 'cod') === 'cod') ? 'checked' : ''; ?>>
                                            <span>Thanh toán khi nhận hàng (COD)</span>
                                        </label>
                                        <label class="checkout-radio-option">
                                            <input type="radio" name="payment_method" value="bank"
                                                <?php echo (($_POST['payment_method'] ?? '') === 'bank') ? 'checked' : ''; ?>>
                                            <span>Chuyển khoản ngân hàng</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" name="place_order" value="1" class="account-btn-save checkout-submit-btn">
                                Đặt hàng
                            </button>
                        </form>
                    </div>
                </section>

                <!-- RIGHT: SUMMARY -->
                <aside class="checkout-summary">
                    <div class="account-card cart-summary-card">
                        <h2 class="cart-summary-title">Đơn hàng của bạn</h2>

                        <div class="checkout-summary-list">
                            <?php foreach ($products as $product): ?>
                                <div class="checkout-summary-item">
                                    <div class="checkout-summary-info">
                                        <p class="checkout-summary-name"><?php echo htmlspecialchars($product['ProductName']); ?></p>
                                        <p class="checkout-summary-qty">
                                            SL: <?php echo (int)$product['Quantity']; ?>
                                            <?php if (!empty($product['Format'])): ?>
                                                (<?php echo htmlspecialchars($product['Format']); ?>)
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="checkout-summary-line-total">
                                        <?php echo number_format((float)$product['TotalPrice'], 0, ',', '.'); ?> đ
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="cart-summary-row">
                            <span>Tạm tính</span>
                            <span><?php echo number_format((float)$subTotal, 0, ',', '.'); ?> đ</span>
                        </div>

                        <div class="cart-summary-row">
                            <span>Giảm giá</span>
                            <span>-<?php echo number_format((float)$discountAmount, 0, ',', '.'); ?> đ</span>
                        </div>

                        <div class="cart-summary-row">
                            <span>Phí vận chuyển</span>
                            <span><?php echo number_format((float)$shippingFee, 0, ',', '.'); ?> đ</span>
                        </div>

                        <div class="cart-summary-row cart-summary-total">
                            <span>Tổng thanh toán</span>
                            <span><?php echo number_format((float)$grandTotal, 0, ',', '.'); ?> đ</span>
                        </div>

                        <p class="cart-note">
                            * Đã lưu Order/Order_Items/Shipping_Order. Tổng thanh toán hiển thị gồm ship, còn bảng `Order` lưu tiền hàng (trước & sau voucher).
                        </p>
                    </div>
                </aside>

            </div>

        <?php endif; ?>
    </div>
</main>

<footer class="site-footer">
    © <?php echo date('Y'); ?> Moonlit Store. All rights reserved.
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

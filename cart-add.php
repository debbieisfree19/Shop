<?php
session_start();
require_once 'db_connect.php';

// phải login mới có cart theo DB
if (!isset($_SESSION['user_id'])) {
    header('Location: auth-login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// nhận input: ưu tiên skuid (mới), fallback id (cũ)
$skuid  = trim($_GET['skuid'] ?? '');
$qty    = (int)($_GET['qty'] ?? 1);
$action = $_GET['action'] ?? 'add_to_cart';

if ($qty < 1) $qty = 1;

if ($skuid === '') {
    header('Location: shop.php');
    exit;
}

// 1) check SKU tồn tại + còn active
$skuStmt = $pdo->prepare("
    SELECT s.SKUID, s.ProductID, s.SellPrice
    FROM SKU s
    WHERE s.SKUID = :skuid AND s.Status = 1
    LIMIT 1
");
$skuStmt->execute([':skuid' => $skuid]);
$sku = $skuStmt->fetch(PDO::FETCH_ASSOC);

if (!$sku) {
    header('Location: shop.php?err=sku_not_found');
    exit;
}

// 2) lấy sale price (nếu có và đang active)
$priceStmt = $pdo->prepare("
    SELECT
      s.SellPrice AS OriginalPrice,
      CASE
        WHEN ps.DiscountedPrice IS NOT NULL
         AND ps.StartDate <= NOW()
         AND (ps.EndDate IS NULL OR ps.EndDate >= NOW())
        THEN ps.DiscountedPrice
        ELSE s.SellPrice
      END AS FinalPrice
    FROM SKU s
    LEFT JOIN PRODUCT_SALE ps ON ps.SKUID = s.SKUID
    WHERE s.SKUID = :skuid
    LIMIT 1
");
$priceStmt->execute([':skuid' => $skuid]);
$priceRow = $priceStmt->fetch(PDO::FETCH_ASSOC);

$unitPrice = (float)$priceRow['OriginalPrice'];
$discPrice = (float)$priceRow['FinalPrice'];

// 3) lấy CartID theo user (chưa có thì tạo)
$cartStmt = $pdo->prepare("SELECT CartID FROM Cart WHERE UserID = :uid LIMIT 1");
$cartStmt->execute([':uid' => $userId]);
$cartId = $cartStmt->fetchColumn();

if (!$cartId) {
    // tạo CartID 6 ký tự
    $cartId = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $insCart = $pdo->prepare("INSERT INTO Cart (CartID, UserID) VALUES (:cid, :uid)");
    $insCart->execute([':cid' => $cartId, ':uid' => $userId]);
}

// 4) nếu item đã có trong cart -> update qty, chưa có -> insert
$checkStmt = $pdo->prepare("
    SELECT CartItemID, Quantity
    FROM Cart_Items
    WHERE CartID = :cid AND SKU_ID = :skuid
    LIMIT 1
");
$checkStmt->execute([':cid' => $cartId, ':skuid' => $skuid]);
$exist = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($exist) {
    $newQty = (int)$exist['Quantity'] + $qty;
    $newTotal = $discPrice * $newQty;

    $upd = $pdo->prepare("
        UPDATE Cart_Items
        SET Quantity = :q,
            UnitPrice = :u,
            DiscountedPrice = :d,
            TotalPrice = :t
        WHERE CartItemID = :id
    ");
    $upd->execute([
        ':q' => $newQty,
        ':u' => $unitPrice,
        ':d' => $discPrice,
        ':t' => $newTotal,
        ':id' => $exist['CartItemID'],
    ]);
} else {
    $total = $discPrice * $qty;

    $insItem = $pdo->prepare("
        INSERT INTO Cart_Items (CartID, SKU_ID, Quantity, UnitPrice, DiscountedPrice, TotalPrice)
        VALUES (:cid, :skuid, :q, :u, :d, :t)
    ");
    $insItem->execute([
        ':cid' => $cartId,
        ':skuid' => $skuid,
        ':q' => $qty,
        ':u' => $unitPrice,
        ':d' => $discPrice,
        ':t' => $total,
    ]);
}

// 5) redirect
if ($action === 'buy_now') {
    header('Location: cart.php?buy_now=1');
    exit;
}

header('Location: cart.php?added=1');
exit;

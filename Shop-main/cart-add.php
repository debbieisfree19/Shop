<?php
session_start();
require_once 'db_connect.php';

/* ================= AUTH ================= */
if (!isset($_SESSION['user_id'])) {
    header('Location: auth-login.php');
    exit;
}

$userId = $_SESSION['user_id'];

/* ================= INPUT ================= */
$skuid  = trim($_GET['skuid'] ?? '');
$qty    = max(1, (int)($_GET['qty'] ?? 1));
$action = $_GET['action'] ?? 'add_to_cart';

if ($skuid === '') {
    header('Location: shop.php');
    exit;
}

/* ================= CHECK SKU ================= */
$skuStmt = $pdo->prepare("
    SELECT
        s.SKUID,
        s.ProductID,
        s.SellPrice
    FROM SKU s
    WHERE s.SKUID = :skuid
      AND s.Status = 1
    LIMIT 1
");
$skuStmt->execute([':skuid' => $skuid]);
$sku = $skuStmt->fetch(PDO::FETCH_ASSOC);

if (!$sku) {
    header('Location: shop.php?err=sku_not_found');
    exit;
}

/* ================= GET FINAL PRICE (SALE) ================= */
$priceStmt = $pdo->prepare("
    SELECT
        s.SellPrice AS UnitPrice,
        MIN(ps.DiscountedPrice) AS SalePrice
    FROM SKU s
    LEFT JOIN PRODUCT_SALE ps
        ON ps.SKUID = s.SKUID
       AND ps.StartDate <= NOW()
       AND (ps.EndDate IS NULL OR ps.EndDate >= NOW())
    WHERE s.SKUID = :skuid
    GROUP BY s.SellPrice
");
$priceStmt->execute([':skuid' => $skuid]);
$priceRow = $priceStmt->fetch(PDO::FETCH_ASSOC);

$unitPrice  = (float)$priceRow['UnitPrice'];
$finalPrice = $priceRow['SalePrice'] !== null
    ? (float)$priceRow['SalePrice']
    : $unitPrice;

/* ================= GET / CREATE CART ================= */
$cartStmt = $pdo->prepare("
    SELECT CartID
    FROM Cart
    WHERE UserID = :uid
    LIMIT 1
");
$cartStmt->execute([':uid' => $userId]);
$cartId = $cartStmt->fetchColumn();

if (!$cartId) {
    $cartId = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $pdo->prepare("
        INSERT INTO Cart (CartID, UserID)
        VALUES (:cid, :uid)
    ")->execute([
        ':cid' => $cartId,
        ':uid' => $userId
    ]);
}

/* ================= CHECK ITEM EXIST ================= */
$checkStmt = $pdo->prepare("
    SELECT CartItemID, Quantity
    FROM Cart_Items
    WHERE CartID = :cid AND SKU_ID = :skuid
    LIMIT 1
");
$checkStmt->execute([
    ':cid'   => $cartId,
    ':skuid' => $skuid
]);
$item = $checkStmt->fetch(PDO::FETCH_ASSOC);

/* ================= INSERT / UPDATE ================= */
if ($item) {
    $newQty   = (int)$item['Quantity'] + $qty;
    $newTotal = $finalPrice * $newQty;

    $pdo->prepare("
        UPDATE Cart_Items
        SET
            Quantity = :q,
            UnitPrice = :u,
            DiscountedPrice = :d,
            TotalPrice = :t
        WHERE CartItemID = :id
    ")->execute([
        ':q'  => $newQty,
        ':u'  => $unitPrice,
        ':d'  => $finalPrice,
        ':t'  => $newTotal,
        ':id' => $item['CartItemID']
    ]);
} else {
    $total = $finalPrice * $qty;

    $pdo->prepare("
        INSERT INTO Cart_Items
            (CartID, SKU_ID, Quantity, UnitPrice, DiscountedPrice, TotalPrice)
        VALUES
            (:cid, :skuid, :q, :u, :d, :t)
    ")->execute([
        ':cid'   => $cartId,
        ':skuid' => $skuid,
        ':q'     => $qty,
        ':u'     => $unitPrice,
        ':d'     => $finalPrice,
        ':t'     => $total
    ]);
}

/* ================= REDIRECT ================= */
if ($action === 'buy_now') {
    header('Location: cart.php?buy_now=1');
    exit;
}

header('Location: cart.php?added=1');
exit;
<?php
session_start();
require_once 'db_connect.php';

// Nhận input
$productId = $_GET['id'] ?? '';
$qty       = (int)($_GET['qty'] ?? 1);
$action    = $_GET['action'] ?? 'add_to_cart';

if ($qty < 1) $qty = 1;

// Validate id
if ($productId === '') {
    header('Location: shop.php');
    exit;
}

// Kiểm tra sản phẩm có tồn tại không (optional nhưng nên có)
$stmt = $pdo->prepare("SELECT ProductID FROM Product WHERE ProductID = ?");
$stmt->execute([$productId]);
$exists = $stmt->fetchColumn();

if (!$exists) {
    header('Location: shop.php?err=not_found');
    exit;
}

// Init cart trong session
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // dạng: [productId => qty]
}

// Add/increase qty
if (isset($_SESSION['cart'][$productId])) {
    $_SESSION['cart'][$productId] += $qty;
} else {
    $_SESSION['cart'][$productId] = $qty;
}

// Redirect theo action
if ($action === 'buy_now') {
    header('Location: checkout.php');
    exit;
}

header('Location: cart.php');
exit;

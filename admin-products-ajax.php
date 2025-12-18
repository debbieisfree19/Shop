<?php
require_once 'db_connect.php';
header('Content-Type: application/json; charset=utf-8');

// nếu có output buffer thì dọn, tránh dính HTML/warning
if (ob_get_length()) { ob_clean(); }

$action = $_POST['action'] ?? '';

try {
    if ($action === 'ajax_load_sku') {
        $pid = trim($_POST['product_id'] ?? '');
        if ($pid === '') throw new Exception('Thiếu product_id');

        $stmt = $pdo->prepare("
            SELECT SKUID, Format, SellPrice, Stock, Status
            FROM SKU
            WHERE ProductID = :pid
            ORDER BY SKUID
        ");
        $stmt->execute([':pid' => $pid]);

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    throw new Exception('Action không hợp lệ: ' . $action);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

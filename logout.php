<?php
session_start();

// Xóa hết session
$_SESSION = [];

// Hủy cookie session (cho chắc)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Quay về shop hoặc home tùy bà
header('Location: shop.php');
exit;

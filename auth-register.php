<?php
/**
 * User Register Page (AUTO FIT SCHEMA)
 * Đăng ký bằng email + password hash
 */

session_start();
require_once 'db_connect.php';

$error_message   = '';
$success_message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: account-index.php');
    exit;
}

/** Lấy danh sách cột của bảng User_Account */
function getUserAccountColumns(PDO $pdo): array {
    $cols = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM User_Account");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[$row['Field']] = $row; // Field, Type, Null, Key, Default, Extra
    }
    return $cols;
}

/** Kiểm tra UserID có auto_increment không */
function userIdIsAutoIncrement(array $cols): bool {
    if (!isset($cols['UserID'])) return false;
    return stripos($cols['UserID']['Extra'] ?? '', 'auto_increment') !== false;
}

/** Tạo UserID dạng U00001 nếu UserID không auto_increment */
function generateUserID(PDO $pdo): string {
    $stmt = $pdo->query("
        SELECT MAX(CAST(SUBSTRING(UserID, 2) AS UNSIGNED)) AS max_id
        FROM User_Account
        WHERE UserID LIKE 'U%'
    ");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    $next = (int)($row['max_id'] ?? 0) + 1;
    return 'U' . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
}

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email            = trim($_POST['email'] ?? '');
    $displayName      = trim($_POST['username'] ?? ''); // input "Tên hiển thị"
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $role_input       = trim($_POST['role'] ?? 'customer');

    $role = strtolower($role_input) === 'admin' ? 'admin' : 'customer';

    if ($email === '' || $displayName === '' || $password === '' || $confirm_password === '') {
        $error_message = 'Vui lòng điền đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email không hợp lệ.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Mật khẩu nhập lại không khớp.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } else {
        try {
            $cols = getUserAccountColumns($pdo);

            // Check trùng email
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM User_Account WHERE Email = :email");
            $checkStmt->execute([':email' => $email]);
            if ((int)$checkStmt->fetchColumn() > 0) {
                $error_message = 'Email này đã được sử dụng. Vui lòng dùng email khác.';
            } else {
                // Xác định cột password
                $passwordCol = null;
                if (isset($cols['PasswordHash'])) $passwordCol = 'PasswordHash';
                else if (isset($cols['Password'])) $passwordCol = 'Password';
                else throw new Exception("Bảng User_Account không có cột PasswordHash/Password.");

                // Xác định cột tên hiển thị (ưu tiên)
                $nameCol = null;
                if (isset($cols['Username'])) $nameCol = 'Username';
                else if (isset($cols['FullName'])) $nameCol = 'FullName';
                else if (isset($cols['DisplayName'])) $nameCol = 'DisplayName';
                // nếu không có cột tên -> thôi, bỏ qua cũng được

                // Role column
                $hasRole = isset($cols['Role']);

                // Build INSERT động
                $fields = [];
                $params = [];

                // UserID
                if (isset($cols['UserID']) && !userIdIsAutoIncrement($cols)) {
                    $fields[] = "UserID";
                    $params[':UserID'] = generateUserID($pdo);
                }

                // Email
                if (!isset($cols['Email'])) {
                    throw new Exception("Bảng User_Account không có cột Email.");
                }
                $fields[] = "Email";
                $params[':Email'] = $email;

                // Display name (nếu có)
                if ($nameCol) {
                    $fields[] = $nameCol;
                    $params[":$nameCol"] = $displayName;
                }

                // Password hash
                $fields[] = $passwordCol;
                $params[":$passwordCol"] = password_hash($password, PASSWORD_DEFAULT);

                // Role (nếu có)
                if ($hasRole) {
                    $fields[] = "Role";
                    $params[':Role'] = $role;
                }

                $sql = "INSERT INTO User_Account (" . implode(", ", $fields) . ")
                        VALUES (" . implode(", ", array_keys($params)) . ")";

                $insertStmt = $pdo->prepare($sql);
                $insertStmt->execute($params);

                $success_message = 'Đăng ký tài khoản thành công! Bạn có thể đăng nhập ngay bây giờ.';
                $_POST = [];
            }
        } catch (Exception $e) {
            // BẬT DEBUG nếu cần:
            // $error_message = $e->getMessage();
            $error_message = 'Có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại sau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Moonlit Store</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="moonlit-style.css">
</head>
<body class="account-body">

<main class="auth-main">
    <div class="auth-card">
        <h1 class="auth-title">Đăng Ký</h1>

        <?php if ($error_message): ?>
            <div class="auth-alert auth-alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="auth-alert" style="background-color:#E6F4EA;border-left:3px solid #28a745;color:#256d3b;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="auth-form-group">
                <label class="auth-label">Email</label>
                <input type="email" name="email" class="account-input auth-input"
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>

            <div class="auth-form-group">
                <label class="auth-label">Tên hiển thị</label>
                <input type="text" name="username" class="account-input auth-input"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="auth-form-group">
                <label class="auth-label">Mật khẩu</label>
                <input type="password" name="password" class="account-input auth-input" required>
            </div>

            <div class="auth-form-group">
                <label class="auth-label">Nhập lại mật khẩu</label>
                <input type="password" name="confirm_password" class="account-input auth-input" required>
            </div>

            <div class="auth-form-group">
                <label class="auth-label">Loại tài khoản</label>
                <select name="role" class="account-input auth-input">
                    <option value="customer" <?php echo (($_POST['role'] ?? '') !== 'admin') ? 'selected' : ''; ?>>
                        Khách hàng
                    </option>
                    <!-- admin ẩn -->
                </select>
            </div>

            <button type="submit" class="account-btn-save auth-btn-submit">Đăng Ký</button>

            <div class="auth-divider"></div>

            <div class="auth-footer">
                <p>
                    Bạn đã có tài khoản?
                    <a href="auth-login.php" class="auth-link">Đăng nhập</a>
                </p>
            </div>
        </form>
    </div>
</main>

<footer class="site-footer">© 2025 Moonlit — All rights reserved.</footer>
</body>
</html>

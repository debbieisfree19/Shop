<?php
/**
 * MOONLIT STORE - CART PAGE (DB BASED) - HEADER MATCH product-detail.php
 */

session_start();
require_once 'db_connect.php';

/* ===== AUTH ===== */
if (!isset($_SESSION['user_id'])) {
    header('Location: auth-login.php');
    exit;
}

$isLoggedIn      = isset($_SESSION['user_id']);
$userId          = $_SESSION['user_id'];
$currentUsername = $_SESSION['username'] ?? '';
$currentPage     = 'cart.php';

/* ===== NAV HELPER ===== */
if (!function_exists('nav_active')) {
    function nav_active(string $page, string $currentPage): string {
        return $page === $currentPage ? 'nav-active' : '';
    }
}

/* ===== HANDLE REMOVE ITEM ===== */
if (isset($_GET['remove'])) {
    $stmt = $pdo->prepare("
        DELETE ci FROM Cart_Items ci
        JOIN Cart c ON ci.CartID = c.CartID
        WHERE ci.CartItemID = :cid AND c.UserID = :uid
    ");
    $stmt->execute([
        ':cid' => $_GET['remove'],
        ':uid' => $userId
    ]);
    header('Location: cart.php');
    exit;
}

/* ===== HANDLE CLEAR CART ===== */
if (isset($_GET['clear']) && $_GET['clear'] == 1) {
    $stmt = $pdo->prepare("
        DELETE ci FROM Cart_Items ci
        JOIN Cart c ON ci.CartID = c.CartID
        WHERE c.UserID = :uid
    ");
    $stmt->execute([':uid' => $userId]);
    header('Location: cart.php');
    exit;
}

/* ===== HANDLE UPDATE QTY ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qty'])) {
    foreach ($_POST['qty'] as $cartItemId => $qty) {
        $qty = max(1, (int)$qty);

        // Lưu ý: TotalPrice = Quantity * DiscountedPrice (theo schema hiện tại)
        $pdo->prepare("
            UPDATE Cart_Items
            SET Quantity = :q,
                TotalPrice = :q * DiscountedPrice
            WHERE CartItemID = :cid
        ")->execute([
            ':q'   => $qty,
            ':cid' => $cartItemId
        ]);
    }
    header('Location: cart.php');
    exit;
}

/* ===== LOAD CART ITEMS ===== */
$sql = "
    SELECT
        ci.CartItemID,
        ci.Quantity,
        ci.DiscountedPrice,
        ci.TotalPrice,

        s.Format,

        p.ProductName,
        (p.Image IS NOT NULL AND OCTET_LENGTH(p.Image) > 0) AS HasImage,
        p.ProductID
    FROM Cart c
    JOIN Cart_Items ci ON c.CartID = ci.CartID
    JOIN SKU s ON ci.SKU_ID = s.SKUID
    JOIN Product p ON s.ProductID = p.ProductID
    WHERE c.UserID = :uid
    ORDER BY ci.CartItemID DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid' => $userId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===== TOTAL ===== */
$cartTotal  = 0;
$totalItems = 0;
foreach ($items as $i) {
    $cartTotal  += (float)$i['TotalPrice'];
    $totalItems += (int)$i['Quantity'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Giỏ hàng - Moonlit Store</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body">

<!-- ===================== HEADER (MATCH SHOP) ===================== -->
<header class="account-header site-header">
  <div class="container header-inner">
    <div class="header-left">
      <a href="index.php" class="logo-link header-logo">
        <img src="img/image.png" alt="Moonlit logo" class="logo-img">
      </a>

      <nav class="header-menu">
        <a href="index.php" class="header-menu-link <?php echo nav_active('index.php', $currentPage); ?>">
          Trang chủ
        </a>
        <a href="shop.php" class="header-menu-link <?php echo nav_active('shop.php', $currentPage); ?>">
          Cửa hàng
        </a>
        <a href="aboutus.php" class="header-menu-link <?php echo nav_active('aboutus.php', $currentPage); ?>">
          Về chúng tôi
        </a>
        <a href="return-policy.php" class="header-menu-link <?php echo nav_active('return-policy.php', $currentPage); ?>">
          Chính sách
        </a>
      </nav>
    </div>

    <div class="header-right">
      <form method="GET" action="shop.php" class="header-search-form">
        <input type="text" name="q" class="account-input header-search-input" placeholder="Tìm sách...">
        <button type="submit" class="account-btn-save header-search-btn">Tìm</button>
      </form>

      <a href="cart.php" class="account-btn-secondary header-cart-btn">Giỏ hàng</a>

      <?php if ($isLoggedIn): ?>
        <div class="header-account">
          <span class="account-username">
            Xin chào, <strong><?php echo htmlspecialchars($currentUsername); ?></strong>
          </span>
          <div class="header-account-actions">
            <a href="account-index.php" class="account-btn-secondary header-account-btn">Tài khoản</a>
            <a href="logout.php" class="account-btn-secondary header-account-btn">Đăng xuất</a>
          </div>
        </div>
      <?php else: ?>
        <a href="auth-login.php" class="account-btn-secondary header-account-btn">Tài khoản</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ===================== MAIN (SHOP-LIKE) ===================== -->
<main class="account-main cart-main">
  <div class="container">

    <!-- Header section giống shop.php -->
    <section class="shop-header cart-header">
      <h1 class="account-section-title">Giỏ hàng Moonlit</h1>
      <p class="account-section-subtitle">
        Kiểm tra lại món bạn chọn rồi “chốt đơn” nha ✨
      </p>
    </section>

    <?php if (empty($items)): ?>
      <!-- Empty state giống shop -->
      <section class="shop-products">
        <div class="shop-grid">
          <div class="shop-grid-empty">
            <div class="account-empty-state">
              <p class="account-empty-text">
                Giỏ hàng của bạn đang trống. Ghé Cửa hàng chọn vài cuốn xinh xinh nè!
              </p>
              <div style="margin-top: 12px;">
                <a href="shop.php" class="account-btn-save">Tiếp tục mua sắm</a>
              </div>
            </div>
          </div>
        </div>
      </section>

    <?php else: ?>

      <form method="POST">
        <div class="cart-layout">

          <!-- LEFT: items -->
          <section class="cart-items">
            <div class="account-card cart-items-card">

              <!-- Header table (mày có CSS sẵn) -->
              <div class="cart-table-header">
                <div>Sản phẩm</div>
                <div>Đơn giá</div>
                <div>Số lượng</div>
                <div>Thành tiền</div>
                <div></div>
              </div>

              <div class="cart-list">
                <?php foreach ($items as $item): ?>
                  <div class="cart-item">
                    <div class="cart-item-info">
                      <div class="cart-item-image">
                        <?php if (!empty($item['HasImage'])): ?>
                          <img src="product-image.php?id=<?php echo urlencode($item['ProductID']); ?>" alt="">
                        <?php else: ?>
                          <span class="shop-product-image-placeholder">Moonlit</span>
                        <?php endif; ?>
                      </div>

                      <div>
                        <div class="cart-item-title">
                          <?php echo htmlspecialchars($item['ProductName']); ?>
                        </div>
                        <div class="cart-item-sku">
                          <?php echo htmlspecialchars($item['Format']); ?>
                        </div>
                      </div>
                    </div>

                    <div class="cart-item-price">
                      <span class="cart-price-current">
                        <?php echo number_format((float)$item['DiscountedPrice'], 0, ',', '.'); ?> đ
                      </span>
                    </div>

                    <div class="cart-item-qty">
                      <input
                        type="number"
                        name="qty[<?php echo (int)$item['CartItemID']; ?>]"
                        value="<?php echo (int)$item['Quantity']; ?>"
                        min="1"
                        class="account-input cart-qty-input"
                      >
                    </div>

                    <div class="cart-item-total">
                      <?php echo number_format((float)$item['TotalPrice'], 0, ',', '.'); ?> đ
                    </div>

                    <div class="cart-item-remove">
                      <a href="cart.php?remove=<?php echo (int)$item['CartItemID']; ?>"
                         class="cart-remove-link"
                         onclick="return confirm('Xóa sản phẩm này khỏi giỏ hàng nha?');">
                        Xóa
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- actions row giống style tổng -->
              <div class="cart-actions-row">
                <button type="submit" class="account-btn-secondary cart-update-btn">
                  Cập nhật giỏ hàng
                </button>

                <a href="cart.php?clear=1" class="cart-clear-link"
                   onclick="return confirm('Xóa toàn bộ giỏ hàng luôn hả?');">
                  Xóa hết
                </a>
              </div>

            </div>
          </section>

          <!-- RIGHT: summary -->
          <aside class="cart-summary">
            <div class="account-card cart-summary-card">
              <h3 class="cart-summary-title">Tóm tắt</h3>

              <div class="cart-summary-row">
                <span>Số lượng</span>
                <span><?php echo (int)$totalItems; ?></span>
              </div>

              <div class="cart-summary-row cart-summary-total">
                <span>Tổng</span>
                <span><?php echo number_format((float)$cartTotal, 0, ',', '.'); ?> đ</span>
              </div>

              <a href="checkout.php" class="account-btn-save">Thanh toán</a>

              <p class="cart-note">
                * Phí ship và ưu đãi sẽ được tính ở bước thanh toán.
              </p>

              <div style="margin-top: 10px;">
                <a href="shop.php" class="account-btn-secondary" style="width:100%; display:inline-block; text-align:center;">
                  Tiếp tục mua sắm
                </a>
              </div>
            </div>
          </aside>

        </div>
      </form>

    <?php endif; ?>
  </div>
</main>

<footer class="site-footer">
  © <?php echo date('Y'); ?> Moonlit Store. All rights reserved.
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>


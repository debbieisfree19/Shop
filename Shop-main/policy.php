<?php
/**
 * MOONLIT STORE - RETURN POLICY PAGE
 * Header/Footer match product-detail.php & cart.php
 */

session_start();
require_once 'db_connect.php';

$isLoggedIn = isset($_SESSION['user_id']);
$currentUsername = $_SESSION['username'] ?? '';
$currentPage = 'return-policy.php';

if (!function_exists('nav_active')) {
  function nav_active(string $page, string $currentPage): string
  {
    return $page === $currentPage ? 'nav-active' : '';
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <title>Chính sách - Moonlit Store</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="moonlit-style.css">
</head>

<body class="account-body">

  <!-- ===================== HEADER ===================== -->
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
          <a href="forum.php" class="header-menu-link <?php echo nav_active('forum.php', $currentPage); ?>">
            Moonlit Forum
          </a>
          <a href="aboutus.php" class="header-menu-link <?php echo nav_active('aboutus.php', $currentPage); ?>">
            Về chúng tôi
          </a>
          <a href="policy.php" class="header-menu-link <?php echo nav_active('policy.php', $currentPage); ?>">
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

  <!-- ===================== MAIN ===================== -->
  <main class="account-main">
    <div class="container">

      <!-- Breadcrumb -->
      <div class="mb-3 small">
        <a href="index.php" class="text-decoration-none" style="color: var(--color-deep-blue);">Trang chủ</a>
        <span class="text-secondary"> / </span>
        <span>Chính sách</span>
      </div>

      <!-- Title -->
      <section class="shop-header mb-3">
        <h1 class="account-section-title">Chính sách Moonlit</h1>
        <p class="account-section-subtitle">
          Tụi mình viết rõ ràng để bạn mua yên tâm – nhận sách vui vẻ ✨
        </p>
      </section>

      <!-- Exchange / Return -->
      <div class="account-card mb-3">
        <h2 class="account-card-title mb-3">1) Đổi / Trả</h2>

        <div class="account-order-card mb-3" style="padding:16px;">
          <strong style="font-size:14px;">Điều kiện áp dụng</strong>
          <ul class="mb-0" style="font-size:14px; margin-top:8px;">
            <li>Sách còn nguyên tem/nhãn (nếu có), không rách/móp/nước, không ghi chú lên sách.</li>
            <li>Đổi/trả trong vòng <strong>07 ngày</strong> kể từ ngày nhận hàng (theo trạng thái giao hàng).</li>
            <li>Áp dụng cho lỗi do Moonlit: giao sai SKU/phiên bản, thiếu hàng, sách lỗi in/rách/móp do vận chuyển.</li>
          </ul>
        </div>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="account-order-card h-100" style="padding:16px;">
              <strong style="font-size:14px;">Trường hợp được đổi</strong>
              <ul class="mb-0" style="font-size:14px; margin-top:8px;">
                <li>Giao sai phiên bản (bìa mềm/bìa cứng), sai ISBN, sai SKU.</li>
                <li>Sách lỗi sản xuất: in thiếu trang, lem mực nặng, bong gáy.</li>
                <li>Hàng hư hại do vận chuyển (móp, rách nhiều).</li>
              </ul>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="account-order-card h-100" style="padding:16px;">
              <strong style="font-size:14px;">Trường hợp không hỗ trợ</strong>
              <ul class="mb-0" style="font-size:14px; margin-top:8px;">
                <li>Đã sử dụng/ghi chú lên sách, hư hại do bảo quản cá nhân.</li>
                <li>Quá thời hạn 07 ngày.</li>
                <li>Không có bằng chứng mở hộp (khuyến khích quay video unbox).</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Refund -->
      <div class="account-card mb-3">
        <h2 class="account-card-title mb-3">2) Hoàn tiền</h2>

        <div class="account-order-card" style="padding:16px;">
          <ul class="mb-0" style="font-size:14px;">
            <li>Nếu không còn hàng để đổi, Moonlit sẽ hỗ trợ <strong>hoàn tiền</strong> theo giá bạn đã thanh toán.</li>
            <li>Thời gian xử lý dự kiến: <strong>3–7 ngày làm việc</strong> (tùy ngân hàng/đơn vị thanh toán).</li>
            <li>Hoàn tiền qua đúng phương thức: COD (chuyển khoản), Bank (chuyển khoản/đối soát).</li>
          </ul>
        </div>
      </div>

      <!-- Shipping -->
      <div class="account-card mb-3">
        <h2 class="account-card-title mb-3">3) Vận chuyển</h2>

        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="account-order-card h-100" style="padding:16px;">
              <strong style="font-size:14px;">Thời gian giao hàng</strong>
              <ul class="mb-0" style="font-size:14px; margin-top:8px;">
                <li>Nội thành: 1–3 ngày</li>
                <li>Liên tỉnh: 3–7 ngày</li>
                <li>Có thể thay đổi theo Carrier và thời điểm cao điểm.</li>
              </ul>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="account-order-card h-100" style="padding:16px;">
              <strong style="font-size:14px;">Phí ship</strong>
              <ul class="mb-0" style="font-size:14px; margin-top:8px;">
                <li>Phí ship hiển thị ở bước checkout (theo đơn vị vận chuyển).</li>
                <li>Một số chương trình có thể hỗ trợ giảm phí ship theo điều kiện.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment -->
      <div class="account-card mb-3">
        <h2 class="account-card-title mb-3">4) Thanh toán</h2>

        <div class="account-order-card" style="padding:16px;">
          <ul class="mb-0" style="font-size:14px;">
            <li><strong>COD</strong>: thanh toán khi nhận hàng.</li>
            <li><strong>Chuyển khoản</strong>: bạn sẽ nhận hướng dẫn ở trang checkout/hoặc xác nhận từ Moonlit.</li>
            <li>Đơn hàng chỉ được giữ tối đa một khoảng thời gian nếu cần xác nhận chuyển khoản (tuỳ chính sách từng
              thời điểm).</li>
          </ul>
        </div>
      </div>

      <!-- How to request -->
      <div class="account-card">
        <h2 class="account-card-title mb-3">5) Cách gửi yêu cầu đổi/trả</h2>

        <div class="account-order-card mb-3" style="padding:16px;">
          <ol class="mb-0" style="font-size:14px;">
            <li>Chuẩn bị mã đơn hàng + ảnh tình trạng sách (hoặc video unbox).</li>
            <li>Gửi yêu cầu qua trang tài khoản (mục “Yêu cầu đổi/trả”) hoặc inbox Moonlit.</li>
            <li>Moonlit phản hồi xác nhận và hướng dẫn gửi hàng về (nếu cần).</li>
          </ol>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <a href="shop.php" class="account-btn-save text-decoration-none">Quay lại Cửa hàng</a>
          <a href="cart.php" class="account-btn-secondary text-decoration-none">Xem Giỏ hàng</a>
        </div>
      </div>

    </div>
  </main>

  <!-- ===================== FOOTER ===================== -->
  <footer class="site-footer">
    © <?php echo date('Y'); ?> Moonlit Store. All rights reserved.
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
// includes/header.php
// Ensure session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Lấy số lượng giỏ hàng nếu user đã đăng nhập
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(gc.SoLuong), 0) as count 
                               FROM GIOHANG_CHITIET gc 
                               JOIN GIOHANG gh ON gc.GioHangID = gh.GioHangID 
                               WHERE gh.KhachHangID = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        $cart_count = $result['count'];
    } catch (PDOException $e) {
        // Xử lý lỗi
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <!-- CSS -->
    <link rel="stylesheet" href="../public/assets/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TINK Jewelry | Vẻ đẹp tinh tế</title>
    <link rel="stylesheet" href="../assets/public.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700&family=Open+Sans:wght@300;400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Header mới -->
    <header class="main-header">
        <!-- Top header -->
        <div class="top-header">
            <div class="top-header-left">
                <div class="header-contact">
                    <i class="fas fa-phone"></i>
                    <span>Hotline: (028) 1234 5678</span>
                </div>
                <div class="header-email">
                    <i class="fas fa-envelope"></i>
                    <span>hello@tinkjewelry.com</span>
                </div>
            </div>
            <div class="top-header-right">
                <a href="homepage.php#store-description" class="header-link">Không gian Tink</a>
                <a href="homepage.php#reviews" class="header-link">Đánh giá</a>
                <a href="homepage.php#contact" class="header-link">Liên hệ chúng tôi</a>
            </div>
        </div>
        
        <!-- Main navbar -->
        <div class="main-navbar">
            <!-- Logo -->
            <a href="homepage.php" class="main-logo">TINK <span>Jewelry</span></a>
            
            <!-- Main menu -->
            <ul class="nav-menu">
                <li><a href="homepage.php"><i class="fas fa-home"></i> Trang chủ</a></li>
                <li><a href="products.php"><i class="fas fa-shopping-bag"></i> Sản phẩm</a></li>
                
                <li class="dropdown">
                    <a href="#"><i class="fas fa-receipt"></i> Đơn hàng <i class="fas fa-chevron-down"></i></a>
                    <ul class="dropdown-menu">
                        <li><a href="your-orders.php"><i class="fas fa-receipt"></i> Đơn của bạn</a></li>
                        <li><a href="order-history.php"><i class="fas fa-history"></i> Lịch sử mua hàng</a></li>
                    </ul>
                </li>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                <?php
                // Lấy thông tin khách hàng
                $customerInfo = null;
                try {
                    $stmt = $pdo->prepare("SELECT * FROM KHACHHANG WHERE KhachHangID = ? LIMIT 1");
                    $stmt->execute([$_SESSION['user_id']]);
                    $customerInfo = $stmt->fetch();
                } catch (PDOException $e) {
                    $customerInfo = null;
                }
                ?>
                <li class="dropdown">
                    <a href="#"><div class="user-icon"><i class="fas fa-user"></i></div></a>
                    <ul class="dropdown-menu">
                        <?php if ($customerInfo): ?>
                            <li style="padding: 12px 15px; border-bottom: 1px solid #eee;">
                                <div style="font-weight: 600; color: #333; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($customerInfo['TenKH']); ?>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo htmlspecialchars($customerInfo['Gmail'] ?? ''); ?>
                                </div>
                                <?php if (!empty($customerInfo['SoDienThoai'])): ?>
                                    <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                        <?php echo htmlspecialchars($customerInfo['SoDienThoai']); ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endif; ?>
                        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li>
                    <a href="login.php" id="loginBtn">
                        <i class="fas fa-user"></i> Đăng nhập
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="search-icon-container">
                    <a href="search.php" class="search-icon">
                        <i class="fas fa-search"></i> Tìm kiếm
                    </a>
                </li>
                
                <li>
                    <a href="cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </header>
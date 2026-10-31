<?php
// dashboard.php - Trang tổng quan
session_start();
require_once '../config/db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../admin/login.php");
    exit();
}

$userName = $_SESSION['username'] ?? 'Quản trị viên';
$userRole = $_SESSION['role'] ?? 'Administrator';
$userAvatar = $_SESSION['user_avatar'] ?? '';

// Lấy thống kê tổng quan
try {
    // Tổng số sản phẩm
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM SANPHAM");
    $productStats = $stmt->fetch();
    $totalProducts = $productStats['total'] ?? 0;
    
    // Tổng số đơn hàng
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM DONHANG");
    $orderStats = $stmt->fetch();
    $totalOrders = $orderStats['total'] ?? 0;
    
    // Tổng số khách hàng
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM KHACHHANG");
    $customerStats = $stmt->fetch();
    $totalCustomers = $customerStats['total'] ?? 0;
    
    // Tổng giá trị tất cả đơn hàng đã hoàn thành
    $stmt = $pdo->query("SELECT SUM(TongTien) as total_revenue FROM DONHANG WHERE TrangThai = 'Đã giao'");
    $revenueStats = $stmt->fetch();
    $totalRevenue = $revenueStats['total_revenue'] ?? 0;
    
    // Đơn hàng cần xử lý (Xác nhận + Đang giao)
    $stmt = $pdo->query("SELECT COUNT(*) as pending FROM DONHANG WHERE TrangThai IN ('Xác nhận', 'Đang giao')");
    $pendingStats = $stmt->fetch();
    $pendingOrders = $pendingStats['pending'] ?? 0;
    
    // Đơn hàng gần đây (10 đơn)
    $stmt = $pdo->query("
        SELECT dh.*, kh.TenKH 
        FROM DONHANG dh
        LEFT JOIN KHACHHANG kh ON dh.KhachHangID = kh.KhachHangID
        ORDER BY dh.NgayTao DESC
        LIMIT 10
    ");
    $recentOrders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $totalProducts = $totalOrders = $totalCustomers = $totalRevenue = 0;
    $pendingOrders = 0;
    $recentOrders = [];
    $_SESSION['message'] = "Lỗi khi tải thống kê: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin TINK Jewelry</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../public/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Thông báo đơn hàng cần xử lý */
        .pending-alert {
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(255, 152, 0, 0.2);
        }
        
        .pending-alert.hidden {
            display: none;
        }
        
        .pending-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .pending-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .pending-text {
            flex: 1;
        }
        
        .pending-text h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        
        .pending-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .pending-action {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Thống kê đơn giản */
        .simple-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .stat-card:nth-child(1)::before {
            background: linear-gradient(90deg, #004080, #005bb5);
        }
        
        .stat-card:nth-child(2)::before {
            background: linear-gradient(90deg, #2ecc71, #27ae60);
        }
        
        .stat-card:nth-child(3)::before {
            background: linear-gradient(90deg, #3498db, #2980b9);
        }
        
        .stat-card:nth-child(4)::before {
            background: linear-gradient(90deg, #9b59b6, #8e44ad);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 28px;
            color: white;
        }
        
        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #004080, #005bb5);
        }
        
        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .stat-card:nth-child(4) .stat-icon {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .stat-info h3 {
            font-size: 32px;
            color: #333;
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-info p {
            color: #666;
            font-size: 16px;
            margin: 0;
        }
        
        /* Đơn hàng gần đây */
        .recent-orders {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .section-title {
            color: #004080;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .orders-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }
        
        .orders-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .orders-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .orders-list::-webkit-scrollbar-thumb {
            background: #ddd;
            border-radius: 10px;
        }
        
        .order-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .order-item:hover {
            background: #f8f9fa;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e6f3ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #004080;
            font-size: 18px;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }
        
        .order-code {
            font-weight: bold;
            color: #004080;
            font-size: 15px;
        }
        
        .order-time {
            color: #888;
            font-size: 13px;
        }
        
        .order-customer {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        
        .order-amount {
            font-weight: bold;
            color: #b8860b;
            font-size: 14px;
        }
        
        .order-status {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .status-confirmed {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-delivering {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .status-delivered {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            border: 2px solid transparent;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            display: block;
        }
        
        .action-card:hover {
            background: linear-gradient(135deg, #004080, #005bb5);
            color: white;
            transform: translateY(-5px);
            border-color: #d4af37;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            text-decoration: none;
        }
        
        .action-card i {
            font-size: 36px;
            margin-bottom: 15px;
            display: block;
        }
        
        .action-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .action-card p {
            font-size: 14px;
            color: #888;
            margin: 0;
        }
        
        .action-card:hover p {
            color: rgba(255,255,255,0.8);
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #004080, #005bb5);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .welcome-banner h1 {
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .welcome-banner p {
            opacity: 0.9;
            margin: 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .pending-alert {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .pending-info {
                flex-direction: column;
                text-align: center;
            }
            
            .simple-stats {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .order-header, .order-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .order-time, .order-status {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo-section">
            <div class="logo">TINK Jewelry </div>
        </div>
        <div class="user-section">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
            <div class="user-avatar">
                <?php if (!empty($userAvatar)): ?>
                    <img src="../<?php echo htmlspecialchars($userAvatar); ?>" alt="User Avatar">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php" class="nav-link">
                        <i class="fas fa-gem"></i> Sản phẩm
                    </a>
                </li>
                <li class="nav-item">
                    <a href="orders.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i> Đơn hàng
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customers.php" class="nav-link">
                        <i class="fas fa-users"></i> Khách hàng
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i> Báo cáo
                    </a>
                </li>
            </ul>
            
            <div class="divider"></div>
            
            <div class="logout">
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Hiển thị thông báo -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                    <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Chào mừng -->
            <div class="welcome-banner">
                <h1>Xin chào, <?php echo htmlspecialchars($userName); ?>!</h1>
                <p>Chào mừng bạn đến với trang quản trị TINK Jewelry.</p>
            </div>

            <!-- Thông báo đơn hàng cần xử lý -->
            <?php if ($pendingOrders > 0): ?>
            <div class="pending-alert">
                <div class="pending-info">
                    <div class="pending-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="pending-text">
                        <h3>Có <?php echo $pendingOrders; ?> đơn hàng cần xử lý</h3>
                        <p>Các đơn hàng đang chờ xác nhận hoặc đang giao cần được xử lý ngay.</p>
                    </div>
                </div>
                <div class="pending-action">
                    <a href="orders.php?status=Xác nhận" class="search-btn" style="text-decoration: none;">
                        <i class="fas fa-clipboard-list"></i> Xem ngay
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Thống kê đơn giản -->
            <div class="simple-stats">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-gem"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalProducts); ?></h3>
                        <p>Tổng sản phẩm</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalOrders); ?></h3>
                        <p>Tổng đơn hàng</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalCustomers); ?></h3>
                        <p>Tổng khách hàng</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalRevenue, 0, ',', '.'); ?>đ</h3>
                        <p>Tổng doanh thu</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="products.php" class="action-card">
                    <i class="fas fa-box-open"></i>
                    <h3>Quản lý sản phẩm</h3>
                    <p>Thêm, sửa, xóa sản phẩm</p>
                </a>
                
                <a href="orders.php" class="action-card">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Quản lý đơn hàng</h3>
                    <p>Xem và xử lý đơn hàng</p>
                </a>
                
                <a href="customers.php" class="action-card">
                    <i class="fas fa-user-friends"></i>
                    <h3>Quản lý khách hàng</h3>
                    <p>Xem thông tin khách hàng</p>
                </a>
                
                <a href="reports.php" class="action-card">
                    <i class="fas fa-chart-pie"></i>
                    <h3>Xem báo cáo</h3>
                    <p>Phân tích doanh thu và hiệu suất</p>
                </a>
            </div>

            <!-- Đơn hàng gần đây -->
            <div class="recent-orders">
                <div class="section-title">
                    <span><i class="fas fa-history"></i> Đơn hàng gần đây</span>
                    <a href="orders.php" style="font-size: 14px; color: #004080; text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i> Xem tất cả
                    </a>
                </div>
                
                <?php if (!empty($recentOrders)): ?>
                    <div class="orders-list">
                        <?php foreach ($recentOrders as $order): 
                            $statusClass = '';
                            switch($order['TrangThai']) {
                                case 'Xác nhận': $statusClass = 'status-confirmed'; break;
                                case 'Đang giao': $statusClass = 'status-delivering'; break;
                                case 'Đã giao': $statusClass = 'status-delivered'; break;
                                case 'Đã hủy': $statusClass = 'status-cancelled'; break;
                                default: $statusClass = 'status-confirmed'; break;
                            }
                        ?>
                        <div class="order-item">
                            <div class="order-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="order-info">
                                <div class="order-header">
                                    <div class="order-code"><?php echo htmlspecialchars($order['MaDonHang']); ?></div>
                                    <div class="order-time"><?php echo date('H:i d/m/Y', strtotime($order['NgayTao'])); ?></div>
                                </div>
                                <div class="order-customer">
                                    <?php echo htmlspecialchars($order['TenKH'] ?? 'Khách lẻ'); ?>
                                </div>
                                <div class="order-footer">
                                    <div class="order-amount">
                                        <?php echo number_format($order['TongTien'], 0, ',', '.'); ?>đ
                                    </div>
                                    <div class="order-status <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($order['TrangThai']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #888;">
                        <i class="fas fa-shopping-cart" style="font-size: 50px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Chưa có đơn hàng nào</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="../public/assets/js/script.js"></script>
    <script src="https://kit.fontawesome.com/a2e0b2b9f5.js" crossorigin="anonymous"></script>
    <script>
        // Tự động ẩn thông báo sau 5 giây
        document.addEventListener("DOMContentLoaded", function() {
            const alertBox = document.querySelector(".alert");
            if (alertBox) {
                setTimeout(() => {
                    alertBox.style.opacity = "0";
                    alertBox.style.transition = "opacity 0.8s ease";
                    setTimeout(() => {
                        alertBox.style.display = "none";
                    }, 800);
                }, 5000);
            }
            
            // Cập nhật thời gian thực cho các đơn hàng
            function updateTimes() {
                document.querySelectorAll('.order-time').forEach(el => {
                    const timeText = el.textContent;
                    // Có thể thêm xử lý hiển thị thời gian tương đối ở đây
                    // Ví dụ: "2 giờ trước", "Hôm nay",...
                });
            }
            
            updateTimes();
            setInterval(updateTimes, 60000); // Cập nhật mỗi phút
            
            // Hiệu ứng hover cho thẻ thống kê
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
        });
        
        // Logout function
        function logout() {
            if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Xử lý sự kiện cho các action cards
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!this.href) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
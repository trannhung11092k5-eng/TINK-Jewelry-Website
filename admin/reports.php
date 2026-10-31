<?php
// reports.php - Trang báo cáo thống kê
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

// Xử lý bộ lọc
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Mặc định đầu tháng
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Mặc định hôm nay

// Lấy dữ liệu báo cáo
try {
    // Tổng doanh thu trong khoảng thời gian
    $stmt = $pdo->prepare("
        SELECT 
            SUM(TongTien) as total_revenue,
            COUNT(*) as total_orders,
            AVG(TongTien) as avg_order_value
        FROM DONHANG 
        WHERE NgayTao BETWEEN ? AND ? 
        AND TrangThai = 'Đã giao'
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $summaryStats = $stmt->fetch();
    
    // Doanh thu theo ngày
    $stmt = $pdo->prepare("
        SELECT 
            DATE(NgayTao) as order_date,
            SUM(TongTien) as daily_revenue,
            COUNT(*) as daily_orders
        FROM DONHANG 
        WHERE NgayTao BETWEEN ? AND ? 
        AND TrangThai = 'Đã giao'
        GROUP BY DATE(NgayTao)
        ORDER BY order_date
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $dailyStats = $stmt->fetchAll();
    
    // Doanh thu theo thể loại sản phẩm
    $stmt = $pdo->prepare("
        SELECT 
            sp.TheLoai,
            SUM(ctdh.SoLuong * ctdh.GiaBan) as category_revenue,
            SUM(ctdh.SoLuong) as total_sold,
            COUNT(DISTINCT dh.MaDonHang) as order_count
        FROM CHITIET_DONHANG ctdh
        JOIN SANPHAM sp ON ctdh.MaSP = sp.MaSP
        JOIN DONHANG dh ON ctdh.MaDonHang = dh.MaDonHang
        WHERE dh.NgayTao BETWEEN ? AND ? 
        AND dh.TrangThai = 'Đã giao'
        GROUP BY sp.TheLoai
        ORDER BY category_revenue DESC
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $categoryStats = $stmt->fetchAll();
    
    // Top 10 sản phẩm bán chạy
    $stmt = $pdo->prepare("
        SELECT 
            sp.TenSP,
            sp.MaSP,
            sp.TheLoai,
            sp.GiaBan,
            sp.AnhSP,
            SUM(ctdh.SoLuong) as total_sold,
            SUM(ctdh.SoLuong * ctdh.GiaBan) as total_revenue
        FROM CHITIET_DONHANG ctdh
        JOIN SANPHAM sp ON ctdh.MaSP = sp.MaSP
        JOIN DONHANG dh ON ctdh.MaDonHang = dh.MaDonHang
        WHERE dh.NgayTao BETWEEN ? AND ? 
        AND dh.TrangThai = 'Đã giao'
        GROUP BY sp.MaSP
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $topProducts = $stmt->fetchAll();
    
    // Thống kê theo phương thức thanh toán
    $stmt = $pdo->prepare("
        SELECT 
            PhuongThucThanhToan,
            COUNT(*) as order_count,
            SUM(TongTien) as total_revenue
        FROM DONHANG 
        WHERE NgayTao BETWEEN ? AND ? 
        AND TrangThai = 'Đã giao'
        GROUP BY PhuongThucThanhToan
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $paymentStats = $stmt->fetchAll();
    
    // Thống kê theo trạng thái đơn hàng
    $stmt = $pdo->prepare("
        SELECT 
            TrangThai,
            COUNT(*) as order_count,
            SUM(TongTien) as total_value
        FROM DONHANG 
        WHERE NgayTao BETWEEN ? AND ? 
        GROUP BY TrangThai
    ");
    $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
    $statusStats = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $summaryStats = [];
    $dailyStats = $categoryStats = $topProducts = $paymentStats = $statusStats = [];
    $_SESSION['message'] = "Lỗi khi tải báo cáo: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Thống kê - Admin TINK Jewelry</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../public/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .report-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }
        
        .summary-card.revenue::before {
            background: linear-gradient(90deg, #2ecc71, #27ae60);
        }
        
        .summary-card.orders::before {
            background: linear-gradient(90deg, #3498db, #2980b9);
        }
        
        .summary-card.avg-order::before {
            background: linear-gradient(90deg, #9b59b6, #8e44ad);
        }
        
        .summary-card.categories::before {
            background: linear-gradient(90deg, #e74c3c, #c0392b);
        }
        
        .summary-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            color: white;
        }
        
        .summary-icon.revenue {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .summary-icon.orders {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .summary-icon.avg-order {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .summary-icon.categories {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .summary-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .summary-label {
            color: #666;
            font-size: 14px;
        }
        
        .report-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
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
        
        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        .category-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .category-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #004080;
        }
        
        .category-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .category-stats {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #666;
        }
        
        .product-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .product-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #eee;
        }
        
        .product-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .product-table tr:hover {
            background: #f8f9fa;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        
        .no-data i {
            font-size: 50px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #004080, #005bb5);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .stat-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .stat-label {
            flex: 1;
            color: #666;
        }
        
        .stat-value {
            font-weight: bold;
            color: #333;
        }
        
        .stat-percent {
            background: #e8f5e9;
            color: #388e3c;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .date-range {
            background: #e6f3ff;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #004080;
            font-weight: 500;
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
                    <a href="dashboard.php" class="nav-link">
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
                    <a href="reports.php" class="nav-link active">
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

            <!-- Header Báo cáo -->
            <div class="report-header">
                <h2 style="color: #004080; margin-bottom: 10px;">
                    <i class="fas fa-chart-bar"></i> Báo cáo Thống kê
                </h2>
                <p style="color: #666; margin-bottom: 15px;">Phân tích dữ liệu và hiệu suất kinh doanh</p>
                
                <!-- Bộ lọc -->
                <form method="GET" class="filter-form">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Từ ngày</label>
                        <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Đến ngày</label>
                        <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" 
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    </div>
                    
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="search-btn" style="padding: 10px 20px; height: fit-content;">
                            <i class="fas fa-filter"></i> Lọc dữ liệu
                        </button>
                        <a href="reports.php" class="btn-cancel" style="padding: 10px 20px; text-decoration: none;">Xóa lọc</a>
                    </div>
                </form>
            </div>

            <!-- Khoảng thời gian -->
            <div class="date-range">
                <i class="fas fa-calendar-alt"></i> 
                Báo cáo từ <?php echo date('d/m/Y', strtotime($startDate)); ?> đến <?php echo date('d/m/Y', strtotime($endDate)); ?>
            </div>

            <!-- Thống kê tổng quan -->
            <div class="summary-cards">
                <div class="summary-card revenue">
                    <div class="summary-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="summary-value">
                        <?php echo number_format($summaryStats['total_revenue'] ?? 0, 0, ',', '.'); ?>đ
                    </div>
                    <div class="summary-label">Tổng doanh thu</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #888;">
                        <?php echo $summaryStats['total_orders'] ?? 0; ?> đơn hàng đã giao
                    </div>
                </div>
                
                <div class="summary-card orders">
                    <div class="summary-icon orders">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="summary-value">
                        <?php echo number_format($summaryStats['total_orders'] ?? 0); ?>
                    </div>
                    <div class="summary-label">Tổng đơn hàng</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #888;">
                        Đã giao trong khoảng thời gian
                    </div>
                </div>
                
                <div class="summary-card avg-order">
                    <div class="summary-icon avg-order">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="summary-value">
                        <?php echo number_format($summaryStats['avg_order_value'] ?? 0, 0, ',', '.'); ?>đ
                    </div>
                    <div class="summary-label">Giá trị đơn trung bình</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #888;">
                        Trung bình mỗi đơn hàng
                    </div>
                </div>
                
                <div class="summary-card categories">
                    <div class="summary-icon categories">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="summary-value">
                        <?php echo count($categoryStats); ?>
                    </div>
                    <div class="summary-label">Thể loại đã bán</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #888;">
                        Phân loại sản phẩm bán được
                    </div>
                </div>
            </div>

            <!-- Doanh thu theo ngày -->
            <div class="report-section">
                <div class="section-title">
                    <span><i class="fas fa-chart-line"></i> Doanh thu theo ngày</span>
                </div>
                
                <?php if (!empty($dailyStats)): ?>
                    <div class="chart-container">
                        <div style="height: 250px; display: flex; align-items: flex-end; gap: 20px; padding: 20px 0; overflow-x: auto;">
                            <?php foreach ($dailyStats as $stat): 
                                $date = date('d/m', strtotime($stat['order_date']));
                                $maxRevenue = max(array_column($dailyStats, 'daily_revenue'));
                                $height = $stat['daily_revenue'] > 0 ? min(($stat['daily_revenue'] / $maxRevenue) * 200, 200) : 5;
                                $color = $stat['daily_revenue'] > 0 ? '#2ecc71' : '#e0e0e0';
                            ?>
                            <div style="display: flex; flex-direction: column; align-items: center; min-width: 50px;">
                                <div style="width: 30px; background: linear-gradient(to top, <?php echo $color; ?>, #27ae60); height: <?php echo $height; ?>px; border-radius: 3px; position: relative;">
                                    <div style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 11px; color: #333; font-weight: bold;">
                                        <?php echo number_format($stat['daily_revenue']/1000000, 1); ?>M
                                    </div>
                                </div>
                                <div style="margin-top: 10px; font-size: 12px; color: #666;"><?php echo $date; ?></div>
                                <div style="font-size: 11px; color: #888;"><?php echo $stat['daily_orders']; ?> đơn</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-chart-line"></i>
                        <p>Không có dữ liệu doanh thu trong khoảng thời gian này</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Thống kê chi tiết -->
            <div class="stat-grid">
                <!-- Theo thể loại -->
                <div class="report-section">
                    <div class="section-title">
                        <span><i class="fas fa-tags"></i> Doanh thu theo thể loại</span>
                    </div>
                    
                    <?php if (!empty($categoryStats)): ?>
                        <div class="category-list">
                            <?php foreach ($categoryStats as $category): ?>
                            <div class="category-item">
                                <div class="category-name"><?php echo htmlspecialchars($category['TheLoai']); ?></div>
                                <div class="category-stats">
                                    <span><?php echo number_format($category['category_revenue'], 0, ',', '.'); ?>đ</span>
                                    <span><?php echo $category['total_sold']; ?> sản phẩm</span>
                                </div>
                                <div style="margin-top: 8px; height: 5px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                    <?php 
                                    $totalCategoryRevenue = array_sum(array_column($categoryStats, 'category_revenue'));
                                    $width = $totalCategoryRevenue > 0 ? ($category['category_revenue'] / $totalCategoryRevenue) * 100 : 0;
                                    ?>
                                    <div style="width: <?php echo $width; ?>%; height: 100%; background: linear-gradient(90deg, #004080, #005bb5);"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data" style="padding: 20px;">
                            <i class="fas fa-tags"></i>
                            <p>Không có dữ liệu thể loại</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top sản phẩm -->
                <div class="report-section">
                    <div class="section-title">
                        <span><i class="fas fa-crown"></i> Top sản phẩm bán chạy</span>
                    </div>
                    
                    <?php if (!empty($topProducts)): ?>
                        <table class="product-table">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th>Đã bán</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; ?>
                                <?php foreach ($topProducts as $product): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-weight: bold; color: #004080;"><?php echo $rank++; ?>.</span>
                                            <img src="../<?php echo htmlspecialchars($product['AnhSP']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['TenSP']); ?>"
                                                 class="product-image"
                                                 onerror="this.src='../uploads/products/default.jpg'">
                                            <div>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($product['TenSP']); ?></div>
                                                <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($product['TheLoai']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight: bold; color: #004080;">
                                        <?php echo number_format($product['total_sold']); ?>
                                    </td>
                                    <td style="font-weight: bold; color: #2ecc71;">
                                        <?php echo number_format($product['total_revenue'], 0, ',', '.'); ?>đ
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data" style="padding: 20px;">
                            <i class="fas fa-box-open"></i>
                            <p>Không có dữ liệu sản phẩm</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thống kê khác -->
            <div class="stat-grid">
                <!-- Theo phương thức thanh toán -->
                <div class="report-section">
                    <div class="section-title">
                        <span><i class="fas fa-credit-card"></i> Theo phương thức thanh toán</span>
                    </div>
                    
                    <?php if (!empty($paymentStats)): ?>
                        <div style="margin-top: 10px;">
                            <?php foreach ($paymentStats as $payment): 
                                $paymentText = $payment['PhuongThucThanhToan'] == 'cod' ? 'Thanh toán khi nhận hàng' : 'QR Code';
                            ?>
                            <div class="stat-item">
                                <div class="stat-label"><?php echo $paymentText; ?></div>
                                <div class="stat-value">
                                    <?php echo number_format($payment['total_revenue'], 0, ',', '.'); ?>đ
                                    <span class="stat-percent">
                                        <?php echo $payment['order_count']; ?> đơn
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data" style="padding: 20px;">
                            <i class="fas fa-credit-card"></i>
                            <p>Không có dữ liệu thanh toán</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Theo trạng thái đơn hàng -->
                <div class="report-section">
                    <div class="section-title">
                        <span><i class="fas fa-clipboard-check"></i> Theo trạng thái đơn hàng</span>
                    </div>
                    
                    <?php if (!empty($statusStats)): ?>
                        <div style="margin-top: 10px;">
                            <?php foreach ($statusStats as $status): ?>
                            <div class="stat-item">
                                <div class="stat-label"><?php echo htmlspecialchars($status['TrangThai']); ?></div>
                                <div class="stat-value">
                                    <?php echo $status['order_count']; ?> đơn
                                    <?php if ($status['total_value'] > 0): ?>
                                        <span style="color: #888; font-size: 12px;">
                                            (<?php echo number_format($status['total_value'], 0, ',', '.'); ?>đ)
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data" style="padding: 20px;">
                            <i class="fas fa-clipboard-list"></i>
                            <p>Không có dữ liệu trạng thái</p>
                        </div>
                    <?php endif; ?>
                </div>
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
            
            // Đặt ngày mặc định
            const today = new Date().toISOString().split('T')[0];
            const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
            
            // Đảm bảo ngày kết thúc không lớn hơn hôm nay
            const endDateInput = document.querySelector('input[name="end_date"]');
            if (endDateInput) {
                endDateInput.max = today;
            }
            
            // Đảm bảo ngày bắt đầu không lớn hơn ngày kết thúc
            const startDateInput = document.querySelector('input[name="start_date"]');
            if (startDateInput && endDateInput) {
                startDateInput.max = endDateInput.value;
                endDateInput.min = startDateInput.value;
                
                startDateInput.addEventListener('change', function() {
                    endDateInput.min = this.value;
                });
                
                endDateInput.addEventListener('change', function() {
                    startDateInput.max = this.value;
                });
            }
        });
        
        // Logout function
        function logout() {
            if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>
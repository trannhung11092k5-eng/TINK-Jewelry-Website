<?php
// orders.php - Trang quản lý đơn hàng
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

// ==================== HÀM TẠO MÃ ĐH TỰ ĐỘNG ====================
function generateOrderCode($pdo) {
    // Sử dụng ORDER BY CAST để sắp xếp đúng số thứ tự
    $sql = "SELECT MaDonHang 
            FROM DONHANG 
            WHERE MaDonHang LIKE 'DH%' 
            ORDER BY CAST(SUBSTRING(MaDonHang, 3) AS UNSIGNED) DESC 
            LIMIT 1";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    
    if (!empty($result['MaDonHang'])) {
        $lastNumber = intval(substr($result['MaDonHang'], 2));
        $newNumber = $lastNumber + 1;
        return 'DH' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    } else {
        return 'DH00001';
    }
}

// Xử lý cập nhật trạng thái
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $maDonHang = $_POST['ma_dh'] ?? '';
    $trangThai = $_POST['trang_thai'] ?? '';
    
    try {
        $sql = "UPDATE DONHANG SET TrangThai = ? WHERE MaDonHang = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$trangThai, $maDonHang]);
        
        $_SESSION['message'] = "Cập nhật trạng thái thành công!";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: orders.php');
    exit();
}

// Xử lý xóa đơn hàng
if (isset($_GET['delete'])) {
    $maDonHang = $_GET['delete'];
    
    try {
        $sql = "DELETE FROM DONHANG WHERE MaDonHang = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$maDonHang]);
        
        $_SESSION['message'] = "Xóa đơn hàng thành công!";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: orders.php');
    exit();
}

// Lấy danh sách đơn hàng với thông tin khách hàng
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (dh.MaDonHang LIKE ? OR kh.TenKH LIKE ? OR kh.Gmail LIKE ? OR kh.SoDienThoai LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

// Sửa phần lọc trạng thái cho đúng với enum tiếng Việt
if (!empty($statusFilter) && $statusFilter !== 'all') {
    $where .= " AND dh.TrangThai = ?";
    $params[] = $statusFilter;
}

try {
    $sql = "SELECT dh.*, kh.TenKH, kh.Gmail, kh.SoDienThoai, kh.DiaChi 
            FROM DONHANG dh 
            LEFT JOIN KHACHHANG kh ON dh.KhachHangID = kh.KhachHangID 
            $where 
            ORDER BY dh.NgayTao DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    $_SESSION['message'] = "Lỗi khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Lấy danh sách khách hàng cho dropdown
try {
    $sql = "SELECT KhachHangID, TenKH FROM KHACHHANG ORDER BY TenKH";
    $stmt = $pdo->query($sql);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
}

// Lấy đơn hàng theo ID để xem chi tiết
$orderDetail = null;
$orderProducts = [];
if (isset($_GET['view'])) {
    try {
        $sql = "SELECT dh.*, kh.TenKH, kh.Gmail, kh.SoDienThoai, kh.DiaChi 
                FROM DONHANG dh 
                LEFT JOIN KHACHHANG kh ON dh.KhachHangID = kh.KhachHangID 
                WHERE dh.MaDonHang = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['view']]);
        $orderDetail = $stmt->fetch();
        
        // Lấy chi tiết sản phẩm từ CHITIET_DONHANG
        if ($orderDetail) {
            $sql = "SELECT ctdh.*, sp.TenSP, sp.AnhSP 
                    FROM CHITIET_DONHANG ctdh 
                    LEFT JOIN SANPHAM sp ON ctdh.MaSP = sp.MaSP 
                    WHERE ctdh.MaDonHang = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['view']]);
            $orderProducts = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi khi tải thông tin đơn hàng: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Đơn hàng - Admin</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../public/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .status-confirmed {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #90caf9;
        }
        
        .status-delivering {
            background-color: #fff3e0;
            color: #f57c00;
            border: 1px solid #ffb74d;
        }
        
        .status-delivered {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #81c784;
        }
        
        .status-cancelled {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ef9a9a;
        }
        
        .payment-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        
        .payment-cod {
            background-color: #fff3e0;
            color: #e65100;
            border: 1px solid #ffb74d;
        }
        
        .payment-qr {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
        }
        
        .product-detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #fafafa;
        }
        
        .product-detail-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .product-detail-info {
            flex: 1;
        }
        
        .product-detail-info strong {
            display: block;
            margin-bottom: 5px;
            color: #004080;
        }
        
        .product-detail-info small {
            color: #666;
            display: block;
            margin-top: 5px;
        }
        
        .filter-container {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-label {
            font-weight: 600;
            color: #004080;
            white-space: nowrap;
        }
        
        .filter-select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            min-width: 180px;
            cursor: pointer;
        }
        
        .customer-info {
            line-height: 1.5;
        }
        
        .customer-info strong {
            display: block;
            margin-bottom: 3px;
        }
        
        .customer-info small {
            color: #666;
            font-size: 12px;
        }
        
        .order-detail-modal .modal-content {
            max-width: 800px;
        }
        
        .order-detail-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .order-detail-section:last-child {
            border-bottom: none;
        }
        
        .order-detail-section h3 {
            color: #004080;
            margin-bottom: 15px;
            font-size: 18px;
            padding-bottom: 5px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .info-value {
            font-size: 15px;
            color: #333;
        }
        
        .order-id {
            font-weight: 700;
            color: #004080;
            letter-spacing: 0.3px;
        }
        
        /* Thanh trượt ngang cho bảng */
        .table-wrapper {
            overflow-x: auto;
            max-height: none;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            position: relative;
            margin: 0 -5px;
            padding: 0 5px;
        }
        
        .table-wrapper::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        
        .table-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #004080, #005bb5);
            border-radius: 10px;
            border: 2px solid #f1f1f1;
        }
        
        .table-wrapper::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #003366, #004c99);
        }
        
        /* For Firefox */
        .table-wrapper {
            scrollbar-width: thin;
            scrollbar-color: #004080 #f1f1f1;
        }
        
        .management-table {
            width: 100%;
            min-width: 1000px;
            table-layout: auto;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        /* Đảm bảo các cột có chiều rộng tối thiểu */
        .management-table th:nth-child(1),
        .management-table td:nth-child(1) {
            min-width: 120px;
        }
        
        .management-table th:nth-child(2),
        .management-table td:nth-child(2) {
            min-width: 200px;
        }
        
        .management-table th:nth-child(3),
        .management-table td:nth-child(3) {
            min-width: 140px;
        }
        
        .management-table th:nth-child(4),
        .management-table td:nth-child(4) {
            min-width: 120px;
        }
        
        .management-table th:nth-child(5),
        .management-table td:nth-child(5) {
            min-width: 180px;
        }
        
        .management-table th:nth-child(6),
        .management-table td:nth-child(6) {
            min-width: 120px;
        }
        
        .management-table th:nth-child(7),
        .management-table td:nth-child(7) {
            min-width: 150px;
        }
        
        .table-scroll-indicator {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            padding: 5px;
            display: none;
        }
        
        @media (max-width: 1200px) {
            .table-scroll-indicator {
                display: block;
            }
        }
        
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .table-wrapper {
                margin: 0 -15px;
                padding: 0 15px;
            }
            
            .table-wrapper::-webkit-scrollbar {
                height: 6px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="logo-section">
            <div class="logo">TINK Jewerly </div>
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
                    <a href="orders.php" class="nav-link active">
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
            <!-- Management Header -->
            <div class="management-header">
                <div class="management-topbar">
                    <h2 class="page-title"></i> Quản lý Đơn hàng</h2>
                    <div class="management-tools">
                        <form method="GET" class="search-form" style="display: flex; gap: 10px; align-items: center; flex: 1;">
                            <input type="text" name="search" placeholder="Tìm theo mã đơn, tên khách hàng..." 
                                   value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
                            <select name="status" class="filter-select" style="min-width: 180px;" onchange="this.form.submit()">
                                <option value="all" <?php echo empty($statusFilter) || $statusFilter === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                                <option value="Xác nhận" <?php echo $statusFilter == 'Xác nhận' ? 'selected' : ''; ?>>Xác nhận</option>
                                <option value="Đang giao" <?php echo $statusFilter == 'Đang giao' ? 'selected' : ''; ?>>Đang giao</option>
                                <option value="Đã giao" <?php echo $statusFilter == 'Đã giao' ? 'selected' : ''; ?>>Đã giao</option>
                                <option value="Đã hủy" <?php echo $statusFilter == 'Đã hủy' ? 'selected' : ''; ?>>Đã hủy</option>
                            </select>
                            <?php if (!empty($statusFilter) && $statusFilter !== 'all'): ?>
                                <a href="orders.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" 
                                   class="btn-cancel" style="padding: 8px 15px; text-decoration: none; white-space: nowrap;">Xóa bộ lọc</a>
                            <?php endif; ?>
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

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

            <!-- Orders Table -->
            <div class="management-container">
                <?php if (empty($orders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart empty-icon"></i>
                        <h3>Không tìm thấy đơn hàng nào trong hệ thống</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="management-table">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Ngày tạo</th>
                                    <th>Tổng tiền</th>
                                    <th>Phương thức thanh toán</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <?php
                                    // Xác định class và text hiển thị cho trạng thái
                                    $statusClass = '';
                                    $statusText = htmlspecialchars($order['TrangThai'] ?? 'Xác nhận');
                                    switch($order['TrangThai']) {
                                        case 'Xác nhận': 
                                            $statusClass = 'status-confirmed'; 
                                            break;
                                        case 'Đang giao': 
                                            $statusClass = 'status-delivering'; 
                                            break;
                                        case 'Đã giao': 
                                            $statusClass = 'status-delivered'; 
                                            break;
                                        case 'Đã hủy': 
                                            $statusClass = 'status-cancelled'; 
                                            break;
                                        default: 
                                            $statusClass = 'status-confirmed'; 
                                            break;
                                    }
                                    
                                    // Xác định class và text hiển thị cho phương thức thanh toán
                                    $paymentMethod = $order['PhuongThucThanhToan'] ?? 'cod';
                                    $paymentClass = '';
                                    $paymentText = '';
                                    switch($paymentMethod) {
                                        case 'cod': 
                                            $paymentClass = 'payment-cod'; 
                                            $paymentText = 'Thanh toán khi nhận hàng'; 
                                            break;
                                        case 'qr': 
                                            $paymentClass = 'payment-qr'; 
                                            $paymentText = 'QR Code'; 
                                            break;
                                        default: 
                                            $paymentClass = 'payment-cod'; 
                                            $paymentText = 'Thanh toán khi nhận hàng'; 
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td class="order-id">
                                        <?php echo htmlspecialchars($order['MaDonHang']); ?>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <strong><?php echo htmlspecialchars($order['TenKH'] ?? 'Khách lẻ'); ?></strong>
                                            <?php if (!empty($order['Gmail'])): ?>
                                                <small><?php echo htmlspecialchars($order['Gmail']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($order['SoDienThoai'])): ?>
                                                <small>Tel: <?php echo htmlspecialchars($order['SoDienThoai']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($order['NgayTao'])); ?>
                                    </td>
                                    <td style="font-weight: 600; color: #b8860b;">
                                        <?php echo number_format($order['TongTien'] ?? 0, 0, ',', '.'); ?>đ
                                    </td>
                                    <td>
                                        <span class="payment-badge <?php echo $paymentClass; ?>">
                                            <?php echo htmlspecialchars($paymentText); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Nút Xem chi tiết -->
                                            <button class="edit-btn" onclick="viewOrderDetail('<?php echo $order['MaDonHang']; ?>')" title="Xem chi tiết">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Nút Thay đổi trạng thái -->
                                            <?php 
                                            $canChangeStatus = !in_array($order['TrangThai'], ['Đã hủy', 'Đã giao']);
                                            if ($canChangeStatus): 
                                            ?>
                                            <button class="edit-btn" onclick="openStatusModal('<?php echo $order['MaDonHang']; ?>', '<?php echo htmlspecialchars($order['TrangThai']); ?>')" title="Thay đổi trạng thái">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <?php else: ?>
                                            <button class="edit-btn disabled-btn" disabled title="Không thể thay đổi trạng thái đơn hàng <?php echo htmlspecialchars($order['TrangThai']); ?>">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <!-- Nút Xóa -->
                                            <button class="delete-btn" onclick="confirmDelete('<?php echo $order['MaDonHang']; ?>', '<?php echo htmlspecialchars($order['MaDonHang']); ?>')" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Thêm indicator cho thanh cuộn -->
                    <div class="table-scroll-indicator">
                        <i class="fas fa-arrows-alt-h"></i> Kéo sang ngang để xem thêm
                    </div>
                    <div class="table-summary">
                        <p>Tổng cộng: <?php echo count($orders); ?> đơn hàng</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Xem chi tiết đơn hàng -->
    <?php if ($orderDetail): ?>
    <div id="detailModal" class="modal order-detail-modal" style="display: flex;">
        <div class="modal-content">
            <span class="close" onclick="closeDetailModal()">&times;</span>
            <h2 style="color: #004080; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-file-invoice"></i> Chi tiết đơn hàng
            </h2>
            
            <div style="margin-bottom: 15px; padding: 10px 15px; background: #e6f3ff; border-radius: 8px;">
                <p style="color: #004080; font-weight: 600; margin: 0;">
                    <i class="fas fa-shopping-cart"></i> Mã đơn hàng: <?php echo $orderDetail['MaDonHang']; ?>
                </p>
            </div>
            
            <div class="order-detail-section">
                <h3>Thông tin đơn hàng</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Mã đơn hàng</div>
                        <div class="info-value"><?php echo $orderDetail['MaDonHang']; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Ngày tạo</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($orderDetail['NgayTao'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Trạng thái</div>
                        <div class="info-value">
                            <?php 
                            $statusClass = '';
                            $statusText = htmlspecialchars($orderDetail['TrangThai'] ?? 'Xác nhận');
                            switch($orderDetail['TrangThai']) {
                                case 'Xác nhận': 
                                    $statusClass = 'status-confirmed'; 
                                    break;
                                case 'Đang giao': 
                                    $statusClass = 'status-delivering'; 
                                    break;
                                case 'Đã giao': 
                                    $statusClass = 'status-delivered'; 
                                    break;
                                case 'Đã hủy': 
                                    $statusClass = 'status-cancelled'; 
                                    break;
                                default: 
                                    $statusClass = 'status-confirmed'; 
                                    break;
                            }
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo $statusText; ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Phương thức thanh toán</div>
                        <div class="info-value">
                            <?php 
                            $paymentMethod = $orderDetail['PhuongThucThanhToan'] ?? 'cod';
                            $paymentClass = '';
                            $paymentText = '';
                            switch($paymentMethod) {
                                case 'cod': 
                                    $paymentClass = 'payment-cod'; 
                                    $paymentText = 'Thanh toán khi nhận hàng'; 
                                    break;
                                case 'qr': 
                                    $paymentClass = 'payment-qr'; 
                                    $paymentText = 'QR Code'; 
                                    break;
                                default: 
                                    $paymentClass = 'payment-cod'; 
                                    $paymentText = 'Thanh toán khi nhận hàng'; 
                                    break;
                            }
                            ?>
                            <span class="payment-badge <?php echo $paymentClass; ?>">
                                <?php echo htmlspecialchars($paymentText); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Tổng tiền</div>
                        <div class="info-value" style="font-weight: 600; color: #b8860b; font-size: 18px;">
                            <?php echo number_format($orderDetail['TongTien'] ?? 0, 0, ',', '.'); ?>đ
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($orderProducts)): ?>
            <div class="order-detail-section">
                <h3>Sản phẩm trong đơn hàng</h3>
                <?php foreach ($orderProducts as $product): ?>
                <div class="product-detail-item">
                    <?php if (!empty($product['AnhSP'])): ?>
                        <img src="../<?php echo htmlspecialchars($product['AnhSP']); ?>" alt="<?php echo htmlspecialchars($product['TenSP'] ?? ''); ?>">
                    <?php else: ?>
                        <img src="../public/assets/images/no-image.jpg" alt="No image">
                    <?php endif; ?>
                    <div class="product-detail-info">
                        <strong><?php echo htmlspecialchars($product['TenSP'] ?? 'Sản phẩm không xác định'); ?></strong>
                        <small>Mã SP: <?php echo htmlspecialchars($product['MaSP']); ?></small>
                        <small>Số lượng: <?php echo htmlspecialchars($product['SoLuong']); ?> | Giá: <?php echo number_format($product['GiaBan'] ?? 0, 0, ',', '.'); ?>đ</small>
                        <small style="color: #004080; font-weight: 600;">Thành tiền: <?php echo number_format(($product['SoLuong'] ?? 0) * ($product['GiaBan'] ?? 0), 0, ',', '.'); ?>đ</small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="order-detail-section">
                <h3>Thông tin khách hàng</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Họ tên</div>
                        <div class="info-value"><?php echo htmlspecialchars($orderDetail['TenKH'] ?? 'Khách lẻ'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($orderDetail['Gmail'] ?? 'Chưa cập nhật'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Điện thoại</div>
                        <div class="info-value"><?php echo htmlspecialchars($orderDetail['SoDienThoai'] ?? 'Chưa cập nhật'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Địa chỉ</div>
                        <div class="info-value"><?php echo htmlspecialchars($orderDetail['DiaChi'] ?? 'Chưa cập nhật'); ?></div>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                <button type="button" class="btn-cancel" onclick="closeDetailModal()">Đóng</button>
                <?php 
                $canChangeStatusDetail = !in_array($orderDetail['TrangThai'] ?? '', ['Đã hủy', 'Đã giao']);
                if ($canChangeStatusDetail): 
                ?>
                <button type="button" class="btn-update" onclick="openStatusModal('<?php echo $orderDetail['MaDonHang']; ?>', '<?php echo htmlspecialchars($orderDetail['TrangThai']); ?>')">
                    <i class="fas fa-exchange-alt"></i> Thay đổi trạng thái
                </button>
                <?php else: ?>
                <button type="button" class="btn-update disabled-btn" disabled title="Không thể thay đổi trạng thái đơn hàng <?php echo htmlspecialchars($orderDetail['TrangThai'] ?? ''); ?>">
                    <i class="fas fa-exchange-alt"></i> Thay đổi trạng thái
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Thay đổi trạng thái -->
    <div id="statusModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <span class="close" onclick="closeStatusModal()">&times;</span>
            <h2 style="color: #004080; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-exchange-alt"></i> Thay đổi trạng thái đơn hàng
            </h2>
            
            <form id="statusForm" method="POST" action="orders.php">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" id="status_ma_dh" name="ma_dh">
                
                <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <p style="margin: 0; font-weight: 600;">
                        Đơn hàng: <span id="statusOrderCode" style="color: #004080;"></span>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #666;">
                        Trạng thái hiện tại: <span id="currentStatus" class="status-badge"></span>
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="status_trang_thai">Chọn trạng thái mới *</label>
                    <select id="status_trang_thai" name="trang_thai" required style="pointer-events: auto;">
                        <option value="">-- Chọn trạng thái --</option>
                        <option value="Xác nhận">Xác nhận</option>
                        <option value="Đang giao">Đang giao</option>
                        <option value="Đã giao">Đã giao</option>
                        <option value="Đã hủy">Đã hủy</option>
                    </select>
                    <div id="statusError" style="color: #f44336; font-size: 13px; margin-top: 5px; display: none;"></div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                    <button type="button" class="btn-cancel" onclick="closeStatusModal()">Hủy</button>
                    <button type="submit" class="btn-update">
                        <i class="fas fa-sync-alt"></i> Cập nhật trạng thái
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Xác nhận xóa -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 420px;">
            <h3 style="color: #f44336; margin-bottom: 15px;">
                <i class="fas fa-exclamation-triangle"></i> Xác nhận xóa
            </h3>
            <p>Bạn có chắc chắn muốn xóa đơn hàng <span id="orderCode" style="font-weight: bold; color: #004080;"></span>?</p>
            <p style="color: #f44336; font-size: 14px; margin-top: 10px;">Hành động này không thể hoàn tác.</p>
            
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Hủy</button>
                <button type="button" class="btn-delete" onclick="deleteOrder()" style="background-color: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-trash"></i> Xác nhận xóa
                </button>
            </div>
        </div>
    </div>

    <script src="../public/assets/js/script.js"></script>
    <script src="https://kit.fontawesome.com/a2e0b2b9f5.js" crossorigin="anonymous"></script>
    <script>
        let orderToDelete = '';
        
        function viewOrderDetail(maDH) {
            console.log('Viewing order detail:', maDH);
            window.location.href = 'orders.php?view=' + encodeURIComponent(maDH);
        }
        
        function closeDetailModal() {
            console.log('Closing detail modal');
            window.location.href = 'orders.php';
        }
        
        function openStatusModal(maDH, currentStatus) {
            // Kiểm tra nếu đơn hàng đã hủy hoặc đã giao thì không cho phép thay đổi
            if (currentStatus === 'Đã hủy' || currentStatus === 'Đã giao') {
                alert('Không thể thay đổi trạng thái đơn hàng ở trạng thái "' + currentStatus + '".');
                return;
            }
            
            console.log('Opening status modal for:', maDH, currentStatus);
            document.getElementById('status_ma_dh').value = maDH;
            document.getElementById('statusOrderCode').textContent = maDH;
            
            // Map trạng thái hiển thị
            const statusClass = getStatusClass(currentStatus);
            const currentStatusEl = document.getElementById('currentStatus');
            currentStatusEl.textContent = currentStatus;
            currentStatusEl.className = 'status-badge ' + statusClass;
            
            // Ẩn lỗi nếu có
            document.getElementById('statusError').style.display = 'none';
            document.getElementById('statusError').textContent = '';
            
            // Set the select value to current status
            document.getElementById('status_trang_thai').value = currentStatus;
            
            document.getElementById('statusModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeStatusModal() {
            console.log('Closing status modal');
            document.getElementById('statusModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('statusError').style.display = 'none';
        }
        
        function getStatusClass(status) {
            switch(status) {
                case 'Xác nhận': return 'status-confirmed';
                case 'Đang giao': return 'status-delivering';
                case 'Đã giao': return 'status-delivered';
                case 'Đã hủy': return 'status-cancelled';
                default: return 'status-confirmed';
            }
        }
        
        function confirmDelete(maDH, orderCode) {
            console.log('Confirm delete:', maDH);
            orderToDelete = maDH;
            document.getElementById('orderCode').textContent = '"' + orderCode + '"';
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            console.log('Closing delete modal');
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            orderToDelete = '';
        }
        
        function deleteOrder() {
            console.log('Deleting order:', orderToDelete);
            if (orderToDelete) {
                window.location.href = 'orders.php?delete=' + encodeURIComponent(orderToDelete);
            }
        }
        
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
            
            // Auto show detail modal if needed
            <?php if (isset($_GET['view']) && $orderDetail): ?>
                console.log('Auto showing detail modal');
                document.getElementById('detailModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            <?php endif; ?>
            
            // Add scroll event listener for table indicator
            const tableWrapper = document.querySelector('.table-wrapper');
            const indicator = document.querySelector('.table-scroll-indicator');
            
            if (tableWrapper && indicator) {
                tableWrapper.addEventListener('scroll', function() {
                    // Hide indicator when user starts scrolling
                    if (this.scrollLeft > 10) {
                        indicator.style.opacity = '0.5';
                    } else {
                        indicator.style.opacity = '1';
                    }
                });
            }
        });
        
        // Đóng modal khi click bên ngoài
        window.onclick = function(event) {
            console.log('Window click:', event.target);
            if (event.target.classList.contains('modal')) {
                console.log('Closing modal by outside click');
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                if (event.target.id === 'detailModal') {
                    closeDetailModal();
                }
            }
        }
        
        // Đóng modal bằng phím ESC
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                console.log('ESC pressed');
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'flex') {
                        modal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                    }
                });
            }
        });
        
        // Logout function
        function logout() {
            if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Ngăn chặn sự kiện click trong modal lan ra ngoài
        document.querySelectorAll('.modal-content').forEach(modalContent => {
            modalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
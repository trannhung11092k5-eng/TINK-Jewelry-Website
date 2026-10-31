<?php
// customers.php - Trang quản lý khách hàng
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

// Xử lý thêm khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $tenKH = $_POST['ten_kh'] ?? '';
    $gmail = $_POST['gmail'] ?? '';
    $soDienThoai = $_POST['so_dien_thoai'] ?? '';
    $diaChi = $_POST['dia_chi'] ?? '';
    
    try {
        // Kiểm tra Gmail đã tồn tại chưa
        $checkSql = "SELECT KhachHangID FROM KHACHHANG WHERE Gmail = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$gmail]);
        
        if ($checkStmt->rowCount() > 0) {
            $_SESSION['message'] = "Gmail đã tồn tại trong hệ thống!";
            $_SESSION['message_type'] = "error";
        } else {
            // Thêm vào bảng KHACHHANG
            $sql = "INSERT INTO KHACHHANG (TenKH, Gmail, SoDienThoai, DiaChi) 
                    VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenKH, $gmail, $soDienThoai, $diaChi]);
            
            $khachHangID = $pdo->lastInsertId();
            
            // Thêm vào bảng USERS với role 'customer'
            $userSql = "INSERT INTO USERS (Username, Password, Role, KhachHangID, TrangThai) 
                        VALUES (?, NULL, 'customer', ?, 'Hoạt động')";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute([$gmail, $khachHangID]);
            
            $_SESSION['message'] = "Thêm khách hàng thành công!";
            $_SESSION['message_type'] = "success";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: customers.php');
    exit();
}

// Xử lý cập nhật khách hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $khachHangID = $_POST['khach_hang_id'] ?? '';
    $tenKH = $_POST['ten_kh'] ?? '';
    $gmail = $_POST['gmail'] ?? '';
    $soDienThoai = $_POST['so_dien_thoai'] ?? '';
    $diaChi = $_POST['dia_chi'] ?? '';
    $trangThai = $_POST['trang_thai'] ?? 'Hoạt động';
    
    try {
        // Kiểm tra Gmail đã tồn tại cho khách hàng khác
        $checkSql = "SELECT KhachHangID FROM KHACHHANG WHERE Gmail = ? AND KhachHangID != ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$gmail, $khachHangID]);
        
        if ($checkStmt->rowCount() > 0) {
            $_SESSION['message'] = "Gmail đã tồn tại cho khách hàng khác!";
            $_SESSION['message_type'] = "error";
        } else {
            // Cập nhật bảng KHACHHANG
            $sql = "UPDATE KHACHHANG SET TenKH = ?, Gmail = ?, SoDienThoai = ?, 
                    DiaChi = ? WHERE KhachHangID = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenKH, $gmail, $soDienThoai, $diaChi, $khachHangID]);
            
            // Cập nhật bảng USERS
            $userSql = "UPDATE USERS SET Username = ?, TrangThai = ? 
                        WHERE KhachHangID = ? AND Role = 'customer'";
            $userStmt = $pdo->prepare($userSql);
            $userStmt->execute([$gmail, $trangThai, $khachHangID]);
            
            $_SESSION['message'] = "Cập nhật khách hàng thành công!";
            $_SESSION['message_type'] = "success";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: customers.php');
    exit();
}

// Xử lý xóa khách hàng
if (isset($_GET['delete'])) {
    $khachHangID = $_GET['delete'];
    
    try {
        // Kiểm tra xem khách hàng có đơn hàng không
        $checkOrderSql = "SELECT COUNT(*) as order_count FROM DONHANG WHERE KhachHangID = ?";
        $checkStmt = $pdo->prepare($checkOrderSql);
        $checkStmt->execute([$khachHangID]);
        $result = $checkStmt->fetch();
        
        if ($result['order_count'] > 0) {
            $_SESSION['message'] = "Không thể xóa khách hàng vì đã có đơn hàng liên quan!";
            $_SESSION['message_type'] = "error";
        } else {
            // Xóa từ bảng USERS trước (có khóa ngoại)
            $deleteUserSql = "DELETE FROM USERS WHERE KhachHangID = ? AND Role = 'customer'";
            $deleteUserStmt = $pdo->prepare($deleteUserSql);
            $deleteUserStmt->execute([$khachHangID]);
            
            // Xóa từ bảng KHACHHANG
            $sql = "DELETE FROM KHACHHANG WHERE KhachHangID = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$khachHangID]);
            
            $_SESSION['message'] = "Xóa khách hàng thành công!";
            $_SESSION['message_type'] = "success";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: customers.php');
    exit();
}

// Lấy danh sách khách hàng với thông tin từ cả 2 bảng
$search = $_GET['search'] ?? '';
$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (k.TenKH LIKE ? OR k.Gmail LIKE ? OR k.SoDienThoai LIKE ? OR k.DiaChi LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

try {
    // Lấy thông tin từ cả 2 bảng KHACHHANG và USERS
    $sql = "SELECT k.*, u.TrangThai,
            (SELECT COUNT(*) FROM DONHANG d WHERE d.KhachHangID = k.KhachHangID) as SoLuongDonHang
            FROM KHACHHANG k 
            LEFT JOIN USERS u ON k.KhachHangID = u.KhachHangID AND u.Role = 'customer'
            $where 
            ORDER BY k.KhachHangID DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $customers = [];
    $_SESSION['message'] = "Lỗi khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Lấy khách hàng theo ID để sửa
$editCustomer = null;
if (isset($_GET['edit'])) {
    try {
        // Lấy thông tin từ cả 2 bảng
        $sql = "SELECT k.*, u.TrangThai 
                FROM KHACHHANG k 
                LEFT JOIN USERS u ON k.KhachHangID = u.KhachHangID AND u.Role = 'customer'
                WHERE k.KhachHangID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['edit']]);
        $editCustomer = $stmt->fetch();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi khi tải thông tin khách hàng: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Khách hàng - Admin</title>
    
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
        
        .status-active {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #81c784;
        }
        
        .status-locked {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ef9a9a;
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
        
        .order-count {
            background: #e6f3ff;
            color: #004080;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .customer-id {
            font-weight: 700;
            color: #004080;
            letter-spacing: 0.3px;
        }
        
        @media (max-width: 768px) {
            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
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
                    <a href="orders.php" class="nav-link">
                        <i class="fas fa-shopping-cart"></i> Đơn hàng
                    </a>
                </li>
                <li class="nav-item">
                    <a href="customers.php" class="nav-link active">
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
                    <h2 class="page-title"></i> Quản lý Khách hàng</h2>
                    <div class="management-tools">
                        <form method="GET" class="search-form">
                            <input type="text" name="search" placeholder="Tìm theo tên, Gmail, điện thoại..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                        <button class="add-btn" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Thêm khách hàng
                        </button>
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

            <!-- Customers Table -->
            <div class="management-container">
                <?php if (empty($customers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users empty-icon"></i>
                        <h3>Không tìm thấy khách hàng nào trong hệ thống</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="management-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tên khách hàng</th>
                                    <th>Gmail</th>
                                    <th>Số điện thoại</th>
                                    <th>Địa chỉ</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <?php
                                    // Xác định class cho trạng thái
                                    $statusClass = $customer['TrangThai'] == 'Hoạt động' ? 'status-active' : 'status-locked';
                                    $orderCount = $customer['SoLuongDonHang'] ?? 0;
                                ?>
                                <tr>
                                    <td class="customer-id">
                                        #<?php echo htmlspecialchars($customer['KhachHangID']); ?>
                                        <?php if ($orderCount > 0): ?>
                                            <span class="order-count" title="<?php echo $orderCount; ?> đơn hàng"><?php echo $orderCount; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <strong><?php echo htmlspecialchars($customer['TenKH']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($customer['Gmail']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($customer['SoDienThoai']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($customer['DiaChi'] ?: 'Chưa cập nhật'); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($customer['TrangThai']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <!-- Nút Sửa -->
                                            <button class="edit-btn" onclick="openEditModal('<?php echo $customer['KhachHangID']; ?>')" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <!-- Nút Xóa -->
                                            <button class="delete-btn" onclick="confirmDelete('<?php echo $customer['KhachHangID']; ?>', '<?php echo htmlspecialchars($customer['TenKH']); ?>', <?php echo $orderCount; ?>)" title="Xóa">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-summary">
                        <p>Tổng cộng: <?php echo count($customers); ?> khách hàng</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Thêm khách hàng -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2 style="color: #004080; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-plus-circle"></i> Thêm khách hàng mới
            </h2>
            
            <form method="POST" id="addForm">
                <div class="form-group">
                    <label for="ten_kh">Tên khách hàng *</label>
                    <input type="text" id="ten_kh" name="ten_kh" required 
                           placeholder="Nguyễn Văn A">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="gmail">Gmail *</label>
                        <input type="email" id="gmail" name="gmail" required 
                               placeholder="example@gmail.com">
                        <small style="color: #888;">Gmail sẽ được dùng để đăng nhập</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="so_dien_thoai">Số điện thoại</label>
                        <input type="text" id="so_dien_thoai" name="so_dien_thoai" 
                               placeholder="0987654321">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="dia_chi">Địa chỉ</label>
                    <textarea id="dia_chi" name="dia_chi" rows="3" 
                              placeholder="Số nhà, đường, phường/xã, quận/huyện, thành phố"></textarea>
                </div>
                
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <h4 style="color: #004080; margin-bottom: 10px; font-size: 16px;">
                        <i class="fas fa-info-circle"></i> Thông tin tài khoản
                    </h4>
                    <p style="color: #666; font-size: 14px; line-height: 1.5;">
                        Khách hàng sẽ đăng nhập bằng Gmail này.<br>
                        <strong>Tự động tạo tài khoản:</strong> Khi thêm khách hàng, hệ thống sẽ tự động tạo tài khoản đăng nhập với role "customer".
                    </p>
                </div>
                
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Hủy</button>
                    <button type="submit" class="btn-save" name="add_customer">
                        <i class="fas fa-save"></i> Lưu khách hàng
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Sửa khách hàng -->
    <?php if ($editCustomer): ?>
    <div id="editModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="color: #004080; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-edit"></i> Sửa thông tin khách hàng
            </h2>
            
            <form method="POST">
                <input type="hidden" name="khach_hang_id" value="<?php echo $editCustomer['KhachHangID']; ?>">
                
                <div style="margin-bottom: 15px; padding: 10px 15px; background: #e6f3ff; border-radius: 8px;">
                    <p style="color: #004080; font-weight: 600; margin: 0;">
                        <i class="fas fa-id-card"></i> Mã khách hàng: #<?php echo $editCustomer['KhachHangID']; ?>
                    </p>
                </div>
                
                <div class="form-group">
                    <label for="edit_ten_kh">Tên khách hàng *</label>
                    <input type="text" id="edit_ten_kh" name="ten_kh" required 
                           value="<?php echo htmlspecialchars($editCustomer['TenKH']); ?>">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="edit_gmail">Gmail *</label>
                        <input type="email" id="edit_gmail" name="gmail" required 
                               value="<?php echo htmlspecialchars($editCustomer['Gmail']); ?>">
                        <small style="color: #888;">Khách hàng dùng Gmail này để đăng nhập</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_so_dien_thoai">Số điện thoại</label>
                        <input type="text" id="edit_so_dien_thoai" name="so_dien_thoai" 
                               value="<?php echo htmlspecialchars($editCustomer['SoDienThoai']); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_dia_chi">Địa chỉ</label>
                    <textarea id="edit_dia_chi" name="dia_chi" rows="3"><?php echo htmlspecialchars($editCustomer['DiaChi']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_trang_thai">Trạng thái tài khoản *</label>
                    <select id="edit_trang_thai" name="trang_thai" required>
                        <option value="Hoạt động" <?php echo $editCustomer['TrangThai'] == 'Hoạt động' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="Khoá" <?php echo $editCustomer['TrangThai'] == 'Khoá' ? 'selected' : ''; ?>>Khoá</option>
                    </select>
                    <small style="color: #888;">"Khoá" sẽ ngăn khách hàng đăng nhập</small>
                </div>
                
                <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 20px;">
                    <h4 style="color: #004080; margin-bottom: 10px; font-size: 16px;">
                        <i class="fas fa-key"></i> Thông tin đăng nhập
                    </h4>
                    <p style="color: #666; font-size: 14px; line-height: 1.5;">
                        <strong>Lưu ý:</strong> Nếu khách hàng muốn thay đổi Gmail mà không muốn tạo tài khoản mới, 
                        có thể liên hệ tổng đài CSKH (1900-xxxx) để được hỗ trợ.<br>
                        Khách hàng đăng nhập bằng Gmail, mật khẩu sẽ được gửi qua OTP.
                    </p>
                </div>
                
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                    <button type="submit" class="btn-update" name="update_customer">
                        <i class="fas fa-sync-alt"></i> Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Xác nhận xóa -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 420px;">
            <h3 style="color: #f44336; margin-bottom: 15px;">
                <i class="fas fa-exclamation-triangle"></i> Xác nhận xóa
            </h3>
            <p>Bạn có chắc chắn muốn xóa khách hàng <span id="customerName" style="font-weight: bold; color: #004080;"></span>?</p>
            <p id="orderWarning" style="color: #f44336; font-size: 14px;"></p>
            <p style="color: #f44336; font-size: 14px; margin-top: 10px;">Hành động này không thể hoàn tác.</p>
            
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Hủy</button>
                <button type="button" class="btn-delete" onclick="deleteCustomer()" style="background-color: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-trash"></i> Xác nhận xóa
                </button>
            </div>
        </div>
    </div>

    <script src="../public/assets/js/script.js"></script>
    <script src="https://kit.fontawesome.com/a2e0b2b9f5.js" crossorigin="anonymous"></script>
    <script>
        let customerToDelete = '';
        
        function openAddModal() {
            console.log('Opening add modal');
            document.getElementById('addModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeAddModal() {
            console.log('Closing add modal');
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addForm').reset();
        }
        
        function openEditModal(khachHangID) {
            console.log('Opening edit modal for:', khachHangID);
            window.location.href = 'customers.php?edit=' + encodeURIComponent(khachHangID);
        }
        
        function closeEditModal() {
            console.log('Closing edit modal');
            window.location.href = 'customers.php';
        }
        
        function confirmDelete(khachHangID, customerName, orderCount) {
            console.log('Confirm delete:', khachHangID);
            customerToDelete = khachHangID;
            document.getElementById('customerName').textContent = '"' + customerName + '"';
            
            // Hiển thị cảnh báo nếu có đơn hàng
            const orderWarning = document.getElementById('orderWarning');
            if (orderCount > 0) {
                orderWarning.innerHTML = `<i class="fas fa-exclamation-circle"></i> <strong>Cảnh báo:</strong> Khách hàng này có ${orderCount} đơn hàng. Không thể xóa khách hàng đã có đơn hàng!`;
                orderWarning.style.display = 'block';
                
                // Vô hiệu hóa nút xóa
                document.querySelector('.btn-delete').disabled = true;
                document.querySelector('.btn-delete').style.backgroundColor = '#ccc';
                document.querySelector('.btn-delete').style.cursor = 'not-allowed';
            } else {
                orderWarning.innerHTML = '';
                orderWarning.style.display = 'none';
                
                // Kích hoạt nút xóa
                document.querySelector('.btn-delete').disabled = false;
                document.querySelector('.btn-delete').style.backgroundColor = '#f44336';
                document.querySelector('.btn-delete').style.cursor = 'pointer';
            }
            
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            console.log('Closing delete modal');
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            customerToDelete = '';
        }
        
        function deleteCustomer() {
            console.log('Deleting customer:', customerToDelete);
            if (customerToDelete) {
                window.location.href = 'customers.php?delete=' + encodeURIComponent(customerToDelete);
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
            
            // Auto show edit modal if needed
            <?php if (isset($_GET['edit']) && $editCustomer): ?>
                console.log('Auto showing edit modal');
                document.getElementById('editModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            <?php endif; ?>
        });
        
        // Đóng modal khi click bên ngoài
        window.onclick = function(event) {
            console.log('Window click:', event.target);
            if (event.target.classList.contains('modal')) {
                console.log('Closing modal by outside click');
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                if (event.target.id === 'editModal') {
                    closeEditModal();
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
        
        // Kiểm tra dữ liệu form thêm khách hàng
        document.getElementById('addForm')?.addEventListener('submit', function(e) {
            const gmail = document.getElementById('gmail').value;
            const phone = document.getElementById('so_dien_thoai').value;
            
            // Kiểm tra định dạng Gmail
            const gmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!gmailPattern.test(gmail)) {
                e.preventDefault();
                alert('Vui lòng nhập đúng định dạng Gmail!');
                return false;
            }
            
            // Kiểm tra số điện thoại (nếu có)
            if (phone && !/^\d{10,11}$/.test(phone.replace(/\D/g, ''))) {
                e.preventDefault();
                alert('Số điện thoại phải có 10-11 chữ số!');
                return false;
            }
        });
        
        // Kiểm tra dữ liệu form sửa khách hàng
        const editForm = document.querySelector('form[name="update_customer"]');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                const gmail = document.getElementById('edit_gmail').value;
                const phone = document.getElementById('edit_so_dien_thoai').value;
                
                // Kiểm tra định dạng Gmail
                const gmailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!gmailPattern.test(gmail)) {
                    e.preventDefault();
                    alert('Vui lòng nhập đúng định dạng Gmail!');
                    return false;
                }
                
                // Kiểm tra số điện thoại (nếu có)
                if (phone && !/^\d{10,11}$/.test(phone.replace(/\D/g, ''))) {
                    e.preventDefault();
                    alert('Số điện thoại phải có 10-11 chữ số!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
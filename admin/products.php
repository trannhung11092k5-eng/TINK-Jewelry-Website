<?php
// products.php - Trang quản lý sản phẩm
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

// ==================== HÀM TẠO MÃ SP TỰ ĐỘNG ====================
function generateProductCode($pdo) {
    // Lấy mã lớn nhất theo phần số để tránh lỗi sắp xếp chuỗi (SP100 > SP99)
    $sql = "SELECT MaSP 
            FROM SANPHAM 
            WHERE MaSP LIKE 'SP%' 
            ORDER BY CAST(SUBSTRING(MaSP, 3) AS UNSIGNED) DESC 
            LIMIT 1";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch();
    
    if (!empty($result['MaSP'])) {
        $lastNumber = intval(substr($result['MaSP'], 2));
        $newNumber = $lastNumber + 1;
        return 'SP' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
    
    return 'SP001';
}

// Xử lý thêm sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    // Tạo mã SP tự động nếu không nhập
    $maSP = !empty($_POST['ma_sp']) ? trim($_POST['ma_sp']) : generateProductCode($pdo);
    $theLoai = trim($_POST['the_loai'] ?? '');
    $tenSP = trim($_POST['ten_sp'] ?? '');
    $giaBan = is_numeric($_POST['gia_ban'] ?? null) ? (int)$_POST['gia_ban'] : -1;
    $soLuongTon = is_numeric($_POST['so_luong_ton'] ?? null) ? (int)$_POST['so_luong_ton'] : -1;
    
    if (empty($maSP) || empty($theLoai) || empty($tenSP) || $giaBan < 0 || $soLuongTon < 0) {
        $_SESSION['message'] = "Vui lòng nhập đầy đủ thông tin và không để số âm.";
        $_SESSION['message_type'] = "error";
        header('Location: products.php');
        exit();
    }
    
    // Xử lý upload ảnh
    $anhSP = 'uploads/products/default.jpg';
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if (isset($_FILES['anh_sp']) && $_FILES['anh_sp']['error'] === 0) {
        $uploadDir = '../uploads/products/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        if (!in_array($_FILES['anh_sp']['type'], $allowedTypes) || $_FILES['anh_sp']['size'] > $maxSize) {
            $_SESSION['message'] = "Ảnh không hợp lệ (định dạng JPG/PNG/GIF/WEBP, tối đa 5MB).";
            $_SESSION['message_type'] = "error";
            header('Location: products.php');
            exit();
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['anh_sp']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['anh_sp']['tmp_name'], $uploadFile)) {
            $anhSP = 'uploads/products/' . $fileName;
        }
    }
    
    try {
        $sql = "INSERT INTO SANPHAM (MaSP, AnhSP, TheLoai, TenSP, GiaBan, SoLuongTon) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$maSP, $anhSP, $theLoai, $tenSP, $giaBan, $soLuongTon]);
        
        $_SESSION['message'] = "Thêm sản phẩm thành công!";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: products.php');
    exit();
}

// Xử lý cập nhật sản phẩm
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $maSP = trim($_POST['ma_sp'] ?? '');
    $theLoai = trim($_POST['the_loai'] ?? '');
    $tenSP = trim($_POST['ten_sp'] ?? '');
    $giaBan = is_numeric($_POST['gia_ban'] ?? null) ? (int)$_POST['gia_ban'] : -1;
    $soLuongTon = is_numeric($_POST['so_luong_ton'] ?? null) ? (int)$_POST['so_luong_ton'] : -1;
    
    if (empty($maSP) || empty($theLoai) || empty($tenSP) || $giaBan < 0 || $soLuongTon < 0) {
        $_SESSION['message'] = "Dữ liệu không hợp lệ. Kiểm tra lại thông tin sản phẩm.";
        $_SESSION['message_type'] = "error";
        header('Location: products.php');
        exit();
    }
    
    try {
        // Lấy ảnh hiện tại
        $stmt = $pdo->prepare("SELECT AnhSP FROM SANPHAM WHERE MaSP = ?");
        $stmt->execute([$maSP]);
        $currentProduct = $stmt->fetch();
        
        if (!$currentProduct) {
            $_SESSION['message'] = "Không tìm thấy sản phẩm để cập nhật.";
            $_SESSION['message_type'] = "error";
            header('Location: products.php');
            exit();
        }
        
        $anhSP = $currentProduct['AnhSP'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;
        
        // Nếu có upload ảnh mới
        if (isset($_FILES['anh_sp']) && $_FILES['anh_sp']['error'] === 0) {
            if (!in_array($_FILES['anh_sp']['type'], $allowedTypes) || $_FILES['anh_sp']['size'] > $maxSize) {
                $_SESSION['message'] = "Ảnh không hợp lệ (định dạng JPG/PNG/GIF/WEBP, tối đa 5MB).";
                $_SESSION['message_type'] = "error";
                header('Location: products.php');
                exit();
            }
            
            $uploadDir = '../uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['anh_sp']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['anh_sp']['tmp_name'], $uploadFile)) {
                $anhSP = 'uploads/products/' . $fileName;
            } else {
                $_SESSION['message'] = "Không thể lưu ảnh mới, vui lòng thử lại.";
                $_SESSION['message_type'] = "error";
                header('Location: products.php');
                exit();
            }
        }
        
        // Cập nhật thông tin sản phẩm
        $sql = "UPDATE SANPHAM SET AnhSP = ?, TheLoai = ?, TenSP = ?, 
                GiaBan = ?, SoLuongTon = ? WHERE MaSP = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$anhSP, $theLoai, $tenSP, $giaBan, $soLuongTon, $maSP]);
        
        $_SESSION['message'] = "Cập nhật sản phẩm thành công!";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: products.php');
    exit();
}

// Xử lý xóa sản phẩm
if (isset($_GET['delete'])) {
    $maSP = $_GET['delete'];
    
    try {
        $sql = "DELETE FROM SANPHAM WHERE MaSP = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$maSP]);
        
        $_SESSION['message'] = "Xóa sản phẩm thành công!";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: products.php');
    exit();
}

// Lấy danh sách sản phẩm
$search = $_GET['search'] ?? '';
$where = '';
$params = [];

if (!empty($search)) {
    $where = "WHERE MaSP LIKE ? OR TenSP LIKE ? OR TheLoai LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm];
}

try {
    $sql = "SELECT * FROM SANPHAM $where ORDER BY MaSP";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
    $_SESSION['message'] = "Lỗi khi tải dữ liệu: " . $e->getMessage();
    $_SESSION['message_type'] = "error";
}

// Lấy sản phẩm theo ID để sửa
$editProduct = null;
if (isset($_GET['edit'])) {
    try {
        $sql = "SELECT * FROM SANPHAM WHERE MaSP = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_GET['edit']]);
        $editProduct = $stmt->fetch();
    } catch (PDOException $e) {
        $_SESSION['message'] = "Lỗi khi tải thông tin sản phẩm: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Sản phẩm - Admin</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../public/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <a href="products.php" class="nav-link active">
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
            <!-- Management Header -->
            <div class="management-header">
                <div class="management-topbar">
                    <h2 class="page-title"></i> Quản lý Sản phẩm</h2>
                    <div class="management-tools">
                        <form method="GET" class="search-form">
                            <input type="text" name="search" placeholder="Tìm theo mã, tên, thể loại..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                        <button class="add-btn" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Thêm sản phẩm
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

            <!-- Products Table -->
            <div class="management-container">
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open empty-icon"></i>
                        <h3>Không tìm thấy sản phẩm nào trong hệ thống</h3>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper">
                        <table class="management-table">
                            <thead>
                                <tr>
                                    <th>Mã SP</th>
                                    <th class="center">Ảnh</th>
                                    <th>Tên SP</th>
                                    <th>Thể loại</th>
                                    <th>Giá bán</th>
                                    <th>Tồn kho</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <?php
                                    $stockClass = $product['SoLuongTon'] > 10 ? 'stock-high' : ($product['SoLuongTon'] > 0 ? 'stock-medium' : 'stock-low');
                                ?>
                                <tr>
                                    <td class="code-text">
                                        <?php echo htmlspecialchars($product['MaSP']); ?>
                                    </td>
                                    <td class="center">
                                        <div class="product-thumb">
                                            <img src="../<?php echo htmlspecialchars($product['AnhSP']); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['TenSP']); ?>"
                                                 class="product-thumb-img"
                                                 onerror="this.src='../uploads/products/default.jpg'">
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['TenSP']); ?></td>
                                    <td>
                                        <span class="pill pill-info">
                                            <?php echo htmlspecialchars($product['TheLoai']); ?>
                                        </span>
                                    </td>
                                    <td class="price-text">
                                        <?php echo number_format($product['GiaBan'], 0, ',', '.'); ?> đ
                                    </td>
                                    <td>
                                        <span class="stock-pill <?php echo $stockClass; ?>">
                                            <?php echo $product['SoLuongTon']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="edit-btn" onclick="openEditModal('<?php echo $product['MaSP']; ?>')" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="delete-btn" onclick="confirmDelete('<?php echo $product['MaSP']; ?>', '<?php echo htmlspecialchars($product['TenSP']); ?>')" title="Xóa">
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
                        <p>Tổng cộng: <?php echo count($products); ?> sản phẩm</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Thêm sản phẩm -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2 style="color: #004080; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-plus-circle"></i> Thêm sản phẩm mới
            </h2>
            
            <form method="POST" enctype="multipart/form-data" id="addForm">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="ma_sp">Mã sản phẩm *</label>
                        <input type="text" id="ma_sp" name="ma_sp" 
                               placeholder="SP001" 
                               value="<?php echo generateProductCode($pdo); ?>">
                        <small style="color: #888;">Để trống để tự động tạo mã</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="the_loai">Thể loại *</label>
                        <select id="the_loai" name="the_loai" required>
                            <option value="">Chọn thể loại</option>
                            <option value="Vòng tay">Vòng tay</option>
                            <option value="Nhẫn">Nhẫn</option>
                            <option value="Vòng cổ">Vòng cổ</option>
                            <option value="Khuyên tai">Khuyên tai</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ten_sp">Tên sản phẩm *</label>
                    <input type="text" id="ten_sp" name="ten_sp" required 
                           placeholder="Nhập tên sản phẩm">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="gia_ban">Giá bán *</label>
                        <input type="number" id="gia_ban" name="gia_ban" required 
                               min="0" step="1000" placeholder="500000">
                    </div>
                    
                    <div class="form-group">
                        <label for="so_luong_ton">Số lượng tồn *</label>
                        <input type="number" id="so_luong_ton" name="so_luong_ton" required 
                               min="0" placeholder="50">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="anh_sp">Ảnh sản phẩm</label>
                    <div onclick="document.getElementById('anh_sp').click()" style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 10px; margin-top: 10px; cursor: pointer;">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 40px; color: #ccc; margin-bottom: 10px;"></i>
                        <p>Click để chọn ảnh</p>
                        <small style="color: #888;">Định dạng: JPG, PNG, GIF (Max: 5MB)</small>
                        <input type="file" id="anh_sp" name="anh_sp" accept="image/*" style="display: none;" 
                               onchange="previewImage(this, 'addPreview')">
                    </div>
                    <div id="addPreview" style="margin-top: 10px;"></div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Hủy</button>
                    <button type="submit" class="btn-save" name="add_product">
                        <i class="fas fa-save"></i> Lưu sản phẩm
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Sửa sản phẩm -->
    <?php if ($editProduct): ?>
    <div id="editModal" class="modal" style="display: flex;">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="color: #004080; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                <i class="fas fa-edit"></i> Sửa sản phẩm
            </h2>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="ma_sp" value="<?php echo $editProduct['MaSP']; ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Mã sản phẩm</label>
                        <input type="text" value="<?php echo $editProduct['MaSP']; ?>" disabled
                               style="background-color: #f5f5f5; cursor: not-allowed;">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_the_loai">Thể loại *</label>
                        <select id="edit_the_loai" name="the_loai" required>
                            <option value="Vòng tay" <?php echo $editProduct['TheLoai'] == 'Vòng tay' ? 'selected' : ''; ?>>Vòng tay</option>
                            <option value="Nhẫn" <?php echo $editProduct['TheLoai'] == 'Nhẫn' ? 'selected' : ''; ?>>Nhẫn</option>
                            <option value="Vòng cổ" <?php echo $editProduct['TheLoai'] == 'Vòng cổ' ? 'selected' : ''; ?>>Vòng cổ</option>
                            <option value="Khuyên tai" <?php echo $editProduct['TheLoai'] == 'Khuyên tai' ? 'selected' : ''; ?>>Khuyên tai</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_ten_sp">Tên sản phẩm *</label>
                    <input type="text" id="edit_ten_sp" name="ten_sp" required 
                           value="<?php echo htmlspecialchars($editProduct['TenSP']); ?>">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="edit_gia_ban">Giá bán *</label>
                        <input type="number" id="edit_gia_ban" name="gia_ban" required 
                               min="0" step="1000" 
                               value="<?php echo $editProduct['GiaBan']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_so_luong_ton">Số lượng tồn *</label>
                        <input type="number" id="edit_so_luong_ton" name="so_luong_ton" required 
                               min="0" value="<?php echo $editProduct['SoLuongTon']; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Ảnh hiện tại</label>
                    <div style="margin-top: 10px;">
                        <img src="../<?php echo $editProduct['AnhSP']; ?>" 
                             alt="Ảnh hiện tại" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 2px solid #eee;"
                             onerror="this.src='../uploads/products/default.jpg'">
                    </div>
                    
                    <label for="edit_anh_sp" style="margin-top: 15px; display: block;">Thay ảnh mới (nếu muốn)</label>
                    <div onclick="document.getElementById('edit_anh_sp').click()" style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 10px; margin-top: 10px; cursor: pointer;">
                        <i class="fas fa-sync-alt" style="font-size: 40px; color: #ccc; margin-bottom: 10px;"></i>
                        <p>Click để chọn ảnh mới</p>
                        <input type="file" id="edit_anh_sp" name="anh_sp" accept="image/*" style="display: none;"
                               onchange="previewImage(this, 'editPreview')">
                    </div>
                    <div id="editPreview" style="margin-top: 10px;"></div>
                </div>
                
                <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                    <button type="submit" class="btn-update" name="update_product" style="background: linear-gradient(135deg, #004080, #005bb5); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
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
            <p>Bạn có chắc chắn muốn xóa sản phẩm <span id="productName" style="font-weight: bold; color: #004080;"></span>?</p>
            <p style="color: #f44336; font-size: 14px;">Hành động này không thể hoàn tác.</p>
            
            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 12px; margin-top: 25px;">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Hủy</button>
                <button type="button" class="btn-delete" onclick="deleteProduct()" style="background-color: #f44336; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fas fa-trash"></i> Xác nhận xóa
                </button>
            </div>
        </div>
    </div>

    <script src="../public/assets/js/script.js"></script>
    <script src="https://kit.fontawesome.com/a2e0b2b9f5.js" crossorigin="anonymous"></script>
    <script>
        let productToDelete = '';
        function openAddModal() {
            console.log('Opening add modal');
            document.getElementById('addModal').style.display = 'flex';
            document.body.style.overflow = 'hidden'; // Ngăn cuộn nền
        }
        
        function closeAddModal() {
            console.log('Closing add modal');
            document.getElementById('addModal').style.display = 'none';
            document.body.style.overflow = 'auto'; // Khôi phục cuộn
            document.getElementById('addForm').reset();
            document.getElementById('addPreview').innerHTML = '';
        }
        
        function openEditModal(maSP) {
            console.log('Opening edit modal for:', maSP);
            window.location.href = 'products.php?edit=' + encodeURIComponent(maSP);
        }
        
        function closeEditModal() {
            console.log('Closing edit modal');
            window.location.href = 'products.php';
        }
        
        function confirmDelete(maSP, productName) {
            console.log('Confirm delete:', maSP, productName);
            productToDelete = maSP;
            document.getElementById('productName').textContent = '"' + productName + '"';
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            console.log('Closing delete modal');
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            productToDelete = '';
        }
        
        function deleteProduct() {
            console.log('Deleting product:', productToDelete);
            if (productToDelete) {
                window.location.href = 'products.php?delete=' + encodeURIComponent(productToDelete);
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
    });
        // Xem trước ảnh
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '150px';
                    img.style.height = '150px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '8px';
                    img.style.marginTop = '10px';
                    img.style.border = '2px solid #004080';
                    preview.appendChild(img);
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Đóng modal khi click bên ngoài - FIXED
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
        
        // Logout function
        function logout() {
            if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
                window.location.href = 'logout.php';
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
        
        // Kiểm tra dữ liệu form thêm sản phẩm
        document.getElementById('addForm')?.addEventListener('submit', function(e) {
            const giaBan = document.getElementById('gia_ban').value;
            const soLuong = document.getElementById('so_luong_ton').value;
            
            if (giaBan < 0 || soLuong < 0) {
                e.preventDefault();
                alert('Giá bán và số lượng không được âm!');
                return false;
            }
        });
        
        // Ngăn chặn sự kiện click trong modal lan ra ngoài
        document.querySelectorAll('.modal-content').forEach(modalContent => {
            modalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
        
        // Auto show edit modal if needed
        <?php if (isset($_GET['edit']) && $editProduct): ?>
            document.addEventListener('DOMContentLoaded', function() {
                console.log('Auto showing edit modal');
                document.getElementById('editModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        <?php endif; ?>
        
        // Ngăn chặn hành vi mặc định của các nút
        document.querySelectorAll('button').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.type === 'button') {
                    e.preventDefault();
                }
            });
        });
        
        // Debug: Log tất cả sự kiện click
        document.addEventListener('click', function(e) {
            console.log('Click on:', e.target.tagName, e.target.className, e.target.id);
        });
    </script>
</body>
</html>
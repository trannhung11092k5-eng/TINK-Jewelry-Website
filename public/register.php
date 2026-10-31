<?php
// register.php - Đăng ký tài khoản
require_once '../config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tenKH = trim($_POST['ten_kh'] ?? '');
    $gmail = trim($_POST['gmail'] ?? '');
    $soDienThoai = trim($_POST['so_dien_thoai'] ?? '');
    $diaChi = trim($_POST['dia_chi'] ?? '');
    
    // Validation
    if (empty($tenKH) || empty($gmail)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc (Tên khách hàng và Gmail)!';
    } elseif (!filter_var($gmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Gmail không hợp lệ!';
    } else {
        try {
            // Kiểm tra Gmail đã tồn tại chưa
            $checkStmt = $pdo->prepare("SELECT KhachHangID FROM KHACHHANG WHERE Gmail = ?");
            $checkStmt->execute([$gmail]);
            
            if ($checkStmt->rowCount() > 0) {
                $error = 'Gmail này đã được sử dụng. Vui lòng sử dụng Gmail khác hoặc đăng nhập!';
            } else {
                // Bắt đầu transaction
                $pdo->beginTransaction();
                
                // Thêm vào bảng KHACHHANG
                $stmt = $pdo->prepare("INSERT INTO KHACHHANG (TenKH, Gmail, SoDienThoai, DiaChi) VALUES (?, ?, ?, ?)");
                $stmt->execute([$tenKH, $gmail, $soDienThoai, $diaChi]);
                
                $khachHangID = $pdo->lastInsertId();
                
                // Thêm vào bảng USERS với role 'customer'
                $userStmt = $pdo->prepare("INSERT INTO USERS (Username, Password, Role, KhachHangID, TrangThai) VALUES (?, NULL, 'customer', ?, 'Hoạt động')");
                $userStmt->execute([$gmail, $khachHangID]);
                
                $pdo->commit();
                
                // Lưu thông báo thành công vào session để hiển thị ở trang login
                $_SESSION['register_success'] = 'Đăng ký thành công! Vui lòng đăng nhập bằng Gmail đã đăng ký.';
                $_SESSION['registered_gmail'] = $gmail;
                
                // Chuyển hướng đến trang đăng nhập
                header("Location: login.php");
                exit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Có lỗi xảy ra: ' . $e->getMessage();
        }
    }
}
?>
<?php include 'includes/header.php'; ?>

<div style="max-width: 500px; margin: 60px auto; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
    <h2 style="font-family: 'Playfair Display', serif; text-align: center; margin-bottom: 30px; color: #1a237e;">
        <i class="fas fa-user-plus"></i> Đăng ký tài khoản
    </h2>
    
    <?php if ($error): ?>
        <div style="background: #ffebee; color: #c62828; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #c62828;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: #e8f5e9; color: #2e7d32; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #2e7d32;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <p style="margin: 8px 0 0 0; font-size: 14px;">Đang chuyển hướng...</p>
        </div>
    <?php endif; ?>
    
    <?php if (!$success): ?>
    <form method="POST" action="" id="registerForm">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                Tên khách hàng <span style="color: #f44336;">*</span>
            </label>
            <input type="text" name="ten_kh" required 
                   value="<?php echo htmlspecialchars($_POST['ten_kh'] ?? ''); ?>"
                   style="width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;"
                   placeholder="Nhập họ tên của bạn">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                Gmail <span style="color: #f44336;">*</span>
            </label>
            <input type="email" name="gmail" required 
                   value="<?php echo htmlspecialchars($_POST['gmail'] ?? ''); ?>"
                   style="width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;"
                   placeholder="example@gmail.com">
            <small style="color: #666; display: block; margin-top: 5px;">Gmail này sẽ dùng để đăng nhập</small>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                Số điện thoại
            </label>
            <input type="tel" name="so_dien_thoai" 
                   value="<?php echo htmlspecialchars($_POST['so_dien_thoai'] ?? ''); ?>"
                   style="width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;"
                   placeholder="0123456789">
        </div>
        
        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; color: #555; font-weight: 500;">
                Địa chỉ
            </label>
            <textarea name="dia_chi" rows="3"
                      style="width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px; resize: vertical;"
                      placeholder="Nhập địa chỉ của bạn"><?php echo htmlspecialchars($_POST['dia_chi'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px; margin-bottom: 15px;">
            <i class="fas fa-user-plus"></i> Đăng ký
        </button>
    </form>
    <?php endif; ?>
    
    <div style="margin-top: 20px; text-align: center; padding-top: 20px; border-top: 1px solid #eee;">
        <p style="color: #666; margin-bottom: 10px;">Đã có tài khoản?</p>
        <a href="login.php" style="color: #1a237e; text-decoration: none; font-weight: 500;">
            <i class="fas fa-sign-in-alt"></i> Đăng nhập ngay
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>



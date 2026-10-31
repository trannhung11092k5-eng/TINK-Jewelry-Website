<?php
// login.php - Đăng nhập
require_once '../config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit();
}

$error = '';
$success = '';

// Hiển thị thông báo đăng ký thành công nếu có
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
    
    // Pre-fill Gmail nếu có
    $prefillEmail = $_SESSION['registered_gmail'] ?? '';
    if ($prefillEmail) {
        unset($_SESSION['registered_gmail']);
    }
} else {
    $prefillEmail = '';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $gmail = trim($_POST['gmail'] ?? '');
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($gmail)) {
        $error = 'Vui lòng nhập Gmail!';
    } elseif (empty($otp)) {
        $error = 'Vui lòng nhập mã OTP!';
    } else {
            try {
                // Kiểm tra gmail có tồn tại trong KHACHHANG không và kiểm tra trạng thái tài khoản
                $stmt = $pdo->prepare("SELECT k.*, u.TrangThai 
                                       FROM KHACHHANG k 
                                       LEFT JOIN USERS u ON k.KhachHangID = u.KhachHangID AND u.Role = 'customer'
                                       WHERE k.Gmail = ?");
                $stmt->execute([$gmail]);
                $customer = $stmt->fetch();
                
                if ($customer) {
                    // Kiểm tra trạng thái tài khoản
                    $accountStatus = $customer['TrangThai'] ?? 'Hoạt động';
                    if ($accountStatus === 'Khoá' || $accountStatus === 'Khóa') {
                        $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên!';
                    } else {
                        // Giả lập OTP (trong thực tế sẽ gửi gmail/SMS)
                        if ($otp == '123456') { // OTP mặc định cho demo
                            // Tạo session với thông tin khách hàng
                            $_SESSION['user_id'] = $customer['KhachHangID'];
                            $_SESSION['user_name'] = $customer['TenKH'];
                            $_SESSION['user_email'] = $customer['Gmail'];
                            
                            // Chuyển hướng đến homepage
                            $redirect = $_GET['redirect'] ?? 'homepage.php';
                            header("Location: " . htmlspecialchars($redirect));
                            exit();
                        } else {
                            $error = 'Mã OTP không chính xác!';
                        }
                    }
                } else {
                    $error = 'Gmail không tồn tại trong hệ thống!';
                }
            } catch (PDOException $e) {
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
}
?>
<?php include 'includes/header.php'; ?>

<div style="max-width: 400px; margin: 60px auto; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
    <h2 style="font-family: 'Playfair Display', serif; text-align: center; margin-bottom: 30px;">Đăng nhập</h2>
    
    <?php if ($error): ?>
        <div style="background: #ffebee; color: #c62828; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: #e8f5e9; color: #2e7d32; padding: 10px 15px; border-radius: 5px; margin-bottom: 20px;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; color: #555;">Gmail</label>
            <input type="email" name="gmail" required 
                   value="<?php echo htmlspecialchars($prefillEmail); ?>"
                   style="width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;"
                   placeholder="Nhập Gmail của bạn">
        </div>
        
        <div style="margin-bottom: 25px;">
            <label style="display: block; margin-bottom: 8px; color: #555;">Mã OTP</label>
            <input type="text" name="otp" required 
                   style="width: 100%; padding: 12px 15px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;"
                   placeholder="Nhập mã OTP (demo: 123456)">
            <small style="color: #666; display: block; margin-top: 5px;">Mã OTP demo: 123456</small>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px;">
            <i class="fas fa-sign-in-alt"></i> Đăng nhập
        </button>
    </form>
    
    <div style="margin-top: 20px; text-align: center;">
        <p style="color: #666;">Chưa có tài khoản?</p>
        <a href="register.php" style="color: #1a237e; text-decoration: none; font-weight: 500;">
            <i class="fas fa-user-plus"></i> Đăng ký ngay
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
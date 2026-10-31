<?php
// public/ajax/cancel-order.php
header('Content-Type: application/json');
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$maDonHang = trim($input['ma_don_hang'] ?? '');
$userId = $_SESSION['user_id'];

if (!$maDonHang) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đơn hàng.']);
    exit;
}

try {
// Kiểm tra đơn hàng thuộc về user và trạng thái hiện tại
$stmt = $pdo->prepare("SELECT TrangThai FROM DONHANG WHERE MaDonHang = ? AND KhachHangID = ?");
    $stmt->execute([$maDonHang, $userId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
        exit;
    }
    
    $currentStatus = $order['TrangThai'];
    
// Chuẩn hóa trạng thái từ CSDL (có thể lưu cả dạng cũ HOA)
$currentStatus = strtoupper($currentStatus) === 'XAC_NHAN' ? 'Xác nhận' : $currentStatus;

// Chỉ được hủy khi đang ở Xác nhận
if ($currentStatus !== 'Xác nhận') {
    echo json_encode(['success' => false, 'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái Xác nhận.']);
    exit;
}

// Cập nhật trạng thái thành Đã hủy
$stmt = $pdo->prepare("UPDATE DONHANG SET TrangThai = 'Đã hủy' WHERE MaDonHang = ? AND KhachHangID = ?");
    $stmt->execute([$maDonHang, $userId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hủy đơn hàng thành công.'
    ]);
    exit;
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}
?>


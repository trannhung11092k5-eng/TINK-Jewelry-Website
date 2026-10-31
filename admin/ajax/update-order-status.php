<?php
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
$newStatus = trim($input['trang_thai'] ?? '');

if (!$maDonHang || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Thiếu tham số.']);
    exit;
}

$validStatuses = ['Xác nhận', 'Đang giao', 'Đã giao', 'Đã hủy', 'XAC_NHAN', 'DANG_GIAO', 'DA_GIAO', 'DA_HUY'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ.']);
    exit;
}

try {
    // Lấy trạng thái hiện tại
    $stmt = $pdo->prepare("SELECT TrangThai FROM DONHANG WHERE MaDonHang = ?");
    $stmt->execute([$maDonHang]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
        exit;
    }

    // Chuẩn hóa trạng thái
    $normalize = function ($status) {
        switch (strtoupper($status)) {
            case 'XAC_NHAN': return 'Xác nhận';
            case 'DANG_GIAO': return 'Đang giao';
            case 'DA_GIAO': return 'Đã giao';
            case 'DA_HUY': return 'Đã hủy';
            default: return $status;
        }
    };

    $currentStatus = $normalize($order['TrangThai']);
    $newStatus = $normalize($newStatus);

    // Validation nghiệp vụ
    if ($currentStatus === 'Đã hủy') {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng đã hủy không thể thay đổi trạng thái.']);
        exit;
    }

    if ($currentStatus === 'Đã giao') {
        echo json_encode(['success' => false, 'message' => 'Đơn hàng đã giao không thể thay đổi trạng thái.']);
        exit;
    }

    if ($newStatus === 'Đã hủy' && $currentStatus !== 'Xác nhận') {
        echo json_encode(['success' => false, 'message' => 'Chỉ có thể hủy đơn hàng khi đang ở trạng thái Xác nhận.']);
        exit;
    }

    $validTransitions = [
        'Xác nhận' => ['Đang giao', 'Đã hủy'],
        'Đang giao' => ['Đã giao'],
        'Đã giao' => [],
        'Đã hủy' => []
    ];

    if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
        echo json_encode([
            'success' => false,
            'message' => 'Không thể chuyển từ "' . $currentStatus . '" sang "' . $newStatus . '".'
        ]);
        exit;
    }

    // Transaction
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE DONHANG SET TrangThai = ? WHERE MaDonHang = ?");
    $stmt->execute([$newStatus, $maDonHang]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật trạng thái thành công.',
        'new_status' => $newStatus
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}

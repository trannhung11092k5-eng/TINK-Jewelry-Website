<?php
// public/ajax/place-order.php
header('Content-Type: application/json');
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập lại.']);
    exit;
}

$userId = $_SESSION['user_id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

$name = trim($input['name'] ?? '');
$phone = trim($input['phone'] ?? '');
$gmail = trim($input['gmail'] ?? '');
$address = trim($input['address'] ?? '');
$payment = $input['payment'] ?? 'cod';
$selectedItems = $input['selectedItems'] ?? [];

if (!$name || !$phone || !$address) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin giao hàng bắt buộc.']);
    exit;
}

// Chuyển đổi selectedItems thành mảng số nguyên
$selectedItemIds = [];
if (!empty($selectedItems) && is_array($selectedItems)) {
    $selectedItemIds = array_values(array_filter(array_map('intval', $selectedItems), fn($v) => $v > 0));
}

if (empty($selectedItemIds)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng chọn ít nhất một sản phẩm để thanh toán.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Cập nhật thông tin khách hàng (nếu cần)
    $stmt = $pdo->prepare("UPDATE KHACHHANG SET TenKH = ?, SoDienThoai = ?, Gmail = ?, DiaChi = ? WHERE KhachHangID = ?");
    $stmt->execute([$name, $phone, $gmail, $address, $userId]);

    // Lấy giỏ hàng
    $stmt = $pdo->prepare("SELECT * FROM GIOHANG WHERE KhachHangID = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $cart = $stmt->fetch();
    if (!$cart) {
        throw new Exception('Không tìm thấy giỏ hàng.');
    }
    $cartId = $cart['GioHangID'];

    // Lấy chi tiết giỏ hàng - CHỈ các sản phẩm đã được chọn
    $in = implode(',', array_fill(0, count($selectedItemIds), '?'));
    $stmt = $pdo->prepare("SELECT gc.*, sp.GiaBan 
                           FROM GIOHANG_CHITIET gc 
                           JOIN SANPHAM sp ON gc.MaSP = sp.MaSP 
                           WHERE gc.GioHangID = ? AND gc.ID IN ($in)");
    $params = array_merge([$cartId], $selectedItemIds);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    if (!$items) {
        throw new Exception('Không tìm thấy sản phẩm đã chọn trong giỏ hàng.');
    }

    // Tính tổng tiền và xác nhận giá
    $orderTotal = 0;
    foreach ($items as $it) {
        $orderTotal += $it['SoLuong'] * $it['GiaBan'];
    }

    // Tạo mã đơn hàng (theo pattern DH00001) - Sử dụng ORDER BY CAST để tránh duplicate
    $stmt = $pdo->query("SELECT MaDonHang 
                         FROM DONHANG 
                         WHERE MaDonHang LIKE 'DH%' 
                         ORDER BY CAST(SUBSTRING(MaDonHang, 3) AS UNSIGNED) DESC 
                         LIMIT 1");
    $row = $stmt->fetch();
    if (!empty($row['MaDonHang'])) {
        $num = intval(substr($row['MaDonHang'], 2)) + 1;
        $maDonHang = 'DH' . str_pad($num, 5, '0', STR_PAD_LEFT);
    } else {
        $maDonHang = 'DH00001';
    }

    // Tạo đơn hàng
    // Trạng thái mặc định sử dụng tiếng Việt để đồng bộ với toàn hệ thống
    $stmt = $pdo->prepare("INSERT INTO DONHANG (MaDonHang, KhachHangID, TrangThai, TongTien, NgayTao, PhuongThucThanhToan) 
                           VALUES (?, ?, ?, ?, NOW(), ?)");
    $status = 'Xác nhận';
    $stmt->execute([$maDonHang, $userId, $status, $orderTotal, $payment]);

    // Thêm chi tiết đơn hàng
    $detailStmt = $pdo->prepare("INSERT INTO CHITIET_DONHANG (MaDonHang, MaSP, SoLuong, GiaBan) VALUES (?, ?, ?, ?)");
    foreach ($items as $it) {
        $detailStmt->execute([
            $maDonHang,
            $it['MaSP'],
            (int)$it['SoLuong'],
            (float)$it['GiaBan']
        ]);
    }

    // Xoá CHỈ các sản phẩm đã chọn khỏi giỏ hàng chi tiết
    $in = implode(',', array_fill(0, count($selectedItemIds), '?'));
    $del = $pdo->prepare("DELETE FROM GIOHANG_CHITIET WHERE GioHangID = ? AND ID IN ($in)");
    $delParams = array_merge([$cartId], $selectedItemIds);
    $del->execute($delParams);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_code' => $maDonHang,
        'total' => $orderTotal
    ]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}




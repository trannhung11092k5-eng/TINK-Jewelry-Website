<?php
// public/ajax/update-cart.php
header('Content-Type: application/json');
session_start();
require_once '../../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$user_id = $_SESSION['user_id'];
$item_id = isset($input['item_id']) ? $input['item_id'] : null;
$action = isset($input['action']) ? $input['action'] : null;

if (!$item_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Thiếu tham số']);
    exit;
}

try {
    // Kiểm tra item thuộc giỏ hàng của user
    $stmt = $pdo->prepare("SELECT gc.*, gh.KhachHangID FROM GIOHANG_CHITIET gc JOIN GIOHANG gh ON gc.GioHangID = gh.GioHangID WHERE gc.ID = ?");
    $stmt->execute([$item_id]);
    $row = $stmt->fetch();
    if (!$row || $row['KhachHangID'] != $user_id) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy mục giỏ hàng']);
        exit;
    }

    $currentQty = (int)$row['SoLuong'];
    if ($action === 'increase') {
        $newQty = $currentQty + 1;
    } elseif ($action === 'decrease') {
        $newQty = max(0, $currentQty - 1);
    } else {
        echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
        exit;
    }

    // Lấy giá bán từ sản phẩm để tính thành tiền chính xác
    $priceStmt = $pdo->prepare("SELECT GiaBan FROM SANPHAM WHERE MaSP = ?");
    $priceStmt->execute([$row['MaSP']]);
    $priceRow = $priceStmt->fetch();
    $unitPrice = $priceRow ? (int)$priceRow['GiaBan'] : ($row['SoLuong'] > 0 ? (int)($row['ThanhTien'] / $row['SoLuong']) : 0);

    if ($newQty <= 0) {
        // Xóa mục
        $del = $pdo->prepare("DELETE FROM GIOHANG_CHITIET WHERE ID = ?");
        $del->execute([$item_id]);
        $newQty = 0;
        $newThanhTien = 0;
    } else {
        // Cập nhật số lượng và thành tiền
        $newThanhTien = $newQty * $unitPrice;
        $upd = $pdo->prepare("UPDATE GIOHANG_CHITIET SET SoLuong = ?, ThanhTien = ? WHERE ID = ?");
        $upd->execute([$newQty, $newThanhTien, $item_id]);
    }

    // Tính tổng tiền và tổng số lượng mới của giỏ hàng
    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(gc.ThanhTien),0) as cart_total, COALESCE(SUM(gc.SoLuong),0) as cart_count
                                FROM GIOHANG_CHITIET gc 
                                JOIN GIOHANG gh ON gc.GioHangID = gh.GioHangID 
                                WHERE gh.KhachHangID = ?");
    $totalStmt->execute([$user_id]);
    $totals = $totalStmt->fetch();

    echo json_encode([
        'success' => true,
        'quantity' => $newQty,
        'item_total' => $newThanhTien,
        'cart_total' => (int)$totals['cart_total'],
        'cart_count' => (int)$totals['cart_count'],
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}

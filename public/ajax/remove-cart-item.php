<?php
// public/ajax/remove-cart-item.php
header('Content-Type: application/json');
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$itemId = $input['item_id'] ?? null;

if (!$itemId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu tham số']);
    exit;
}

try {
    // Kiểm tra item thuộc giỏ hàng của user
    $stmt = $pdo->prepare("SELECT gc.*, gh.KhachHangID FROM GIOHANG_CHITIET gc JOIN GIOHANG gh ON gc.GioHangID = gh.GioHangID WHERE gc.ID = ?");
    $stmt->execute([$itemId]);
    $row = $stmt->fetch();

    if (!$row || $row['KhachHangID'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy mục giỏ hàng']);
        exit;
    }

    $del = $pdo->prepare("DELETE FROM GIOHANG_CHITIET WHERE ID = ?");
    $del->execute([$itemId]);

    // Tính tổng mới
    $totalStmt = $pdo->prepare("SELECT COALESCE(SUM(gc.ThanhTien),0) as cart_total, COALESCE(SUM(gc.SoLuong),0) as cart_count
                                FROM GIOHANG_CHITIET gc 
                                JOIN GIOHANG gh ON gc.GioHangID = gh.GioHangID 
                                WHERE gh.KhachHangID = ?");
    $totalStmt->execute([$_SESSION['user_id']]);
    $totals = $totalStmt->fetch();

    echo json_encode([
        'success' => true,
        'cart_total' => (int)$totals['cart_total'],
        'cart_count' => (int)$totals['cart_count'],
    ]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}




<?php
// public/ajax/add-to-cart.php
header('Content-Type: application/json');
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập trước khi thêm vào giỏ hàng.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$productId = $input['MaSP'] ?? '';
$quantity = isset($input['quantity']) ? (int)$input['quantity'] : 1;

if (empty($productId) || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit;
}

try {
    // Lấy thông tin sản phẩm
    $productStmt = $pdo->prepare("SELECT MaSP, GiaBan FROM SANPHAM WHERE MaSP = ?");
    $productStmt->execute([$productId]);
    $product = $productStmt->fetch();

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy sản phẩm.']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Lấy hoặc tạo giỏ hàng cho user
    $cartStmt = $pdo->prepare("SELECT GioHangID FROM GIOHANG WHERE KhachHangID = ?");
    $cartStmt->execute([$userId]);
    $cart = $cartStmt->fetch();

    if ($cart) {
        $cartId = $cart['GioHangID'];
    } else {
        $createCart = $pdo->prepare("INSERT INTO GIOHANG (KhachHangID) VALUES (?)");
        $createCart->execute([$userId]);
        $cartId = $pdo->lastInsertId();
    }

    // Kiểm tra sản phẩm đã có trong giỏ chưa
    $detailStmt = $pdo->prepare("SELECT * FROM GIOHANG_CHITIET WHERE GioHangID = ? AND MaSP = ?");
    $detailStmt->execute([$cartId, $productId]);
    $detail = $detailStmt->fetch();

    $unitPrice = (int)$product['GiaBan'];
    if ($detail) {
        $newQty = (int)$detail['SoLuong'] + $quantity;
        $newTotal = $newQty * $unitPrice;
        $update = $pdo->prepare("UPDATE GIOHANG_CHITIET SET SoLuong = ?, ThanhTien = ? WHERE ID = ?");
        $update->execute([$newQty, $newTotal, $detail['ID']]);
    } else {
        $newQty = $quantity;
        $newTotal = $quantity * $unitPrice;
        $insert = $pdo->prepare("INSERT INTO GIOHANG_CHITIET (GioHangID, MaSP, SoLuong, ThanhTien) VALUES (?, ?, ?, ?)");
        $insert->execute([$cartId, $productId, $newQty, $newTotal]);
    }

    // Tổng số lượng mới trong giỏ
    $countStmt = $pdo->prepare("SELECT COALESCE(SUM(gc.SoLuong), 0) as total_items 
                                FROM GIOHANG_CHITIET gc 
                                JOIN GIOHANG gh ON gc.GioHangID = gh.GioHangID 
                                WHERE gh.KhachHangID = ?");
    $countStmt->execute([$userId]);
    $count = $countStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Đã thêm vào giỏ hàng!',
        'cart_count' => (int)$count['total_items']
    ]);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
    exit;
}




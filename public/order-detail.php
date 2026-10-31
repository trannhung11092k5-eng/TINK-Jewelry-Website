<?php
// order-detail.php - Chi tiết đơn hàng cho khách
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=order-detail.php');
    exit();
}

$normalizeStatus = function($status) {
    switch (strtoupper($status)) {
        case 'XAC_NHAN': return 'Xác nhận';
        case 'DANG_GIAO': return 'Đang giao';
        case 'DA_GIAO': return 'Đã giao';
        case 'DA_HUY': return 'Đã hủy';
        default: return $status;
    }
};

$orderCode = $_GET['code'] ?? $_GET['id'] ?? '';
$customerId = $_SESSION['user_id'];
$order = null;
$products = [];

if ($orderCode) {
    try {
        $stmt = $pdo->prepare("SELECT dh.*, kh.TenKH, kh.Gmail, kh.SoDienThoai, kh.DiaChi
                               FROM DONHANG dh
                               LEFT JOIN KHACHHANG kh ON dh.KhachHangID = kh.KhachHangID
                               WHERE dh.MaDonHang = ? AND dh.KhachHangID = ?");
        $stmt->execute([$orderCode, $customerId]);
        $order = $stmt->fetch();

        if ($order) {
            $order['TrangThai'] = $normalizeStatus($order['TrangThai']);

            $detail = $pdo->prepare("SELECT ctdh.*, sp.TenSP, sp.AnhSP, sp.GiaBan
                                     FROM CHITIET_DONHANG ctdh
                                     LEFT JOIN SANPHAM sp ON ctdh.MaSP = sp.MaSP
                                     WHERE ctdh.MaDonHang = ?");
            $detail->execute([$orderCode]);
            $products = $detail->fetchAll();
        }
    } catch (PDOException $e) {
        $order = null;
    }
}

include 'includes/header.php';
?>

<style>
.order-detail-wrapper {
    max-width: 1100px;
    margin: 40px auto;
    padding: 0 20px;
}
.order-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,64,128,0.08);
    overflow: hidden;
}
.order-header {
    background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    color: #fff;
    padding: 20px 24px;
}
.order-header h2 { margin: 0 0 6px 0; }
.order-header p { margin: 0; opacity: 0.9; }
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
    padding: 20px 24px;
}
.info-item {
    background: #f8faff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 14px 16px;
}
.info-label {
    color: #4b5563;
    font-size: 13px;
    margin-bottom: 6px;
    font-weight: 600;
}
.info-value { color: #111827; font-weight: 600; }
.status-badge {
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
    display: inline-block;
}
.status-confirmed { background: #e8f0ff; color: #0d47a1; }
.status-delivering { background: #fff4e5; color: #e67e22; }
.status-delivered { background: #e6f4ea; color: #2e7d32; }
.status-cancelled { background: #ffebee; color: #c62828; }
.product-list { padding: 10px 24px 24px; }
.product-item {
    display: grid;
    grid-template-columns: 80px 1fr 120px;
    gap: 16px;
    padding: 14px 0;
    border-bottom: 1px solid #f1f5f9;
    align-items: center;
}
.product-item:last-child { border-bottom: none; }
.product-img {
    width: 80px; height: 80px; border-radius: 8px;
    object-fit: cover; background: #f3f4f6;
}
.product-name { font-weight: 600; color: #0f172a; }
.product-meta { color: #6b7280; font-size: 13px; }
.price { color: #0d6efd; font-weight: 700; }
.total-row {
    display: flex;
    justify-content: space-between;
    padding: 18px 24px;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    align-items: center;
}
.total-row strong { color: #0f172a; }
.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: #e8f0ff;
    color: #0d47a1;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
}
.back-btn:hover { background: #d7e5ff; }
.empty-state {
    text-align: center;
    padding: 50px 20px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 4px 18px rgba(0,64,128,0.08);
    max-width: 600px;
    margin: 60px auto;
}
</style>

<div class="order-detail-wrapper">
    <?php if (!$order): ?>
        <div class="empty-state">
            <i class="fas fa-info-circle" style="font-size:42px;color:#9ca3af;margin-bottom:12px;"></i>
            <h3>Không tìm thấy đơn hàng</h3>
            <p style="color:#6b7280;margin:10px 0 20px;">Đơn hàng không tồn tại hoặc không thuộc tài khoản của bạn.</p>
            <a href="your-orders.php" class="back-btn"><i class="fas fa-arrow-left"></i> Quay về đơn hàng của tôi</a>
        </div>
    <?php else: ?>
        <div class="order-card">
            <div class="order-header">
                <h2><i class="fas fa-file-invoice"></i> Đơn hàng <?php echo htmlspecialchars($order['MaDonHang']); ?></h2>
                <p>Ngày tạo: <?php echo date('d/m/Y H:i', strtotime($order['NgayTao'])); ?></p>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Trạng thái</div>
                    <?php
                        $statusClass = 'status-confirmed';
                        switch ($order['TrangThai']) {
                            case 'Đang giao': $statusClass = 'status-delivering'; break;
                            case 'Đã giao': $statusClass = 'status-delivered'; break;
                            case 'Đã hủy': $statusClass = 'status-cancelled'; break;
                        }
                    ?>
                    <div class="info-value">
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo htmlspecialchars($order['TrangThai']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Khách hàng</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['TenKH'] ?? ''); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['Gmail'] ?? ''); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Số điện thoại</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['SoDienThoai'] ?? ''); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Địa chỉ</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['DiaChi'] ?? ''); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Tổng tiền</div>
                    <div class="info-value price"><?php echo number_format($order['TongTien'] ?? 0, 0, ',', '.'); ?>đ</div>
                </div>
            </div>

            <div class="product-list">
                <?php if (empty($products)): ?>
                    <p style="color:#6b7280;">Không tìm thấy sản phẩm trong đơn.</p>
                <?php else: ?>
                    <?php foreach ($products as $product): 
                        $imgPath = !empty($product['AnhSP']) ? '../' . ltrim($product['AnhSP'], '/') : '../public/assets/images/no-image.jpg';
                        $lineTotal = ($product['SoLuong'] ?? 0) * ($product['GiaBan'] ?? 0);
                    ?>
                        <div class="product-item">
                            <img class="product-img" src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($product['TenSP'] ?? ''); ?>" onerror="this.src='../public/assets/images/no-image.jpg'">
                            <div>
                                <div class="product-name"><?php echo htmlspecialchars($product['TenSP'] ?? 'Sản phẩm'); ?></div>
                                <div class="product-meta">Mã SP: <?php echo htmlspecialchars($product['MaSP']); ?></div>
                                <div class="product-meta">Số lượng: <?php echo htmlspecialchars($product['SoLuong']); ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div class="price"><?php echo number_format($product['GiaBan'] ?? 0, 0, ',', '.'); ?>đ</div>
                                <div class="product-meta">Thành tiền: <?php echo number_format($lineTotal, 0, ',', '.'); ?>đ</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="total-row">
                <a href="your-orders.php" class="back-btn"><i class="fas fa-arrow-left"></i> Quay lại</a>
                <div>
                    <span style="color:#4b5563;margin-right:10px;">Tổng thanh toán:</span>
                    <span class="price" style="font-size:20px;"><?php echo number_format($order['TongTien'] ?? 0, 0, ',', '.'); ?>đ</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>



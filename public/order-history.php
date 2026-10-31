<?php
// order-history.php - Lịch sử mua hàng
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=order-history.php');
    exit();
}

$customerId = $_SESSION['user_id'];

try {
    // Đơn đã hoàn tất hoặc hủy được coi là lịch sử (chấp nhận cả dạng cũ và mới)
    $stmt = $pdo->prepare("SELECT * FROM DONHANG 
                           WHERE KhachHangID = ? 
                             AND (TrangThai IN ('Đã giao', 'Đã hủy', 'DA_GIAO', 'DA_HUY'))
                           ORDER BY NgayTao DESC");
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

include 'includes/header.php';
?>

<div style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
    <h1 style="font-family: 'Playfair Display', serif; margin-bottom: 24px;">Lịch sử mua hàng</h1>

    <?php if (empty($orders)): ?>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:32px;text-align:center;">
            <i class="fas fa-clock" style="font-size:40px;color:#d1d5db;margin-bottom:12px;"></i>
            <p style="font-size:18px;margin-bottom:8px;">Bạn chưa có đơn hàng nào trước đó</p>
            <p style="color:#6b7280;margin-bottom:20px;">Khi hoàn tất đơn, lịch sử sẽ hiển thị tại đây.</p>
            <a href="products.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Mua sắm ngay</a>
        </div>
    <?php else: ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;">
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th style="padding:12px 16px;text-align:left;border-bottom:1px solid #e5e7eb;">Mã đơn</th>
                        <th style="padding:12px 16px;text-align:left;border-bottom:1px solid #e5e7eb;">Ngày tạo</th>
                        <th style="padding:12px 16px;text-align:right;border-bottom:1px solid #e5e7eb;">Tổng tiền</th>
                        <th style="padding:12px 16px;text-align:center;border-bottom:1px solid #e5e7eb;">Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:12px 16px;"><?php echo htmlspecialchars($order['MaDonHang']); ?></td>
                            <td style="padding:12px 16px;"><?php echo htmlspecialchars($order['NgayTao'] ?? ''); ?></td>
                            <td style="padding:12px 16px;text-align:right;"><?php echo number_format($order['TongTien'] ?? 0, 0, ',', '.'); ?>đ</td>
                            <td style="padding:12px 16px;text-align:center;">
                                <?php 
                                $statusText = '';
                                $statusBg = '';
                                $statusColor = '';
                                switch($order['TrangThai']) {
                                    case 'DA_GIAO':
                                        $statusText = 'Đã giao';
                                        $statusBg = '#e8f5e9';
                                        $statusColor = '#388e3c';
                                        break;
                                    case 'DA_HUY':
                                        $statusText = 'Đã hủy';
                                        $statusBg = '#ffebee';
                                        $statusColor = '#d32f2f';
                                        break;
                                    default:
                                        $statusText = $order['TrangThai'];
                                        $statusBg = '#f3f4f6';
                                        $statusColor = '#666';
                                }
                                ?>
                                <span style="display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;
                                             background:<?php echo $statusBg; ?>;
                                             color:<?php echo $statusColor; ?>;">
                                    <?php echo htmlspecialchars($statusText); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>




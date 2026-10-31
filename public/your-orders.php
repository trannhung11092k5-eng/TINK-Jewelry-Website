<?php
// your-orders.php - Đơn hàng hiện tại của khách 
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=your-orders.php');
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

$customerId = $_SESSION['user_id'];

// Lấy tất cả đơn hàng của khách hàng, phân loại theo trạng thái
$allOrders = [];
$ordersByStatus = [
    'Xác nhận' => [],
    'Đang giao' => [],
    'Đã hủy' => []
];
$historyOrders = []; // lưu đơn đã giao để chuyển qua lịch sử

try {
    $stmt = $pdo->prepare("SELECT * FROM DONHANG WHERE KhachHangID = ? ORDER BY NgayTao DESC");
    $stmt->execute([$customerId]);
    $allOrders = $stmt->fetchAll();

    // Phân loại đơn hàng theo trạng thái
    foreach ($allOrders as $order) {
        $status = $normalizeStatus($order['TrangThai']);
        $order['TrangThai'] = $status; // lưu trạng thái đã chuẩn hóa
        // Đơn đã giao chuyển qua lịch sử
        if ($status === 'Đã giao') {
            $historyOrders[] = $order;
            continue;
        }
        if (isset($ordersByStatus[$status])) {
            $ordersByStatus[$status][] = $order;
        }
    }
    
    // Lấy chi tiết sản phẩm cho mỗi đơn hàng
    foreach ($ordersByStatus as $status => $orders) {
        foreach ($orders as $index => $order) {
            $stmt = $pdo->prepare("
                SELECT ctdh.*, sp.TenSP, sp.AnhSP, sp.GiaBan 
                FROM CHITIET_DONHANG ctdh 
                LEFT JOIN SANPHAM sp ON ctdh.MaSP = sp.MaSP 
                WHERE ctdh.MaDonHang = ?
            ");
            $stmt->execute([$order['MaDonHang']]);
            $products = $stmt->fetchAll();
            $ordersByStatus[$status][$index]['products'] = $products;
            $ordersByStatus[$status][$index]['total_items'] = array_sum(array_column($products, 'SoLuong'));
        }
    }
} catch (PDOException $e) {
    $allOrders = [];
}

include 'includes/header.php';
?>

<style>
/* Order Page Styles */
.order-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.order-header {
    background: linear-gradient(135deg,rgb(255, 255, 255),rgb(255, 255, 255));
    color: rgb(15, 18, 108);
    padding: 20px 24px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(15, 18, 108, 0.2);
}

.order-header h1 {
    margin: 0 0 10px 0;
    font-size: 28px;
    font-weight: 700;
}

.order-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 15px;
}

.order-status-tabs {
    display: flex;
    background: white;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.status-tab {
    flex: 1;
    padding: 16px 20px;
    text-align: center;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.3s;
    font-weight: 500;
    color: rgb(15, 18, 108);
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.status-tab:hover {
    background: #f8f9fa;
    color: rgb(15, 18, 108);
}

.status-tab.active {
    color: rgb(15, 18, 108);
    border-bottom-color: rgb(15, 18, 108);
    background: #f4f8ff;
}

.status-count {
    background: rgb(15, 18, 108);
    color: white;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 24px;
    display: inline-block;
}

.status-tab:not(.active) .status-count {
    background: #ccc;
}

.order-list {
    display: none;
}

.order-list.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.order-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 16px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(0,64,128,0.18);
}

.order-header-card {
    background: #f7f9fc;
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.order-info {
    display: flex;
    gap: 20px;
    align-items: center;
    flex-wrap: wrap;
}

.order-id {
    font-weight: 600;
    color: #333;
    font-size: 15px;
}

.order-date {
    color: #666;
    font-size: 14px;
}

.order-status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.status-confirmed { background: #e8f0ff; color: #0d47a1; }
.status-delivering { background: #fff4e5; color: #e67e22; }
.status-delivered { background: #e6f4ea; color: #2e7d32; }
.status-cancelled { background: #ffebee; color: #c62828; }

.order-actions {
    display: flex;
    gap: 10px;
}

.order-actions .btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.order-actions .btn-primary {
    background: rgb(15, 18, 108);
    color: white;
    box-shadow: 0 2px 6px rgba(13,110,253,0.25);
}

.order-actions .btn-primary:hover {
    background: rgb(29, 35, 136);
}

.order-actions .btn-outline {
    background: white;
    color: rgb(15, 18, 108);
    border: 1px solid rgb(15, 18, 108);
}

.order-actions .btn-outline:hover {
    border-color: rgb(15, 18, 108);
    color: rgb(255, 255, 255);
}

.order-content {
    padding: 20px;
}

.product-item {
    display: flex;
    padding: 16px 0;
    border-bottom: 1px solid #f5f5f5;
}

.product-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 180px;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    margin-right: 16px;
}

.product-details {
    flex: 1;
}

.product-name {
    font-weight: 500;
    color: #333;
    margin-bottom: 4px;
    font-size: 15px;
    line-height: 1.4;
}

.product-price {
    color: rgb(220, 38, 38);
    font-weight: 600;
    font-size: 16px;
}

.product-quantity {
    color: #666;
    font-size: 14px;
}

.order-total {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 20px;
    border-top: 1px solid #eee;
    gap: 20px;
}

.total-label {
    color: #666;
    font-size: 15px;
}

.total-amount {
    color: rgb(220, 38, 38);
    font-size: 22px;
    font-weight: 700;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.08);
}

.empty-icon {
    font-size: 60px;
    color: #ccc;
    margin-bottom: 16px;
}

.empty-state h3 {
    color: #333;
    margin-bottom: 8px;
    font-size: 18px;
}

.empty-state p {
    color: #666;
    margin-bottom: 24px;
}

/* Loading animation */
.loading {
    display: none;
    text-align: center;
    padding: 40px;
}

.loading.active {
    display: block;
}

.spinner {
    border: 3px solid #f3f3f3;
    border-top: 3px solid rgb(15, 18, 108);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Modal cho hủy đơn */
.cancel-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.cancel-modal.active {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    padding: 24px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.modal-title {
    color: #333;
    margin-bottom: 16px;
    font-size: 18px;
    font-weight: 600;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
}

.modal-actions .btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.modal-actions .btn-cancel {
    background: #ccc;
    color: #333;
}

.modal-actions .btn-cancel:hover {
    background: #bbb;
}

.modal-actions .btn-confirm {
    background: rgb(15, 18, 108);
    color: white;
}

.modal-actions .btn-confirm:hover {
        background: rgb(29, 35, 136);
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 1001;
    display: none;
    animation: slideIn 0.3s ease;
}

.notification.success {
    background: rgb(22, 163, 74);
}

.notification.error {
    background: rgb(220, 38, 38);
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .order-container {
        padding: 12px;
    }
    
    .order-header {
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .order-header h1 {
        font-size: 24px;
    }
    
    .status-tab {
        padding: 12px 8px;
        font-size: 13px;
    }
    
    .order-header-card {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .order-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .order-total {
        flex-direction: column;
        align-items: flex-end;
        gap: 12px;
    }
    
    .product-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .product-image {
        width: 100%;
        height: 200px;
        margin-right: 0;
        margin-bottom: 12px;
    }
}
</style>

<div class="order-container">
    <!-- Header -->
    <div class="order-header">
        <h1><i class="fas fa-shopping-bag"></i> Đơn hàng của tôi</h1>
        <p>Theo dõi và quản lý đơn hàng của bạn tại đây</p>
    </div>

    <!-- Tabs trạng thái -->
    <div class="order-status-tabs">
        <?php 
        $statusTabs = [
            'all' => ['name' => 'Tất cả', 'count' => count($ordersByStatus['Xác nhận']) + count($ordersByStatus['Đang giao']) + count($ordersByStatus['Đã hủy']), 'icon' => 'fas fa-list'],
            'Xác nhận' => ['name' => 'Chờ xác nhận', 'count' => count($ordersByStatus['Xác nhận']), 'icon' => 'fas fa-clock'],
            'Đang giao' => ['name' => 'Đang giao', 'count' => count($ordersByStatus['Đang giao']), 'icon' => 'fas fa-shipping-fast'],
            'Đã hủy' => ['name' => 'Đã hủy', 'count' => count($ordersByStatus['Đã hủy']), 'icon' => 'fas fa-ban']
        ];
        
        foreach ($statusTabs as $status => $tab):
        ?>
        <a href="javascript:void(0)" 
           class="status-tab <?php echo $status === 'all' ? 'active' : ''; ?>" 
           data-status="<?php echo $status; ?>"
           onclick="showOrders('<?php echo $status; ?>')">
            <i class="<?php echo $tab['icon']; ?>"></i>
            <span><?php echo $tab['name']; ?></span>
            <?php if ($tab['count'] > 0): ?>
                <span class="status-count"><?php echo $tab['count']; ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Loading indicator -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <p>Đang tải đơn hàng...</p>
    </div>

    <!-- Container cho danh sách đơn hàng -->
    <div id="orders-container">
        <?php 
        // Hiển thị tất cả đơn hàng mặc định
        foreach ($statusTabs as $status => $tab):
            $displayStatus = $status === 'all' ? 'block' : 'none';
        ?>
        <div class="order-list <?php echo $status === 'all' ? 'active' : ''; ?>" id="order-list-<?php echo $status; ?>">
            <?php 
            if ($status === 'all') {
                $ordersToShow = array_merge($ordersByStatus['Xác nhận'], $ordersByStatus['Đang giao'], $ordersByStatus['Đã hủy']);
            } else {
                $ordersToShow = $ordersByStatus[$status];
            }
            
            if (empty($ordersToShow)): 
            ?>
                <div class="empty-state">
                    <i class="fas fa-box-open empty-icon"></i>
                    <h3>Chưa có đơn hàng <?php echo $status === 'all' ? '' : strtolower($tab['name']); ?></h3>
                    <p>Bắt đầu hành trình mua sắm cùng TINK Jewelry ngay hôm nay!</p>
                    <a href="products.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Mua sắm ngay
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($ordersToShow as $order): 
                    $statusClass = '';
                    switch($order['TrangThai']) {
                        case 'Xác nhận': $statusClass = 'status-confirmed'; break;
                        case 'Đang giao': $statusClass = 'status-delivering'; break;
                        case 'Đã giao': $statusClass = 'status-delivered'; break;
                        case 'Đã hủy': $statusClass = 'status-cancelled'; break;
                    }
                ?>
                <div class="order-card" data-status="<?php echo $order['TrangThai']; ?>">
                    <div class="order-header-card">
                        <div class="order-info">
                            <div class="order-id">
                                <i class="fas fa-receipt"></i> Mã đơn: <?php echo htmlspecialchars($order['MaDonHang']); ?>
                            </div>
                            <div class="order-date">
                                <i class="far fa-calendar-alt"></i> Ngày đặt: <?php echo date('d/m/Y H:i', strtotime($order['NgayTao'])); ?>
                            </div>
                            <div class="order-status-badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($order['TrangThai']); ?>
                            </div>
                        </div>
                        <div class="order-actions">
                            <?php if ($order['TrangThai'] === 'Xác nhận'): ?>
                                <button class="btn btn-outline" onclick="showCancelModal('<?php echo $order['MaDonHang']; ?>')">
                                    <i class="fas fa-times"></i> Hủy đơn
                                </button>
                            <?php endif; ?>
                            <a href="order-detail.php?code=<?php echo $order['MaDonHang']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> Xem chi tiết
                            </a>
                        </div>
                    </div>
                    
                    <div class="order-content">
                        <?php if (!empty($order['products'])): ?>
                            <?php foreach ($order['products'] as $product): ?>
                            <div class="product-item">
                                <?php 
                                    $imgPath = !empty($product['AnhSP']) ? '../' . ltrim($product['AnhSP'], '/') : '../public/assets/images/no-image.jpg';
                                ?>
                                <img src="<?php echo htmlspecialchars($imgPath); ?>" 
                                     alt="<?php echo htmlspecialchars($product['TenSP']); ?>" 
                                     class="product-image"
                                     onerror="this.src='../public/assets/images/no-image.jpg'">
                                <div class="product-details">
                                    <div class="product-name"><?php echo htmlspecialchars($product['TenSP']); ?></div>
                                    <div class="product-price">
                                        <?php echo number_format($product['GiaBan'], 0, ',', '.'); ?>đ
                                    </div>
                                    <div class="product-quantity">
                                        Số lượng: <?php echo $product['SoLuong']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-total">
                        <div class="total-label">
                            Tổng cộng (<?php echo $order['total_items'] ?? 0; ?> sản phẩm):
                        </div>
                        <div class="total-amount">
                            <?php echo number_format($order['TongTien'] ?? 0, 0, ',', '.'); ?>đ
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal hủy đơn -->
<div class="cancel-modal" id="cancelModal">
    <div class="modal-content">
        <div class="modal-title">
            <i class="fas fa-exclamation-triangle" style="color: #f57c00; margin-right: 8px;"></i>
            Xác nhận hủy đơn hàng
        </div>
        <p>Bạn có chắc chắn muốn hủy đơn hàng <strong id="cancelOrderCode"></strong>?</p>
        <p style="color: #666; font-size: 14px; margin-top: 8px;">
            <i class="fas fa-info-circle"></i> Chỉ có thể hủy đơn hàng khi đang ở trạng thái "Chờ xác nhận"
        </p>
        <div class="modal-actions">
            <button class="btn btn-cancel" onclick="closeCancelModal()">Quay lại</button>
            <button class="btn btn-confirm" onclick="confirmCancelOrder()">
                <i class="fas fa-check"></i> Xác nhận hủy
            </button>
        </div>
    </div>
</div>

<!-- Notification -->
<div class="notification" id="notification"></div>

<script>
let currentStatus = 'all';
let cancelOrderId = '';

// Hiển thị đơn hàng theo trạng thái
function showOrders(status) {
    currentStatus = status;
    
    // Cập nhật active tab
    document.querySelectorAll('.status-tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('data-status') === status) {
            tab.classList.add('active');
        }
    });
    
    // Hiển thị danh sách tương ứng
    document.querySelectorAll('.order-list').forEach(list => {
        list.classList.remove('active');
    });
    document.getElementById('order-list-' + status).classList.add('active');
    
    // Cuộn lên đầu
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Modal hủy đơn
function showCancelModal(orderId) {
    cancelOrderId = orderId;
    document.getElementById('cancelOrderCode').textContent = orderId;
    document.getElementById('cancelModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
    document.body.style.overflow = 'auto';
    cancelOrderId = '';
}

function confirmCancelOrder() {
    if (!cancelOrderId) return;
    
    showLoading(true);
    
    fetch('ajax/cancel-order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ma_don_hang: cancelOrderId })
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);
        closeCancelModal();
        
        if (data.success) {
            showNotification('Hủy đơn hàng thành công!', 'success');
            // Reload sau 1.5 giây
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message || 'Không thể hủy đơn hàng', 'error');
        }
    })
    .catch(error => {
        showLoading(false);
        showNotification('Có lỗi xảy ra, vui lòng thử lại', 'error');
        console.error('Error:', error);
    });
}

// Hiển thị loading
function showLoading(show) {
    document.getElementById('loading').classList.toggle('active', show);
}

// Hiển thị thông báo
function showNotification(message, type) {
    const notification = document.getElementById('notification');
    notification.textContent = message;
    notification.className = `notification ${type}`;
    notification.style.display = 'block';
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 3000);
}

// Auto refresh đơn hàng mỗi 30 giây (tùy chọn)
setInterval(() => {
    // Chỉ refresh nếu đang ở tab đang hoạt động
    if (currentStatus !== 'Đã giao' && currentStatus !== 'Đã hủy') {
        fetchOrders();
    }
}, 30000);

// Fetch đơn hàng mới (có thể dùng cho real-time update)
function fetchOrders() {
    fetch('ajax/get-orders.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Cập nhật UI với data.orders
            // Implement UI update logic here
        }
    })
    .catch(error => console.error('Error fetching orders:', error));
}

// Đóng modal khi click bên ngoài
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCancelModal();
    }
});

// Đóng bằng phím ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>
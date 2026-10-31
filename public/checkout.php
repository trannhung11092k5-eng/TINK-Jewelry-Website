<?php
session_start();
require_once '../config/db.php';

// Bắt buộc đăng nhập để thanh toán
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Lấy thông tin khách hàng (nếu có bảng KHACHHANG)
$customer = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM KHACHHANG WHERE KhachHangID = ? LIMIT 1");
    $stmt->execute([$userId]);
    $customer = $stmt->fetch();
} catch (PDOException $e) {
    $customer = null;
}

// Lấy danh sách item ID được chọn từ trang giỏ hàng (nếu có)
$selectedIds = [];
if (!empty($_GET['items'])) {
    $raw = explode(',', $_GET['items']);
    $selectedIds = array_values(array_filter(array_map('intval', $raw), fn($v) => $v > 0));
}

// Lấy giỏ hàng hiện tại
try {
    $stmt = $pdo->prepare("SELECT * FROM GIOHANG WHERE KhachHangID = ?");
    $stmt->execute([$userId]);
    $cart = $stmt->fetch();
    $cartId = $cart ? $cart['GioHangID'] : null;

    $cartItems = [];
    $cartTotal = 0;
    if ($cartId) {
        if ($selectedIds) {
            $in = implode(',', array_fill(0, count($selectedIds), '?'));
            $sql = "SELECT gc.*, sp.TenSP, sp.AnhSP, sp.GiaBan 
                    FROM GIOHANG_CHITIET gc 
                    JOIN SANPHAM sp ON gc.MaSP = sp.MaSP 
                    WHERE gc.GioHangID = ? AND gc.ID IN ($in)";
            $stmt = $pdo->prepare($sql);
            $params = array_merge([$cartId], $selectedIds);
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("SELECT gc.*, sp.TenSP, sp.AnhSP, sp.GiaBan 
                                   FROM GIOHANG_CHITIET gc 
                                   JOIN SANPHAM sp ON gc.MaSP = sp.MaSP 
                                   WHERE gc.GioHangID = ?");
            $stmt->execute([$cartId]);
        }
        $cartItems = $stmt->fetchAll();
        foreach ($cartItems as $item) {
            $cartTotal += $item['ThanhTien'];
        }
    }
} catch (PDOException $e) {
    $cartItems = [];
    $cartTotal = 0;
}
?>
<?php include 'includes/header.php'; ?>

<div style="max-width: 1100px; margin: 40px auto; padding: 0 20px;">
    <h1 style="font-family: 'Playfair Display', serif; margin-bottom: 24px;">Thanh toán</h1>

    <?php if (empty($cartItems)): ?>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:32px;text-align:center;">
            <i class="fas fa-shopping-cart" style="font-size:40px;color:#d1d5db;margin-bottom:12px;"></i>
            <p style="font-size:18px;margin-bottom:8px;">Giỏ hàng của bạn đang trống</p>
            <p style="color:#6b7280;margin-bottom:20px;">Vui lòng thêm sản phẩm trước khi thanh toán.</p>
            <a href="products.php" class="btn btn-primary"><i class="fas fa-shopping-bag"></i> Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns: minmax(0, 2fr) minmax(0, 1.4fr);gap:24px;align-items:flex-start;">
            <!-- Thông tin giao hàng -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:20px 20px 16px;">
                <h2 style="font-size:18px;margin-bottom:16px;">Thông tin giao hàng</h2>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div>
                        <label style="font-size:14px;color:#4b5563;">Họ và tên</label>
                        <input type="text" id="ship-name" value="<?php echo htmlspecialchars($customer['TenKH'] ?? ''); ?>"
                               style="width:100%;margin-top:4px;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                    </div>
                    <div>
                        <label style="font-size:14px;color:#4b5563;">Số điện thoại</label>
                        <input type="text" id="ship-phone" value="<?php echo htmlspecialchars($customer['SoDienThoai'] ?? ''); ?>"
                               style="width:100%;margin-top:4px;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;">
                    </div>
                    <div>
                        <label style="font-size:14px;color:#4b5563;">Gmail <span style="color:#6b7280;font-size:12px;">(Không thể thay đổi)</span></label>
                        <input type="email" id="ship-gmail" value="<?php echo htmlspecialchars($customer['Gmail'] ?? ''); ?>" readonly
                               style="width:100%;margin-top:4px;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;background-color:#f9fafb;color:#6b7280;cursor:not-allowed;">
                    </div>
                    <div>
                        <label style="font-size:14px;color:#4b5563;">Địa chỉ giao hàng</label>
                        <textarea id="ship-address" rows="3"
                                  style="width:100%;margin-top:4px;padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;"><?php echo htmlspecialchars($customer['DiaChi'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Giỏ hàng + Tóm tắt -->
            <div style="display:flex;flex-direction:column;gap:16px;">
                <!-- Giỏ hàng (có thể chỉnh số lượng) -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                    <h2 style="font-size:18px;margin-bottom:12px;">Giỏ hàng</h2>
                    <div style="max-height:260px;overflow-y:auto;">
                        <?php foreach ($cartItems as $item): ?>
                            <div data-id="<?php echo $item['ID']; ?>" data-price="<?php echo (int)$item['GiaBan']; ?>"
                                 style="display:flex;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6;align-items:center;">
                                <img src="../<?php echo htmlspecialchars($item['AnhSP']); ?>"
                                     alt="<?php echo htmlspecialchars($item['TenSP']); ?>"
                                     style="width:60px;height:60px;border-radius:6px;object-fit:cover;">
                                <div style="flex:1 1 auto;">
                                    <div style="font-size:14px;font-weight:500;"><?php echo htmlspecialchars($item['TenSP']); ?></div>
                                    <div style="font-size:13px;color:#6b7280;">Mã: <?php echo htmlspecialchars($item['MaSP']); ?></div>
                                    <div style="font-size:13px;color:#b8860b;margin-top:2px;">
                                        <?php echo number_format($item['GiaBan'], 0, ',', '.'); ?>đ
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                                    <div style="display:inline-flex;align-items:center;gap:6px;">
                                        <button class="btn-qty" onclick="checkoutUpdateQty('<?php echo $item['ID']; ?>','decrease')">-</button>
                                        <span class="co-qty" id="co-qty-<?php echo $item['ID']; ?>"><?php echo $item['SoLuong']; ?></span>
                                        <button class="btn-qty" onclick="checkoutUpdateQty('<?php echo $item['ID']; ?>','increase')">+</button>
                                    </div>
                                    <div style="font-weight:600;color:#b8860b;font-size:14px;">
                                        <span class="co-row-total" id="co-total-<?php echo $item['ID']; ?>">
                                            <?php echo number_format($item['ThanhTien'], 0, ',', '.'); ?>đ
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Tóm tắt đơn hàng -->
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">
                    <h2 style="font-size:18px;margin-bottom:12px;">Tóm tắt đơn hàng</h2>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;">
                        <span>Tổng tiền hàng</span>
                        <span id="co-subtotal"><?php echo number_format($cartTotal, 0, ',', '.'); ?>đ</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:14px;">
                        <span>Phí vận chuyển</span>
                        <span>Miễn phí</span>
                    </div>
                    <hr style="border:none;border-top:1px solid #e5e7eb;margin:10px 0;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <span style="font-weight:600;">Tổng thanh toán</span>
                        <span id="co-total" style="font-size:20px;color:#b8860b;font-weight:700;">
                            <?php echo number_format($cartTotal, 0, ',', '.'); ?>đ
                        </span>
                    </div>

                    <div style="margin-top:10px;">
                        <p style="font-size:14px;margin-bottom:6px;font-weight:600;">Phương thức thanh toán</p>
                        <label style="display:flex;align-items:center;gap:8px;font-size:14px;margin-bottom:4px;">
                            <input type="radio" name="payment" value="cod" checked>
                            Thanh toán khi nhận hàng (COD)
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:14px;">
                            <input type="radio" name="payment" value="qr">
                            Thanh toán qua mã QR
                        </label>
                        <div id="qr-box" style="display:none;margin-top:10px;padding:10px;border-radius:8px;border:1px dashed #d1d5db;text-align:center;">
                            <p style="font-size:13px;color:#4b5563;margin-bottom:8px;">Quét mã QR bên dưới để thanh toán</p>
                            <div style="width:140px;height:140px;margin:0 auto;background:#f3f4f6;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px;">
                                QR CODE
                            </div>
                        </div>
                    </div>

                    <button class="btn btn-primary" style="width:100%;margin-top:14px;" onclick="fakePlaceOrder()">
                        <i class="fas fa-lock"></i> Xác nhận đặt hàng
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    const formatVND = (v) => (Number(v) || 0).toLocaleString('vi-VN') + 'đ';

    function syncCheckoutTotals() {
        let subtotal = 0;
        document.querySelectorAll('[data-id][data-price]').forEach(row => {
            const price = parseInt(row.getAttribute('data-price'), 10) || 0;
            const qtyEl = row.querySelector('.co-qty');
            const qty = qtyEl ? parseInt(qtyEl.textContent, 10) || 0 : 0;
            subtotal += price * qty;
        });
        const subEl = document.getElementById('co-subtotal');
        const totalEl = document.getElementById('co-total');
        if (subEl) subEl.textContent = formatVND(subtotal);
        if (totalEl) totalEl.textContent = formatVND(subtotal);
    }

    function checkoutUpdateQty(id, action) {
        fetch('ajax/update-cart.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ item_id: id, action })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Không thể cập nhật số lượng.');
                return;
            }
            const row = document.querySelector(`[data-id="${id}"]`);
            if (!row) return;
            if (data.quantity <= 0) {
                row.remove();
                if (!document.querySelector('[data-id][data-price]')) {
                    window.location.reload();
                    return;
                }
            } else {
                const qtyEl = row.querySelector('.co-qty');
                const rowTotalEl = row.querySelector('.co-row-total');
                if (qtyEl) qtyEl.textContent = data.quantity;
                if (rowTotalEl) rowTotalEl.textContent = formatVND(data.item_total);
            }
            syncCheckoutTotals();

            // cập nhật badge giỏ hàng trên header
            const badge = document.querySelector('.cart-count');
            if (badge) badge.textContent = data.cart_count;
        })
        .catch(err => {
            console.error(err);
            alert('Có lỗi xảy ra, vui lòng thử lại.');
        });
    }

    function fakePlaceOrder() {
        const name = document.getElementById('ship-name')?.value.trim();
        const phone = document.getElementById('ship-phone')?.value.trim();
        const gmail = document.getElementById('ship-gmail')?.value.trim();
        const address = document.getElementById('ship-address')?.value.trim();
        if (!name || !phone || !address) {
            alert('Vui lòng nhập đầy đủ Họ tên, Số điện thoại và Địa chỉ giao hàng.');
            return;
        }
        const method = document.querySelector('input[name="payment"]:checked')?.value || 'cod';
        
        // Lấy danh sách item ID đã chọn từ URL hoặc từ các item hiện tại trên trang
        const urlParams = new URLSearchParams(window.location.search);
        const itemsParam = urlParams.get('items');
        const selectedItems = itemsParam ? itemsParam.split(',').map(id => parseInt(id)).filter(id => id > 0) : [];

        fetch('ajax/place-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, phone, gmail, address, payment: method, selectedItems: selectedItems })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Không thể tạo đơn hàng, vui lòng thử lại.');
                return;
            }
            alert('Đặt hàng thành công. Mã đơn: ' + data.order_code);
            window.location.href = 'your-orders.php';
        })
        .catch(err => {
            console.error(err);
            alert('Có lỗi xảy ra, vui lòng thử lại.');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        syncCheckoutTotals();
        const radios = document.querySelectorAll('input[name="payment"]');
        const qrBox = document.getElementById('qr-box');
        radios.forEach(r => {
            r.addEventListener('change', () => {
                if (r.value === 'qr' && r.checked) {
                    qrBox.style.display = 'block';
                } else if (r.value === 'cod' && r.checked) {
                    qrBox.style.display = 'none';
                }
            });
        });
    });
</script>

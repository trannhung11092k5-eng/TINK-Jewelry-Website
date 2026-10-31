<?php
// cart.php - Giỏ hàng
require_once '../config/db.php';
session_start();

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=cart.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy giỏ hàng của user
try {
    // Kiểm tra xem user có giỏ hàng chưa
    $stmt = $pdo->prepare("SELECT * FROM GIOHANG WHERE KhachHangID = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch();
    
    if (!$cart) {
        // Tạo giỏ hàng mới nếu chưa có
        $stmt = $pdo->prepare("INSERT INTO GIOHANG (KhachHangID) VALUES (?)");
        $stmt->execute([$user_id]);
        $cart_id = $pdo->lastInsertId();
    } else {
        $cart_id = $cart['GioHangID'];
    }
    
    // Lấy chi tiết giỏ hàng
    $stmt = $pdo->prepare("SELECT gc.*, sp.TenSP, sp.AnhSP, sp.GiaBan 
                          FROM GIOHANG_CHITIET gc 
                          JOIN SANPHAM sp ON gc.MaSP = sp.MaSP 
                          WHERE gc.GioHangID = ?");
    $stmt->execute([$cart_id]);
    $cart_items = $stmt->fetchAll();
    
    // Tính tổng tiền
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['ThanhTien'];
    }
    
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<?php include 'includes/header.php'; ?>

<div class="cart-container" style="max-width: 1200px; margin: 40px auto; padding: 0 20px;">
    <h1 style="font-family: 'Playfair Display', serif; margin-bottom: 30px;">Giỏ hàng của bạn</h1>
    
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart" style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-shopping-cart" style="font-size: 60px; color: #ddd; margin-bottom: 20px;"></i>
            <p style="font-size: 18px; color: #666; margin-bottom: 30px;">Giỏ hàng của bạn đang trống</p>
            <a href="products.php" class="btn btn-primary" style="padding: 12px 30px;">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="cart-items">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:12px;">
                <label style="display:flex; align-items:center; gap:8px; font-weight:600;">
                    <input type="checkbox" id="selectAll" checked>
                    Chọn tất cả
                </label>
                <span style="color:#666; font-size:14px;">Chỉ tính tổng cho sản phẩm được chọn</span>
            </div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd; width:60px;">
                            <input type="checkbox" id="selectAllHeader" checked>
                        </th>
                        <th style="padding: 15px; text-align: left; border-bottom: 2px solid #ddd;">Sản phẩm</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Đơn giá</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Số lượng</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Thành tiền</th>
                        <th style="padding: 15px; text-align: center; border-bottom: 2px solid #ddd;">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                    <tr style="border-bottom: 1px solid #eee;" data-id="<?php echo $item['ID']; ?>" data-price="<?php echo (int)$item['GiaBan']; ?>">
                        <td style="padding: 15px; text-align:center;">
                            <input type="checkbox" class="cart-checkbox" data-id="<?php echo $item['ID']; ?>" checked>
                        </td>
                        <td style="padding: 15px;">
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <img src="../<?php echo htmlspecialchars($item['AnhSP']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['TenSP']); ?>"
                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px;">
                                <div>
                                    <h3 style="margin: 0 0 5px 0; font-size: 16px;"><?php echo htmlspecialchars($item['TenSP']); ?></h3>
                                    <p style="margin: 0; color: #666; font-size: 14px;">Mã SP: <?php echo htmlspecialchars($item['MaSP']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <?php echo number_format($item['GiaBan'], 0, ',', '.'); ?>đ
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <div style="display: inline-flex; align-items: center; gap: 10px;">
                                <button class="btn-qty" onclick="updateQuantity('<?php echo $item['ID']; ?>', 'decrease')">-</button>
                                <span id="qty-<?php echo $item['ID']; ?>" class="qty-value" style="min-width: 30px;"><?php echo $item['SoLuong']; ?></span>
                                <button class="btn-qty" onclick="updateQuantity('<?php echo $item['ID']; ?>', 'increase')">+</button>
                            </div>
                        </td>
                        <td style="padding: 15px; text-align: center; font-weight: bold; color: #b8860b;">
                            <span id="row-total-<?php echo $item['ID']; ?>" class="row-total"><?php echo number_format($item['ThanhTien'], 0, ',', '.'); ?>đ</span>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <button class="btn-remove" onclick="removeItem('<?php echo $item['ID']; ?>')">
                                <i class="fas fa-trash"></i> Xóa
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Tổng tiền và thanh toán -->
            <div style="margin-top: 30px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <a href="products.php" style="color: #1a237e; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Tiếp tục mua sắm
                    </a>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 20px; margin-bottom: 10px;">
                        <strong>Tổng tiền: </strong>
                        <span id="cartTotal" style="color: #b8860b; font-size: 24px;"><?php echo number_format($total, 0, ',', '.'); ?>đ</span>
                    </p>
                    <button type="button" id="btnCheckout" class="btn btn-primary" style="padding: 12px 40px; font-size: 16px;">
                        <i class="fas fa-shopping-bag"></i> Tiến hành thanh toán
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .btn-qty {
        width: 30px;
        height: 30px;
        border: 1px solid #ddd;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 16px;
    }
    
    .btn-qty:hover {
        background: #f0f0f0;
    }
    
    .btn-remove {
        padding: 6px 12px;
        background: #e53935;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .btn-remove:hover {
        background: #c62828;
    }

.cart-checkbox {
    width: 18px;
    height: 18px;
}
</style>

<script>
    const formatCurrency = (value) => {
        const number = Number(value) || 0;
        return number.toLocaleString('vi-VN') + 'đ';
    };

    function updateCartBadge(count) {
        const badge = document.querySelector('.cart-count');
        if (badge) {
            badge.textContent = count;
        }
    }

    function updateCartTotals() {
        let total = 0;
        document.querySelectorAll('.cart-checkbox:checked').forEach(cb => {
            const row = cb.closest('tr');
            const price = parseInt(row.getAttribute('data-price'), 10) || 0;
            const qtyEl = row.querySelector('.qty-value');
            const qty = qtyEl ? parseInt(qtyEl.textContent, 10) || 0 : 0;
            total += price * qty;
        });
        const totalEl = document.getElementById('cartTotal');
        if (totalEl) totalEl.textContent = formatCurrency(total);
    }

    function syncSelectAll() {
        const checkboxes = document.querySelectorAll('.cart-checkbox');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
        document.getElementById('selectAll').checked = allChecked;
        document.getElementById('selectAllHeader').checked = allChecked;
        // Nếu không có item nào được chọn, tổng tiền = 0
        if (!anyChecked) {
            const totalEl = document.getElementById('cartTotal');
            if (totalEl) totalEl.textContent = formatCurrency(0);
        }
    }

    function handleEmptyCart() {
        const rows = document.querySelectorAll('tbody tr');
        if (rows.length === 0) {
            window.location.reload();
        }
    }

    function updateQuantity(itemId, action) {
        fetch('ajax/update-cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId,
                action: action
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = document.querySelector(`tr[data-id="${itemId}"]`);
                if (!row) return;

                if (data.quantity <= 0) {
                    row.remove();
                } else {
                    const qtyEl = row.querySelector('.qty-value');
                    const totalEl = row.querySelector('.row-total');
                    if (qtyEl) qtyEl.textContent = data.quantity;
                    if (totalEl) totalEl.textContent = formatCurrency(data.item_total);
                }

                updateCartBadge(data.cart_count);
                updateCartTotals();
                syncSelectAll();
                handleEmptyCart();
            } else {
                alert(data.message || 'Có lỗi xảy ra!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra!');
        });
    }
    
    function removeItem(itemId) {
        if (confirm('Bạn có chắc muốn xóa sản phẩm này khỏi giỏ hàng?')) {
            fetch('ajax/remove-cart-item.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-id="${itemId}"]`);
                    if (row) row.remove();
                    updateCartBadge(data.cart_count);
                    updateCartTotals();
                    syncSelectAll();
                    handleEmptyCart();
                } else {
                    alert(data.message || 'Có lỗi xảy ra!');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Có lỗi xảy ra!');
            });
        }
    }

    // Checkbox handlers
    document.addEventListener('DOMContentLoaded', () => {
        const selectAll = document.getElementById('selectAll');
        const selectAllHeader = document.getElementById('selectAllHeader');
        const toggleAll = (checked) => {
            document.querySelectorAll('.cart-checkbox').forEach(cb => cb.checked = checked);
            updateCartTotals();
            syncSelectAll();
        };

        if (selectAll) selectAll.addEventListener('change', (e) => toggleAll(e.target.checked));
        if (selectAllHeader) selectAllHeader.addEventListener('change', (e) => toggleAll(e.target.checked));

        document.querySelectorAll('.cart-checkbox').forEach(cb => {
            cb.addEventListener('change', () => {
                updateCartTotals();
                syncSelectAll();
            });
        });

        // Nút thanh toán chỉ mang theo các item đã chọn
        const btnCheckout = document.getElementById('btnCheckout');
        if (btnCheckout) {
            btnCheckout.addEventListener('click', () => {
                const selected = Array.from(document.querySelectorAll('.cart-checkbox:checked'))
                    .map(cb => cb.getAttribute('data-id'))
                    .filter(Boolean);

                if (!selected.length) {
                    alert('Vui lòng chọn ít nhất một sản phẩm để thanh toán.');
                    return;
                }

                const params = new URLSearchParams();
                params.set('items', selected.join(','));
                window.location.href = 'checkout.php?' + params.toString();
            });
        }

        updateCartTotals();
    });
</script>

<?php include 'includes/footer.php'; ?>
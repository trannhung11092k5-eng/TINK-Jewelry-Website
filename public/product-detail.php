<?php
// product-detail.php
session_start();
require_once '../config/db.php';

// Lấy mã sản phẩm từ URL
$productId = isset($_GET['id']) ? $_GET['id'] : '';

if (empty($productId)) {
    header('Location: homepage.php');
    exit();
}

// Lấy thông tin sản phẩm từ database
try {
    $stmt = $pdo->prepare("SELECT * FROM SANPHAM WHERE MaSP = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: homepage.php');
        exit();
    }
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}

$isLoggedIn = isset($_SESSION['user_id']);
?>
<?php include 'includes/header.php'; ?>
<style>
    /* Additional styles for product detail page */
    .product-detail-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px 20px;
    }
    
    .product-detail {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 50px;
        margin-bottom: 40px;
    }
    
    @media (max-width: 768px) {
        .product-detail {
            grid-template-columns: 1fr;
            gap: 30px;
        }
    }
    
    .product-images {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .main-image {
        width: 100%;
        height: 400px;
        overflow: hidden;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    
    .main-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }
    
    .main-image:hover img {
        transform: scale(1.02);
    }
    
    .thumbnail-images {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding: 10px 0;
    }
    
    .thumbnail {
        width: 80px;
        height: 80px;
        border-radius: 5px;
        overflow: hidden;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.3s ease;
    }
    
    .thumbnail.active {
        border-color: #1a237e;
    }
    
    .thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .product-info {
        padding: 20px 0;
    }
    
    .product-code {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
        display: block;
    }
    
    .product-title {
        font-family: 'Playfair Display', serif;
        font-size: 32px;
        margin-bottom: 15px;
        color: #000;
    }
    
    .product-price {
        font-size: 28px;
        color: #b8860b;
        font-weight: 700;
        margin-bottom: 20px;
    }
    
    .product-category {
        display: inline-block;
        background: #f0f2f5;
        padding: 5px 15px;
        border-radius: 20px;
        margin-bottom: 20px;
        color: #666;
        font-size: 14px;
    }
    
    .product-stock {
        margin-bottom: 30px;
        padding: 15px;
        background: #f9f9f9;
        border-radius: 8px;
        border-left: 4px solid #28a745;
    }
    
    .in-stock {
        color: #28a745;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .product-actions {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }
    
    /* Related products section */
    .related-products {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .related-products h2 {
        font-family: 'Playfair Display', serif;
        font-size: 28px;
        margin-bottom: 30px;
        text-align: center;
    }
    
    .related-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 25px;
    }
    
    /* Breadcrumb */
    .breadcrumb {
        padding: 15px 0;
        margin-bottom: 10px;
        color: #666;
        font-size: 14px;
        border-bottom: 1px solid #eee;
    }
    
    .breadcrumb a {
        color: #666;
        text-decoration: none;
        transition: color 0.3s;
    }
    
    .breadcrumb a:hover {
        color: #1a237e;
    }
    
    .breadcrumb span {
        margin: 0 10px;
    }
    
    .current-page {
        color: #1a237e;
        font-weight: 500;
    }
    
    /* Success message */
    .success-message {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #28a745;
        color: white;
        padding: 15px 25px;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        display: none;
        z-index: 10000;
        animation: slideIn 0.3s ease;
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
    
    /* Loading overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.8);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }
    
    .spinner {
        width: 50px;
        height: 50px;
        border: 5px solid #f0f0f0;
        border-top: 5px solid #1a237e;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <div class="product-detail-container">
        <a href="homepage.php">Trang chủ</a>
        <span>></span>
        <a href="products.php">Sản phẩm</a>
        <span>></span>
        <span class="current-page"><?php echo htmlspecialchars($product['TenSP']); ?></span>
    </div>
</div>

    <!-- Product Detail -->
    <div class="product-detail-container">
        <div class="product-detail">
            <!-- Product Images -->
            <div class="product-images">
                <div class="main-image">
                    <img id="mainImage" src="../<?php echo htmlspecialchars($product['AnhSP']); ?>" alt="<?php echo htmlspecialchars($product['TenSP']); ?>">
                </div>
            </div>
            
            <!-- Product Info -->
            <div class="product-info">
                <span class="product-code">Mã SP: <?php echo htmlspecialchars($product['MaSP']); ?></span>
                <h1 class="product-title"><?php echo htmlspecialchars($product['TenSP']); ?></h1>
                <div class="product-price"><?php echo number_format($product['GiaBan'], 0, ',', '.'); ?>đ</div>
                <div class="product-category">
                    <i class="fas fa-tag"></i> 
                    <a href="products.php?category=<?php echo urlencode($product['TheLoai']); ?>" 
                       style="color: #1a237e; text-decoration: none; transition: color 0.3s;"
                       onmouseover="this.style.color='#005bb5'" 
                       onmouseout="this.style.color='#1a237e'">
                        <?php echo htmlspecialchars($product['TheLoai']); ?>
                    </a>
                </div>
                
                <div class="product-stock">
                    <div class="in-stock">
                        <i class="fas fa-check-circle"></i>
                        <span>Còn <?php echo $product['SoLuongTon']; ?> sản phẩm trong kho</span>
                    </div>
                </div>
                
                <div class="product-actions">
                    <button class="btn btn-primary" onclick="addToCart()">
                        <i class="fas fa-cart-plus"></i> Thêm vào giỏ hàng
                    </button>
                    <button class="btn btn-secondary" onclick="buyNow()">
                        <i class="fas fa-bolt"></i> Mua ngay
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <div class="related-products">
            <h2>Sản phẩm liên quan</h2>
            <div class="related-grid">
                <?php
                // Lấy sản phẩm cùng thể loại
                try {
                    $relatedStmt = $pdo->prepare("SELECT * FROM SANPHAM WHERE TheLoai = ? AND MaSP != ? LIMIT 4");
                    $relatedStmt->execute([$product['TheLoai'], $product['MaSP']]);
                    $relatedProducts = $relatedStmt->fetchAll();
                    
                    if (empty($relatedProducts)) {
                        // Nếu không có sản phẩm cùng thể loại, lấy 4 sản phẩm mới nhất
                        $relatedStmt = $pdo->query("SELECT * FROM SANPHAM WHERE MaSP != '{$product['MaSP']}' ORDER BY MaSP DESC LIMIT 4");
                        $relatedProducts = $relatedStmt->fetchAll();
                    }
                    
                    foreach ($relatedProducts as $relatedProduct) {
                        echo '<div class="product-card" onclick="window.location=\'product-detail.php?id=' . $relatedProduct['MaSP'] . '\'">
                                <div class="product-image">
                                    <img src="../' . htmlspecialchars($relatedProduct['AnhSP']) . '" alt="' . htmlspecialchars($relatedProduct['TenSP']) . '">
                                </div>
                                <div class="product-info">
                                    <h3 class="product-name">' . htmlspecialchars($relatedProduct['TenSP']) . '</h3>
                                    <div class="product-price">' . number_format($relatedProduct['GiaBan'], 0, ',', '.') . 'đ</div>
                                    <div class="product-sold">Mã: ' . htmlspecialchars($relatedProduct['MaSP']) . '</div>
                                </div>
                              </div>';
                    }
                } catch (PDOException $e) {
                    echo '<p>Không thể tải sản phẩm liên quan.</p>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div class="success-message" id="successMessage">
        <i class="fas fa-check-circle"></i> <span id="messageText">Đã thêm vào giỏ hàng!</span>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>
<?php include 'includes/footer.php'; ?>

<script>
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    const productId = '<?php echo $product['MaSP']; ?>';

    // Function to change main image (không cần nữa vì chỉ có 1 ảnh)
    
    function updateCartCount(count) {
        const badge = document.querySelector('.cart-count');
        if (badge && typeof count !== 'undefined') {
            badge.textContent = count;
        }
    }

    async function addToCart() {
        if (!isLoggedIn) {
            if (confirm('Bạn cần đăng nhập để thêm sản phẩm vào giỏ. Đến trang đăng nhập?')) {
                window.location.href = 'login.php?redirect=product-detail.php?id=<?php echo urlencode($product['MaSP']); ?>';
            }
            return false;
        }

        showLoading();
        try {
            const response = await fetch('ajax/add-to-cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ MaSP: productId, quantity: 1 })
            });
            const data = await response.json();
            hideLoading();

            if (data.success) {
                updateCartCount(data.cart_count);
                showSuccessMessage(data.message || 'Đã thêm vào giỏ hàng!');
                return true;
            }

            alert(data.message || 'Không thể thêm vào giỏ hàng.');
            return false;
        } catch (err) {
            hideLoading();
            console.error(err);
            alert('Có lỗi xảy ra, vui lòng thử lại.');
            return false;
        }
    }
    
    async function buyNow() {
        const ok = await addToCart();
        if (ok) {
            window.location.href = 'cart.php';
        }
    }
    
    // Message functions
    function showSuccessMessage(text) {
        const message = document.getElementById('successMessage');
        document.getElementById('messageText').textContent = text;
        message.style.display = 'block';
        
        setTimeout(() => {
            message.style.display = 'none';
        }, 3000);
    }
    
    function showLoading() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    }
    
    function hideLoading() {
        document.getElementById('loadingOverlay').style.display = 'none';
    }
</script>
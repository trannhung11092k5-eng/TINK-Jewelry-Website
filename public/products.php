<?php
// products.php - Danh sách sản phẩm
require_once '../config/db.php';

// Lấy tham số từ URL
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'all';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12; // Số sản phẩm mỗi trang
$offset = ($page - 1) * $limit;

// Xây dựng câu truy vấn
$where = [];
$params = [];

if ($category) {
    $where[] = "TheLoai = ?";
    $params[] = $category;
}

// Sắp xếp
switch ($sort) {
    case 'price_asc':
        $order_by = "ORDER BY GiaBan ASC";
        break;
    case 'price_desc':
        $order_by = "ORDER BY GiaBan DESC";
        break;
    default:
        // Tất cả sản phẩm - sắp xếp theo mã sản phẩm
        $order_by = "ORDER BY MaSP ASC";
}

// Đếm tổng số sản phẩm và lấy sản phẩm
try {
    $where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $count_sql = "SELECT COUNT(*) as total FROM SANPHAM $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_products / $limit);
    
    // Lấy sản phẩm (bind LIMIT/OFFSET as integers)
    $sql = "SELECT * FROM SANPHAM $where_clause $order_by LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);

    // Bind WHERE params first (if any)
    $bindIndex = 1;
    foreach ($params as $p) {
        $stmt->bindValue($bindIndex++, $p);
    }

    // Bind limit and offset as integers
    $stmt->bindValue($bindIndex++, (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue($bindIndex++, (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    $products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<?php include 'includes/header.php'; ?>

<div class="product-sections">
    <!-- Breadcrumb -->
    <div class="breadcrumb" style="max-width: 1200px; margin: 0 auto 30px; padding: 0 20px; color: #666;">
        <a href="homepage.php">Trang chủ</a> &gt; 
        <a href="products.php">Sản phẩm</a>
        <?php if ($category): ?>
        &gt; <span><?php echo htmlspecialchars($category); ?></span>
        <?php endif; ?>
    </div>
    
    <!-- Filter và Sort -->
    <div class="section-header">
        <h2>
            <?php 
            if ($category) {
                echo htmlspecialchars($category);
            } else {
                echo "Tất cả sản phẩm";
            }
            ?>
        </h2>
        <div class="section-tabs">
            <a href="products.php<?php echo $category ? '?category=' . urlencode($category) : ''; ?>" 
               class="tab-btn <?php echo ($sort == 'all' || empty($sort)) ? 'active' : ''; ?>">Tất cả sản phẩm</a>
            <a href="products.php?sort=price_asc<?php echo $category ? '&category=' . urlencode($category) : ''; ?>" 
               class="tab-btn <?php echo $sort == 'price_asc' ? 'active' : ''; ?>">Giá thấp đến cao</a>
            <a href="products.php?sort=price_desc<?php echo $category ? '&category=' . urlencode($category) : ''; ?>" 
               class="tab-btn <?php echo $sort == 'price_desc' ? 'active' : ''; ?>">Giá cao đến thấp</a>
        </div>
    </div>
    
    <!-- Danh sách sản phẩm -->
    <div class="product-grid">
        <?php if ($products): ?>
            <?php foreach ($products as $product): ?>
                <div class="product-card" onclick="window.location='product-detail.php?id=<?php echo $product['MaSP']; ?>'">
                    <div class="product-image">
                        <img src="../<?php echo htmlspecialchars($product['AnhSP']); ?>" 
                             alt="<?php echo htmlspecialchars($product['TenSP']); ?>">
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars($product['TenSP']); ?></h3>
                        <div class="product-price"><?php echo number_format($product['GiaBan'], 0, ',', '.'); ?>đ</div>
                        <div class="product-sold">Mã: <?php echo htmlspecialchars($product['MaSP']); ?></div>
                        <div class="product-sold">Loại: <?php echo htmlspecialchars($product['TheLoai']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column: 1/-1; text-align: center; padding: 40px;">Không tìm thấy sản phẩm nào.</p>
        <?php endif; ?>
    </div>
    
    <!-- Phân trang -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo ($sort && $sort != 'all') ? '&sort=' . $sort : ''; ?>">&laquo; Trước</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <a href="#" class="active"><?php echo $i; ?></a>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo ($sort && $sort != 'all') ? '&sort=' . $sort : ''; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?><?php echo ($sort && $sort != 'all') ? '&sort=' . $sort : ''; ?>">Sau &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 40px;
    }
    
    .pagination a {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s;
    }
    
    .pagination a:hover,
    .pagination a.active {
        background: #1a237e;
        color: white;
        border-color: #1a237e;
    }
    
    .breadcrumb a {
        color: #666;
        text-decoration: none;
    }
    
    .breadcrumb a:hover {
        color: #1a237e;
    }
</style>

<?php include 'includes/footer.php'; ?>
<?php
// search.php - Tìm kiếm sản phẩm
require_once '../config/db.php';

$keyword = isset($_GET['q']) ? $_GET['q'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Xây dựng câu truy vấn
$where = [];
$params = [];

if ($keyword) {
    $where[] = "(TenSP LIKE ? OR MaSP LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

if ($category) {
    $where[] = "TheLoai = ?";
    $params[] = $category;
}

$where_clause = $where ? "WHERE " . implode(" AND ", $where) : "";

try {
    // Đếm tổng số sản phẩm
    $count_sql = "SELECT COUNT(*) as total FROM SANPHAM $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_products / $limit);
    
    // Lấy sản phẩm (bind LIMIT/OFFSET as integers)
    $sql = "SELECT * FROM SANPHAM $where_clause ORDER BY MaSP DESC LIMIT ? OFFSET ?";
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
    
    // Lấy danh sách thể loại cho filter
    $categories_stmt = $pdo->query("SELECT DISTINCT TheLoai FROM SANPHAM ORDER BY TheLoai");
    $categories = $categories_stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Lỗi truy vấn: " . $e->getMessage());
}
?>
<?php include 'includes/header.php'; ?>

<div class="product-sections">
    <!-- Search Form -->
    <div style="max-width: 800px; margin: 0 auto 40px;">
        <form action="search.php" method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="q" value="<?php echo htmlspecialchars($keyword); ?>" 
                   placeholder="Nhập tên sản phẩm hoặc mã sản phẩm..." 
                   style="flex: 1; padding: 12px 20px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;">
            <select name="category" style="padding: 0 20px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;">
                <option value="">Tất cả thể loại</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['TheLoai']); ?>" 
                            <?php echo $category == $cat['TheLoai'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['TheLoai']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary" style="padding: 0 30px;">
                <i class="fas fa-search"></i> Tìm kiếm
            </button>
        </form>
    </div>
    
    <!-- Kết quả tìm kiếm -->
    <div class="section-header">
        <h2>
            <?php if ($keyword || $category): ?>
                Kết quả tìm kiếm 
                <?php if ($keyword): ?>cho "<?php echo htmlspecialchars($keyword); ?>"<?php endif; ?>
                <?php if ($category): ?>- <?php echo htmlspecialchars($category); ?><?php endif; ?>
                <span style="color: #666; font-size: 16px;">(<?php echo $total_products; ?> kết quả)</span>
            <?php else: ?>
                Tất cả sản phẩm
            <?php endif; ?>
        </h2>
    </div>
    
    <?php if ($keyword || $category): ?>
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
                <p style="grid-column: 1/-1; text-align: center; padding: 40px;">
                    Không tìm thấy sản phẩm nào phù hợp.
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Phân trang -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($keyword); ?>&category=<?php echo urlencode($category); ?>">&laquo; Trước</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <a href="#" class="active"><?php echo $i; ?></a>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&q=<?php echo urlencode($keyword); ?>&category=<?php echo urlencode($category); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($keyword); ?>&category=<?php echo urlencode($category); ?>">Sau &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<?php

$pageTitle = 'Shop Laptops - Laptro';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Get filter parameters
$brand = $_GET['brand'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$ram = $_GET['ram'] ?? '';
$cpu = $_GET['cpu'] ?? '';
$storage = $_GET['storage'] ?? '';

// Whitelists for filters
$allowedRam = ['8GB','16GB','32GB'];
$allowedCpu = ['Intel Core i5','Intel Core i7','Intel Core i9','AMD Ryzen 5','AMD Ryzen 7','AMD Ryzen 9','Apple M'];
$allowedStorage = ['256GB','512GB','1TB'];

if ($ram && !in_array($ram, $allowedRam, true)) { $ram = ''; }
if ($cpu && !array_filter($allowedCpu, function($c) use ($cpu){ return $cpu === $c || ($c === 'Apple M' && strpos($cpu,'Apple M')!==false); })) { $cpu = ''; }
if ($storage && !in_array($storage, $allowedStorage, true)) { $storage = ''; }
$search = $_GET['search'] ?? '';

// Build dynamic WHERE and params
$where = " WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR description LIKE ? OR brand LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($brand) {
    $where .= " AND brand = ?";
    $params[] = $brand;
}

if ($minPrice) {
    $where .= " AND price >= ?";
    $params[] = $minPrice;
}

if ($maxPrice) {
    $where .= " AND price <= ?";
    $params[] = $maxPrice;
}

if ($ram) {
    $where .= " AND ram LIKE ?";
    $params[] = "%$ram%";
}

if ($cpu) {
    $where .= " AND cpu LIKE ?";
    $params[] = "%$cpu%";
}

if ($storage) {
    $where .= " AND storage LIKE ?";
    $params[] = "%$storage%";
}

// Pagination
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Total count
$countStmt = $conn->prepare("SELECT COUNT(*) FROM products" . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, (int)ceil($total / $perPage));
if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }

// Fetch current page
$listSql = "SELECT * FROM products" . $where . " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($listSql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Prefetch ratings in one query to avoid N+1
$ratingsByProduct = [];
if ($products) {
    $ids = array_column($products, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rStmt = $conn->prepare("SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS total_reviews FROM reviews WHERE product_id IN ($in) GROUP BY product_id");
    $rStmt->execute($ids);
    foreach ($rStmt->fetchAll() as $row) {
        $ratingsByProduct[(int)$row['product_id']] = [
            'average' => round($row['avg_rating'] ?? 0, 1),
            'total' => (int)($row['total_reviews'] ?? 0)
        ];
    }
}

// Get unique brands
$brandsStmt = $conn->query("SELECT DISTINCT brand FROM products ORDER BY brand");
$brands = $brandsStmt->fetchAll(PDO::FETCH_COLUMN);

// Helper func
function getProductImage($product) {
    if (!empty($product['pictures'])) {
        $images = json_decode($product['pictures'], true);
        if (is_array($images) && !empty($images)) {
            return htmlspecialchars(url(ltrim($images[0], '/'))); 
        }
    }
    
    // Fallback 
    if (!empty($product['main_image']) && $product['main_image'] !== 'placeholder.jpg') {
        return asset('products/' . htmlspecialchars($product['main_image']));
    }
    
    // Extra fallback
    return 'https://via.placeholder.com/400x300?text=' . urlencode($product['name']);
}
?>

<div class="container section-padding">
    <h1 class="mb-4">Laptops</h1>
    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="filter-section">
                <h4 class="mb-4">Filters</h4>
                
                <form method="GET" action="<?php echo url('products.php'); ?>" id="filterForm">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               placeholder="Search laptops..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="brand">Brand</label>
                        <select id="brand" name="brand" class="form-control">
                            <option value="">All Brands</option>
                            <?php foreach ($brands as $b): ?>
                                <option value="<?php echo $b; ?>" 
                                        <?php echo $brand === $b ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($b); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>Price Range</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="visually-hidden" for="min_price">Minimum price</label>
                                <input type="number" id="min_price" name="min_price" class="form-control" inputmode="decimal" 
                                       placeholder="Min" 
                                       value="<?php echo htmlspecialchars($minPrice); ?>">
                            </div>
                            <div class="col-6">
                                <label class="visually-hidden" for="max_price">Maximum price</label>
                                <input type="number" id="max_price" name="max_price" class="form-control" inputmode="decimal" 
                                       placeholder="Max" 
                                       value="<?php echo htmlspecialchars($maxPrice); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label for="ram">RAM</label>
                        <select id="ram" name="ram" class="form-control">
                            <option value="">Any RAM</option>
                            <option value="8GB" <?php echo $ram === '8GB' ? 'selected' : ''; ?>>8GB</option>
                            <option value="16GB" <?php echo $ram === '16GB' ? 'selected' : ''; ?>>16GB</option>
                            <option value="32GB" <?php echo $ram === '32GB' ? 'selected' : ''; ?>>32GB</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="cpu">Processor</label>
                        <select id="cpu" name="cpu" class="form-control">
                            <option value="">Any CPU</option>
                            <option value="Intel Core i5" <?php echo strpos($cpu, 'i5') !== false ? 'selected' : ''; ?>>Intel Core i5</option>
                            <option value="Intel Core i7" <?php echo strpos($cpu, 'i7') !== false ? 'selected' : ''; ?>>Intel Core i7</option>
                            <option value="Intel Core i9" <?php echo strpos($cpu, 'i9') !== false ? 'selected' : ''; ?>>Intel Core i9</option>
                            <option value="AMD Ryzen 5" <?php echo strpos($cpu, 'Ryzen 5') !== false ? 'selected' : ''; ?>>AMD Ryzen 5</option>
                            <option value="AMD Ryzen 7" <?php echo strpos($cpu, 'Ryzen 7') !== false ? 'selected' : ''; ?>>AMD Ryzen 7</option>
                            <option value="AMD Ryzen 9" <?php echo strpos($cpu, 'Ryzen 9') !== false ? 'selected' : ''; ?>>AMD Ryzen 9</option>
                            <option value="Apple M" <?php echo strpos($cpu, 'Apple M') !== false ? 'selected' : ''; ?>>Apple M Series</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="storage">Storage</label>
                        <select id="storage" name="storage" class="form-control">
                            <option value="">Any Storage</option>
                            <option value="256GB" <?php echo strpos($storage, '256GB') !== false ? 'selected' : ''; ?>>256GB SSD</option>
                            <option value="512GB" <?php echo strpos($storage, '512GB') !== false ? 'selected' : ''; ?>>512GB SSD</option>
                            <option value="1TB" <?php echo strpos($storage, '1TB') !== false ? 'selected' : ''; ?>>1TB SSD</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-2">Apply Filters</button>
                    <a href="<?php echo url('products.php'); ?>" class="btn btn-outline w-100">Clear Filters</a>
                </form>
            </div>
        </div>
        
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>All Laptops</h2>
                <span class="text-muted"><?php echo count($products); ?> products found</span>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-laptop fa-4x text-muted mb-3"></i>
                    <h4>No laptops found</h4>
                    <p class="text-muted">Try adjusting your filters</p>
                    <a href="<?php echo url('products.php'); ?>" class="btn btn-primary">View All Products</a>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($products as $product): 
                        $rating = $ratingsByProduct[$product['id']] ?? ['average' => 0, 'total' => 0];
                        $productImage = getProductImage($product);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="product-card">
                                <a href="<?php echo url('product.php?id=' . $product['id']); ?>">
                                    <?php 
                                        // Server-side fallback to avoid 404 waterfalls
                                        $imgCandidate = $productImage;
                                        $imgFs = null;
                                        if (strpos($imgCandidate, BASE_URL) === 0) {
                                            $rel = substr($imgCandidate, strlen(BASE_URL) + 1); // strip base + '/'
                                            $imgFs = realpath(__DIR__ . '/../' . str_replace('..','',$rel));
                                        }
                                        $imgSrc = ($imgFs && file_exists($imgFs)) ? $imgCandidate : 'https://via.placeholder.com/400x300?text=' . urlencode($product['name']);
                                    ?>
                                    <img src="<?php echo $imgSrc; ?>" loading="lazy" width="400" height="300" sizes="(min-width: 992px) 33vw, (min-width: 768px) 50vw, 100vw"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="product-card-img" data-fallback="https://via.placeholder.com/400x300?text=<?php echo urlencode($product['name']); ?>">
                                </a>
                                <div class="product-card-body">
                                    <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
                                    <h3 class="product-title">
                                        <a href="<?php echo url('product.php?id=' . $product['id']); ?>">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </a>
                                    </h3>
                                    <div class="product-specs">
                                        <?php if (!empty($product['cpu'])): ?>
                                            <?php echo htmlspecialchars($product['cpu']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($product['ram'])): ?>
                                            • <?php echo htmlspecialchars($product['ram']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($product['storage'])): ?>
                                            • <?php echo htmlspecialchars($product['storage']); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-rating">
                                        <?php echo renderStars($rating['average']); ?>
                                        <span class="text-muted ms-2">(<?php echo $rating['total']; ?>)</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                                        <a href="<?php echo url('product.php?id=' . $product['id']); ?>" class="btn btn-outline btn-sm">View</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($lastPage > 1): ?>
                    <?php
                        // Preserve filters in query string
                        $qs = $_GET;
                        $makeLink = function($p) use ($qs) {
                            $qs['page'] = $p;
                            return url('products.php?' . http_build_query($qs));
                        };
                    ?>
                    <nav aria-label="Product pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $makeLink(max(1, $page-1)); ?>">Prev</a>
                            </li>
                            <?php for ($p = 1; $p <= $lastPage; $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $makeLink($p); ?>"><?php echo $p; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $lastPage ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $makeLink(min($lastPage, $page+1)); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.product-card-img {
    width: 100%;
    height: 250px;
    object-fit: cover;
    border-radius: 8px 8px 0 0;
}

.product-card:hover .product-card-img {
    transform: scale(1.05);
    transition: transform 0.3s ease;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

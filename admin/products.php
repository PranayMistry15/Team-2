<?php

$pageTitle = 'Manage Products - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

if (!isAdmin()) { redirect(url('index.php')); }

$conn = getDBConnection();

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    verify_csrf_or_abort();
    $id = (int)($_POST['product_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    if ($stmt->execute([$id])) {
        setFlash('success', 'Product deleted!');
    }
    redirect(url('admin/products.php'));
}

// Build WHERE
$where = ' WHERE 1=1';
$params = [];
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR brand LIKE ?)';
    $like = "%$q%";
    $params[] = $like; $params[] = $like;
}


$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$countStmt = $conn->prepare("SELECT COUNT(*) FROM products" . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, (int)ceil($total / $perPage));
if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }

// Get current page products
$stmt = $conn->prepare("SELECT * FROM products" . $where . " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

require_once '../includes/header.php';
?>
<aside class="admin-sidebar">
  <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link active" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link" href="<?php echo url('admin/stock-receipts.php'); ?>"><i class="fas fa-boxes-stacked me-2"></i>Stock In</a>
        <a class="nav-link" href="<?php echo url('admin/inventory-reports.php'); ?>"><i class="fas fa-chart-column me-2"></i>Reports</a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
        <a class="nav-link" href="<?php echo url('admin/assistance.php'); ?>"><i class="fas fa-headset me-2"></i>Assistance</a>
        <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
 </aside>

<main class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Product Management</h1>
        <form class="d-flex gap-2" method="GET" action="<?php echo url('admin/products.php'); ?>" role="search" aria-label="Search products">
            <label for="q" class="visually-hidden">Search</label>
            <input id="q" name="q" class="form-control" placeholder="Search name or brand" value="<?php echo htmlspecialchars($q); ?>">
            <button class="btn btn-outline" type="submit">Search</button>
        </form>
        <a href="<?php echo url('admin/product-form.php'); ?>" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add New Product</a>
    </div>
    
    <div class="dashboard-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$products): ?>
                        <tr><td colspan="8" class="text-center text-muted">No products found</td></tr>
                    <?php endif; ?>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars(resolve_product_image($product['main_image'], $product['name'])); ?>"
                                     style="width: 50px; height: 50px; object-fit: cover;"
                                     onerror="this.src='https://via.placeholder.com/50'">
                            </td>
                            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($product['brand']); ?></td>
                            <td><?php echo htmlspecialchars($product['category'] ?: 'uncategorized'); ?></td>
                            <td><?php echo formatPrice($product['price']); ?></td>
                            <td>
                                <span class="badge <?php echo $product['stock'] < 5 ? 'badge-warning' : 'badge-success'; ?>">
                                    <?php echo $product['stock']; ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo url('admin/product-form.php?id=' . $product['id']); ?>" 
                                   class="btn btn-sm btn-outline">Edit</a>
                                <form method="POST" action="<?php echo url('admin/products.php'); ?>" class="d-inline">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">
                                    <button type="submit" name="delete_product" value="1"
                                        class="btn btn-sm btn-outline-danger" data-confirm="Delete this product?">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>

<?php if ($lastPage > 1): ?>
    <nav aria-label="Products pagination" class="admin-main" style="margin-left: 0; padding-top: 0;">
        <ul class="pagination">
            <?php $makeLink = function($p) use ($q){ $qs = []; if ($q!=='') $qs['q']=$q; $qs['page']=$p; return url('admin/products.php?' . http_build_query($qs)); }; ?>
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

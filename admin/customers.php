<?php
$pageTitle = 'Manage Customers - Admin';
require_once '../includes/header.php';

// Check admin access
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

 // Connection
$conn = getDBConnection();

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

// Pagination + WHERE
$where = ' WHERE u.is_admin = 0';
$params = [];
if ($q !== '') { $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $like = "%$q%"; $params[] = $like; $params[] = $like; }

$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$countStmt = $conn->prepare("SELECT COUNT(*) FROM users u" . $where);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, (int)ceil($total / $perPage));
if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }

// Gets users order count and total spent
$stmt = $conn->prepare("SELECT u.*, COUNT(o.id) AS total_orders, SUM(o.total_amount) AS total_spent
                        FROM users u
                        LEFT JOIN orders o ON u.id = o.user_id" .
                        $where .
                        " GROUP BY u.id ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$customers = $stmt->fetchAll();

?>
<aside class="admin-sidebar">
  <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link active" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
         <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
 </aside>

<main class="admin-main">
    <h1 class="mb-4">Customer Management</h1>
    <form class="d-flex gap-2 mb-3" method="GET" action="<?php echo url('admin/customers.php'); ?>" role="search" aria-label="Search customers">
        <label for="q" class="visually-hidden">Search</label>
        <input id="q" name="q" class="form-control" placeholder="Name or email" value="<?php echo htmlspecialchars($q); ?>">
        <button class="btn btn-outline" type="submit">Search</button>
    </form>
    <div class="dashboard-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Registered</th>
                    <th>Total Orders</th>
                    <th>Total Spent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$customers): ?>
                    <tr><td colspan="6" class="text-center text-muted">No customers found</td></tr>
                <?php endif; ?>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo $customer['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td><?php echo $customer['total_orders'] ?? 0; ?></td>
                        <td><strong><?php echo formatPrice($customer['total_spent'] ?? 0); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php if ($lastPage > 1): ?>
    <nav aria-label="Customers pagination" class="admin-main" style="margin-left: 0; padding-top: 0;">
        <ul class="pagination">
            <?php $makeLink = function($p) use ($q){ $qs=[]; if($q!=='')$qs['q']=$q; $qs['page']=$p; return url('admin/customers.php?' . http_build_query($qs)); }; ?>
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

<?php require_once '../includes/footer.php'; ?>

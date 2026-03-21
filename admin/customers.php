<?php
$pageTitle = 'Manage Customers - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

// Check admin access
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

 // Connection
$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    verify_csrf_or_abort();
    $customerId = (int)($_POST['customer_id'] ?? 0);
    if ($customerId > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
        $stmt->execute([$customerId]);
        setFlash('success', 'Customer deleted successfully.');
    }
    redirect(url('admin/customers.php'));
}

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

require_once '../includes/header.php';
?>
<aside class="admin-sidebar">
  <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link" href="<?php echo url('admin/stock-receipts.php'); ?>"><i class="fas fa-boxes-stacked me-2"></i>Stock In</a>
        <a class="nav-link" href="<?php echo url('admin/inventory-reports.php'); ?>"><i class="fas fa-chart-column me-2"></i>Reports</a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link active" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
        <a class="nav-link" href="<?php echo url('admin/assistance.php'); ?>"><i class="fas fa-headset me-2"></i>Assistance</a>
         <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
 </aside>

<main class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Customer Management</h1>
        <a href="<?php echo url('admin/customer-form.php'); ?>" class="btn btn-primary"><i class="fas fa-user-plus me-2"></i>Add Customer</a>
    </div>
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
                    <th>Phone</th>
                    <th>Registered</th>
                    <th>Total Orders</th>
                    <th>Total Spent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$customers): ?>
                    <tr><td colspan="8" class="text-center text-muted">No customers found</td></tr>
                <?php endif; ?>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo $customer['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td><?php echo htmlspecialchars($customer['phone'] ?? ''); ?></td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td><?php echo $customer['total_orders'] ?? 0; ?></td>
                        <td><strong><?php echo formatPrice($customer['total_spent'] ?? 0); ?></strong></td>
                        <td>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-outline" href="<?php echo url('admin/customer.php?id=' . $customer['id']); ?>">View</a>
                                <a class="btn btn-sm btn-outline" href="<?php echo url('admin/customer-form.php?id=' . $customer['id']); ?>">Edit</a>
                                <form method="POST" action="<?php echo url('admin/customers.php'); ?>" onsubmit="return confirm('Delete this customer account?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="customer_id" value="<?php echo (int)$customer['id']; ?>">
                                    <button type="submit" name="delete_customer" value="1" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </td>
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

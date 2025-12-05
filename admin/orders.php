<?php

$pageTitle = 'Manage Orders - Admin';
require_once '../includes/header.php';

// Check admin access
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

// Connection
$conn = getDBConnection();

if (isset($_POST['update_status'])) {
    verify_csrf_or_abort();
    // Rate limit (20 per session)
    if (!rate_limit_allow('admin_update_status', 20, 60)) {
        setFlash('error', 'Too many updates. Please wait a moment and try again.');
        redirect(url('admin/orders.php'));
    }

    $orderId = (int)$_POST['order_id'];
    $status = clean($_POST['status']);
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $orderId]);
    setFlash('success', 'Order status updated!');
    redirect(url('admin/orders.php'));
}

// Pagination
// Filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$allowedStatus = ['pending','processing','completed','cancelled'];
if ($status && !in_array($status, $allowedStatus, true)) { $status = ''; }

// Build WHERE
$conds = [];
$params = [];
if ($status !== '') { $conds[] = 'o.status = ?'; $params[] = $status; }
if ($q !== '') {
    // match order id or customer email
    if (ctype_digit($q)) { $conds[] = 'o.id = ?'; $params[] = (int)$q; }
    $conds[] = 'u.email LIKE ?'; $params[] = "%$q%";
}
$where = $conds ? (' WHERE ' . implode(' OR ', $conds)) : '';

// Pagination
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Total orders (distinct orders)
$countSql = "SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id" . $where;
$countStmt = $conn->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$lastPage = max(1, (int)ceil($total / $perPage));
if ($page > $lastPage) { $page = $lastPage; $offset = ($page - 1) * $perPage; }

// Get current page orders with customer info and item count
$listSql = "SELECT o.*, u.name AS customer_name, u.email AS customer_email, COUNT(oi.id) AS item_count
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id" .
            $where .
            " GROUP BY o.id ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($listSql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

?>
<aside class="admin-sidebar">
   <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link active" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
         <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
</aside>

<main class="admin-main">
    <h1 class="mb-4">Order Management</h1>
    <form class="d-flex gap-2 mb-3" method="GET" action="<?php echo url('admin/orders.php'); ?>" role="search" aria-label="Search orders">
        <label for="q" class="visually-hidden">Order ID or customer email</label>
        <input id="q" name="q" class="form-control" placeholder="Order ID or customer email" value="<?php echo htmlspecialchars($q); ?>">
        <label for="status" class="visually-hidden">Status</label>
        <select id="status" name="status" class="form-select">
            <option value="">All Statuses</option>
            <?php foreach ($allowedStatus as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline" type="submit">Filter</button>
    </form>
    <div class="dashboard-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="7" class="text-center text-muted">No orders found</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $order):
                    $statusClass = ['pending' => 'warning', 'processing' => 'info', 'completed' => 'success', 'cancelled' => 'danger'][$order['status']];
                ?>
                    <tr>
                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small></td>
                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                        <td><?php echo $order['item_count']; ?></td>
                        <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                        <td>
                            <form method="POST" class="d-inline">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <label for="status-<?php echo $order['id']; ?>" class="visually-hidden">Order Status</label>
                                <select id="status-<?php echo $order['id']; ?>" name="status" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Update order status">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline" href="<?php echo url('admin/order.php?id=' . $order['id']); ?>">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php if ($lastPage > 1): ?>
    <nav aria-label="Orders pagination" class="admin-main" style="margin-left: 0; padding-top: 0;">
        <ul class="pagination">
            <?php $makeLink = function($p) use ($status,$q){ $qs=[]; if($status!=='')$qs['status']=$status; if($q!=='')$qs['q']=$q; $qs['page']=$p; return url('admin/orders.php?' . http_build_query($qs)); }; ?>
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

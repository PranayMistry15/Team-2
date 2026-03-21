<?php
$pageTitle = 'Customer Details - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

$conn = getDBConnection();
$customerId = (int)($_GET['id'] ?? 0);
if ($customerId <= 0) {
    setFlash('error', 'Invalid customer ID');
    redirect(url('admin/customers.php'));
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_admin = 0");
$stmt->execute([$customerId]);
$customer = $stmt->fetch();
if (!$customer) {
    setFlash('error', 'Customer not found');
    redirect(url('admin/customers.php'));
}

$stmt = $conn->prepare("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent FROM orders WHERE user_id = ?");
$stmt->execute([$customerId]);
$stats = $stmt->fetch();

$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$customerId]);
$orders = $stmt->fetchAll();

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
        <h1>Customer #<?php echo (int)$customer['id']; ?></h1>
        <div class="d-flex gap-2">
            <a href="<?php echo url('admin/customer-form.php?id=' . (int)$customer['id']); ?>" class="btn btn-primary">Edit Customer</a>
            <a href="<?php echo url('admin/customers.php'); ?>" class="btn btn-outline">Back to Customers</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dashboard-card">
                <h4 class="mb-3">Profile</h4>
                <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($customer['name']); ?></p>
                <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
                <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone'] ?: 'Not set'); ?></p>
                <p class="mb-2"><strong>City:</strong> <?php echo htmlspecialchars($customer['city'] ?: 'Not set'); ?></p>
                <p class="mb-2"><strong>Postal Code:</strong> <?php echo htmlspecialchars($customer['postal_code'] ?: 'Not set'); ?></p>
                <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($customer['address'] ?: 'Not set'); ?></p>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo (int)($stats['total_orders'] ?? 0); ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo formatPrice($stats['total_spent'] ?? 0); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <h4 class="mb-3">Recent Orders</h4>
                <?php if (!$orders): ?>
                    <div class="text-muted">This customer has no orders yet.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Total</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo (int)$order['id']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($order['status'])); ?></td>
                                        <td><?php echo formatPrice($order['total_amount']); ?></td>
                                        <td><a class="btn btn-sm btn-outline" href="<?php echo url('admin/order.php?id=' . (int)$order['id']); ?>">View Order</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>

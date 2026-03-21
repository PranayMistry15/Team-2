<?php
$pageTitle = 'Order Details - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

$conn = getDBConnection();
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    setFlash('error', 'Invalid order ID');
    redirect(url('admin/orders.php'));
}

// Fetch order + user
$stmt = $conn->prepare("SELECT o.*, u.name as customer_name, u.email as customer_email
                        FROM orders o JOIN users u ON o.user_id = u.id
                        WHERE o.id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    setFlash('error', 'Order not found');
    redirect(url('admin/orders.php'));
}

// Fetch order items with product names
$stmt = $conn->prepare("SELECT oi.*, p.name as product_name
                        FROM order_items oi JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

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
        <a class="nav-link active" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
        <a class="nav-link" href="<?php echo url('admin/assistance.php'); ?>"><i class="fas fa-headset me-2"></i>Assistance</a>
         <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
    </aside>

<main class="admin-main">
    <h1 class="mb-3">Order #<?php echo $order['id']; ?></h1>
    <a href="<?php echo url('admin/orders.php'); ?>" class="btn btn-outline mb-3">&larr; Back to Orders</a>
    <div class="dashboard-card p-4">
        <h3 class="mb-3">Order #<?php echo $order['id']; ?></h3>
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Customer</h5>
                <p class="mb-1"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($order['customer_email']); ?></p>
            </div>
            <div class="col-md-6">
                <h5>Summary</h5>
                <p class="mb-1">Status: <strong class="text-capitalize"><?php echo htmlspecialchars($order['status']); ?></strong></p>
                <p class="mb-1">Placed: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                <p class="mb-0">Total: <strong><?php echo formatPrice($order['total_amount']); ?></strong></p>
            </div>
        </div>

        <h5 class="mb-2">Shipping Address</h5>
        <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>

        <h5 class="mt-4 mb-2">Items</h5>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['product_name']); ?></td>
                            <td><?php echo (int)$it['quantity']; ?></td>
                            <td><?php echo formatPrice($it['price']); ?></td>
                            <td><?php echo formatPrice($it['price'] * $it['quantity']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>

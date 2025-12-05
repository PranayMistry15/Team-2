<?php

$pageTitle = 'Admin Dashboard - Laptro';
require_once '../includes/header.php';

// Check admin access
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

// Connection
$conn = getDBConnection();

// Get total sales
$stmt = $conn->query("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders FROM orders");
$salesStats = $stmt->fetch();

// Get recent orders
$stmt = $conn->query("SELECT orders.*, users.name as customer_name, users.email as customer_email FROM orders JOIN users ON orders.user_id = users.id  ORDER BY orders.created_at DESC LIMIT 10");
$recentOrders = $stmt->fetchAll();

// Get new user registrations (last 30 days)
$stmt = $conn->query("SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND is_admin = 0");
$newUsersStats = $stmt->fetch();

// Get total users
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 0");
$totalUsers = $stmt->fetch()['total'];

// Get low stock products
$stmt = $conn->query("SELECT * FROM products WHERE stock < 5 AND stock > 0 ORDER BY stock ASC LIMIT 5");
$lowStockProducts = $stmt->fetchAll();

// Get popular products (most ordered)
$stmt = $conn->query("SELECT products.*, COUNT(order_items.id) as order_count FROM products JOIN order_items ON products.id = order_items.product_id GROUP BY products.id ORDER BY order_count DESC LIMIT 5");
$popularProducts = $stmt->fetchAll();?>



<aside class="admin-sidebar">
    <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link active" href="<?php echo url('admin/index.php'); ?>">
            <i class="fas fa-home me-2"></i>Dashboard
        </a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>">
            <i class="fas fa-laptop me-2"></i>Products
        </a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>">
            <i class="fas fa-shopping-bag me-2"></i>Orders
        </a>
        <a class="nav-link" href="<?php echo url('admin/customers.php'); ?>">
            <i class="fas fa-users me-2"></i>Customers
        </a>
        <a class="nav-link" href="<?php echo url('admin/returns.php'); ?>">
            <i class="fas fa-undo me-2"></i>Returns
        </a>
        <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>">
            <i class="fas fa-globe me-2"></i>View Site
        </a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>">
            <i class="fas fa-sign-out-alt me-2"></i>Logout
        </a>
    </nav>
 </aside>

<main class="admin-main">
    <div class="mb-4">
        <h1>Dashboard</h1>
        <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
    </div>
    
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label">Total Sales</div>
                        <div class="stat-number"><?php echo formatPrice($salesStats['total_sales'] ?? 0); ?></div>
                    </div>
                    <i class="fas fa-dollar-sign fa-2x"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card" style="background-color: #28a745;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.8);">Total Orders</div>
                        <div class="stat-number"><?php echo $salesStats['total_orders'] ?? 0; ?></div>
                    </div>
                    <i class="fas fa-shopping-cart fa-2x" style="opacity: 0.5;"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card" style="background-color: #17a2b8;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label" style="color: rgba(255,255,255,0.8);">New Users (30 days)</div>
                        <div class="stat-number"><?php echo $newUsersStats['new_users'] ?? 0; ?></div>
                    </div>
                    <i class="fas fa-user-plus fa-2x" style="opacity: 0.5;"></i>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="stat-card" style="background-color: #ffc107; color: #000;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-label" style="color: rgba(0,0,0,0.7);">Pending Orders</div>
                        <div class="stat-number" style="color: #000;"><?php echo $salesStats['pending_orders'] ?? 0; ?></div>
                    </div>
                    <i class="fas fa-clock fa-2x" style="opacity: 0.5;"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="dashboard-card">
                <h4 class="mb-4">Recent Orders</h4>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): 
                                $statusClass = [
                                    'pending' => 'warning',
                                    'processing' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger'
                                ][$order['status']];
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo url('admin/orders.php'); ?>" class="btn btn-outline">View All Orders</a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <?php if (!empty($lowStockProducts)): ?>
                <div class="dashboard-card mb-4">
                    <h5 class="mb-3">⚠️ Low Stock Alert</h5>
                    <?php foreach ($lowStockProducts as $product): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                            <div>
                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                            </div>
                            <span class="badge badge-warning"><?php echo $product['stock']; ?> left</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-card">
                <h5 class="mb-3">🔥 Popular Products</h5>
                <?php foreach ($popularProducts as $product): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                            <small class="text-muted"><?php echo $product['order_count']; ?> orders</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>

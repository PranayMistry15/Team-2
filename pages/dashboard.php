<?php


$pageTitle = 'My Dashboard - Laptro';
require_once __DIR__ . '/../includes/header.php';

// Require login
if (!isLoggedIn()) {
    redirect(url('login.php'));
}

$conn = getDBConnection();

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([getUserId()]);
$user = $stmt->fetch();

// Get order history with items
$stmt = $conn->prepare("SELECT orders.*, COUNT(order_items.id) as item_count FROM orders LEFT JOIN order_items ON orders.id = order_items.order_id WHERE orders.user_id = ? GROUP BY orders.id ORDER BY orders.created_at DESC");
$stmt->execute([getUserId()]);
$orders = $stmt->fetchAll();

// Get order statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_orders, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders, SUM(total_amount) as total_spent FROM orders  WHERE user_id = ?");
$stmt->execute([getUserId()]);
$stats = $stmt->fetch();
?>

<div class="container section-padding">
    <div class="row">
        <div class="col-lg-3 mb-4">
            <div class="dashboard-card">
                <div class="text-center mb-4">
                    <div class="bg-dark text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                         style="width: 80px; height: 80px; font-size: 2rem;">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                    <p class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                
                <nav class="nav flex-column">
                    <a class="nav-link active" href="<?php echo url('dashboard.php'); ?>">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#orders'); ?>">
                        <i class="fas fa-shopping-bag me-2"></i>My Orders
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#returns'); ?>">
                        <i class="fas fa-undo me-2"></i>My Returns
                    </a>
                    <a class="nav-link" href="<?php echo url('cart.php'); ?>">
                        <i class="fas fa-shopping-cart me-2"></i>Shopping Cart
                    </a>
                    <a class="nav-link" href="<?php echo url('logout.php'); ?>">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
            </div>
        </div>
        

        <div class="col-lg-9">
            <h2 class="mb-4">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_orders'] ?? 0; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['completed_orders'] ?? 0; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo formatPrice($stats['total_spent'] ?? 0); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card" id="orders">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Order History</h4>
                    <a href="<?php echo url('products.php'); ?>" class="btn btn-outline btn-sm">Continue Shopping</a>
                </div>
                
                <?php if (empty($orders)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-bag fa-3x text-muted mb-3"></i>
                        <h5>No orders yet</h5>
                        <p class="text-muted">Start shopping to see your orders here</p>
                        <a href="<?php echo url('products.php'); ?>" class="btn btn-primary mt-3">Browse Laptops</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Build a quick map of existing return requests for this user keyed by order_id
                                $hasReturn = [];
                                try {
                                    $rs = $conn->prepare("SELECT DISTINCT order_id FROM `returns` WHERE user_id = ?");
                                    $rs->execute([getUserId()]);
                                    foreach ($rs->fetchAll() as $rowR) { $hasReturn[(int)$rowR['order_id']] = true; }
                                } catch (Throwable $e) { /* returns feature optional */ }
                                foreach ($orders as $order): 
                                    $statusClass = [
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ][$order['status']];
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td><?php echo $order['item_count']; ?> item(s)</td>
                                        <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                        <td>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline" 
                                                        data-toggle-order-details
                                                        data-order-id="<?php echo $order['id']; ?>">
                                                    View Details
                                                </button>
                                                <?php 
                                                    $eligible = ($order['status'] === 'completed') && empty($hasReturn[(int)$order['id']]);
                                                    if ($eligible): ?>
                                                    <a class="btn btn-sm btn-primary" href="<?php echo url('return-request.php?order_id=' . $order['id']); ?>">Request Return</a>
                                                <?php elseif ($order['status'] === 'completed' && !empty($hasReturn[(int)$order['id']])): ?>
                                                    <span class="badge badge-info align-self-center">Return Requested</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <tr id="order-<?php echo $order['id']; ?>" style="display: none;">
                                        <td colspan="6">
                                            <div class="p-3" style="background-color: var(--off-white);">
                                                <?php
                                                $itemStmt = $conn->prepare("
                                                    SELECT order_items.*, products.name, products.brand, products.main_image
                                                    FROM order_items
                                                    JOIN products ON order_items.product_id = products.id
                                                    WHERE order_items.order_id = ?
                                                ");
                                                $itemStmt->execute([$order['id']]);
                                                $items = $itemStmt->fetchAll();
                                                ?>
                                                
                                                <h6 class="mb-3">Order Items:</h6>
                                                <?php foreach ($items as $item): ?>
                                                    <div class="d-flex mb-2">
                                                        <img src="<?php echo url('assets/products/' . $item['main_image']); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                                             data-fallback="https://via.placeholder.com/50">
                                                        <div class="ms-3">
                                                            <div><strong><?php echo htmlspecialchars($item['name']); ?></strong></div>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($item['brand']); ?> • 
                                                                Qty: <?php echo $item['quantity']; ?> • 
                                                                <?php echo formatPrice($item['price']); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <hr>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>Shipping Address:</h6>
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Payment Method:</h6>
                                                        <p class="mb-0"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-card mt-4" id="returns">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">My Returns</h4>
                </div>
                <?php
                    $myReturns = [];
                    try {
                        $rStmt = $conn->prepare("SELECT r.*, o.total_amount, o.created_at as order_date FROM `returns` r JOIN orders o ON r.order_id = o.id WHERE r.user_id = ? ORDER BY r.created_at DESC");
                        $rStmt->execute([getUserId()]);
                        $myReturns = $rStmt->fetchAll();
                    } catch (Throwable $e) {
                        echo '<div class="text-muted">Returns feature not installed.</div>';
                    }
                ?>
                <?php if (empty($myReturns)): ?>
                    <div class="text-center py-4 text-muted">No return requests yet.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Order</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($myReturns as $r): ?>
                                <tr>
                                    <td><?php echo (int)$r['id']; ?></td>
                                    <td>#<?php echo (int)$r['order_id']; ?><br><small class="text-muted"><?php echo date('M d, Y', strtotime($r['order_date'])); ?> • <?php echo formatPrice($r['total_amount']); ?></small></td>
                                    <td><?php echo htmlspecialchars($r['reason']); ?></td>
                                    <td><span class="badge badge-<?php echo $r['status']==='rejected'?'danger':($r['status']==='approved'?'success':($r['status']==='processing'?'info':'warning')); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                                    <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>

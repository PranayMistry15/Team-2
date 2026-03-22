<?php

$pageTitle = 'Inventory Reports - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

$conn = getDBConnection();
stock_receipts_ensure_table();

$stockSummary = $conn->query("
    SELECT
        COUNT(*) AS total_products,
        SUM(stock) AS total_units,
        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
        SUM(CASE WHEN stock BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low_stock
    FROM products
")->fetch();

$inventoryRows = $conn->query("
    SELECT
        p.id,
        p.name,
        p.category,
        p.stock,
        COALESCE(outgoing.units_sold, 0) AS units_sold,
        outgoing.last_order_at,
        COALESCE(incoming.units_received, 0) AS units_received,
        incoming.last_receipt_at
    FROM products p
    LEFT JOIN (
        SELECT oi.product_id, SUM(oi.quantity) AS units_sold, MAX(o.created_at) AS last_order_at
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        GROUP BY oi.product_id
    ) outgoing ON outgoing.product_id = p.id
    LEFT JOIN (
        SELECT sr.product_id, SUM(sr.quantity) AS units_received, MAX(sr.created_at) AS last_receipt_at
        FROM stock_receipts sr
        GROUP BY sr.product_id
    ) incoming ON incoming.product_id = p.id
    ORDER BY p.stock ASC, p.name ASC
")->fetchAll();

$recentOutgoing = $conn->query("
    SELECT
        o.id AS order_id,
        o.created_at,
        p.name AS product_name,
        oi.quantity,
        u.name AS customer_name
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id
    JOIN users u ON u.id = o.user_id
    ORDER BY o.created_at DESC, oi.id DESC
    LIMIT 20
")->fetchAll();

$recentIncoming = $conn->query("
    SELECT
        sr.created_at,
        p.name AS product_name,
        p.category,
        sr.quantity,
        u.name AS admin_name,
        sr.note
    FROM stock_receipts sr
    JOIN products p ON p.id = sr.product_id
    JOIN users u ON u.id = sr.admin_user_id
    ORDER BY sr.created_at DESC, sr.id DESC
    LIMIT 20
")->fetchAll();

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
        <a class="nav-link active" href="<?php echo url('admin/inventory-reports.php'); ?>"><i class="fas fa-chart-column me-2"></i>Reports</a>
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
        <div>
            <h1 class="mb-1">Inventory Reports</h1>
            <p class="text-muted mb-0">Real-time stock levels with incoming and outgoing product activity.</p>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-number"><?php echo (int)($stockSummary['total_products'] ?? 0); ?></div>
                <div class="stat-label">Tracked Products</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card" style="background-color: #17a2b8;">
                <div class="stat-number"><?php echo (int)($stockSummary['total_units'] ?? 0); ?></div>
                <div class="stat-label">Units In Stock</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card" style="background-color: #ffc107; color: #000;">
                <div class="stat-number" style="color: #000;"><?php echo (int)($stockSummary['low_stock'] ?? 0); ?></div>
                <div class="stat-label" style="color: rgba(0,0,0,0.7);">Low Stock Lines</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card" style="background-color: #dc3545;">
                <div class="stat-number"><?php echo (int)($stockSummary['out_of_stock'] ?? 0); ?></div>
                <div class="stat-label">Out of Stock</div>
            </div>
        </div>
    </div>

    <div class="dashboard-card mb-4">
        <h4 class="mb-3">Current Stock Report</h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Incoming Units</th>
                        <th>Last Incoming</th>
                        <th>Outgoing Units</th>
                        <th>Last Outgoing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventoryRows as $row): ?>
                        <?php $stockMeta = stock_status_meta($row['stock']); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><span class="badge badge-<?php echo htmlspecialchars($stockMeta['class']); ?>"><?php echo htmlspecialchars($stockMeta['label']); ?></span></td>
                            <td><?php echo (int)$row['units_received']; ?></td>
                            <td><?php echo $row['last_receipt_at'] ? date('M d, Y H:i', strtotime($row['last_receipt_at'])) : 'Never'; ?></td>
                            <td><?php echo (int)$row['units_sold']; ?></td>
                            <td><?php echo $row['last_order_at'] ? date('M d, Y H:i', strtotime($row['last_order_at'])) : 'Never'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h4 class="mb-3">Recent Incoming Activity</h4>
                <?php if (!$recentIncoming): ?>
                    <p class="text-muted mb-0">No incoming stock has been recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentIncoming as $row): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($row['category']); ?></small></td>
                                        <td><strong>+<?php echo (int)$row['quantity']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['admin_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="dashboard-card">
                <h4 class="mb-3">Recent Outgoing Activity</h4>
                <?php if (!$recentOutgoing): ?>
                    <p class="text-muted mb-0">No outgoing order activity yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOutgoing as $row): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                        <td>#<?php echo (int)$row['order_id']; ?><br><small class="text-muted"><?php echo htmlspecialchars($row['customer_name']); ?></small></td>
                                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                                        <td><strong>-<?php echo (int)$row['quantity']; ?></strong></td>
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

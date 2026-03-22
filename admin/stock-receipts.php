<?php

$pageTitle = 'Stock Replenishment - Admin';
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/url-helper.php';

if (!isAdmin()) {
    setFlash('error', 'Unauthorized access');
    redirect(url('index.php'));
}

$conn = getDBConnection();
stock_receipts_ensure_table();

$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_receipt'])) {
    verify_csrf_or_abort();

    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $note = clean($_POST['note'] ?? '');

    v_int_range($productId, 'Product', 1, PHP_INT_MAX, $formErrors);
    v_int_range($quantity, 'Quantity', 1, 5000, $formErrors);
    if ($note !== '') {
        v_string_length($note, 'Note', 2, 255, $formErrors);
    }

    $productStmt = $conn->prepare("SELECT id, name, stock FROM products WHERE id = ? LIMIT 1");
    $productStmt->execute([$productId]);
    $selectedProduct = $productStmt->fetch();
    if (!$selectedProduct) {
        $formErrors[] = 'Select a valid product';
    }

    if (empty($formErrors)) {
        try {
            $conn->beginTransaction();

            $update = $conn->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $update->execute([$quantity, $productId]);

            $receipt = $conn->prepare("INSERT INTO stock_receipts (product_id, admin_user_id, quantity, note) VALUES (?, ?, ?, ?)");
            $receipt->execute([$productId, getUserId(), $quantity, $note !== '' ? $note : null]);

            $conn->commit();
            setFlash('success', 'Incoming stock recorded for ' . $selectedProduct['name'] . '.');
            redirect(url('admin/stock-receipts.php'));
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $formErrors[] = 'Could not record the stock receipt right now.';
        }
    }
}

$products = $conn->query("SELECT id, name, brand, stock, category FROM products ORDER BY name ASC")->fetchAll();
$recentReceipts = $conn->query("
    SELECT sr.*, p.name AS product_name, p.category, u.name AS admin_name
    FROM stock_receipts sr
    JOIN products p ON p.id = sr.product_id
    JOIN users u ON u.id = sr.admin_user_id
    ORDER BY sr.created_at DESC, sr.id DESC
    LIMIT 20
")->fetchAll();
$lowStockProducts = $conn->query("SELECT id, name, stock, category FROM products WHERE stock <= 5 ORDER BY stock ASC, name ASC LIMIT 10")->fetchAll();

require_once '../includes/header.php';
?>
<aside class="admin-sidebar">
    <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link active" href="<?php echo url('admin/stock-receipts.php'); ?>"><i class="fas fa-boxes-stacked me-2"></i>Stock In</a>
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
        <div>
            <h1 class="mb-1">Incoming Stock</h1>
            <p class="text-muted mb-0">Record replenishment deliveries and update inventory automatically.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="dashboard-card">
                <h4 class="mb-3">Record Receipt</h4>
                <?php if (!empty($formErrors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($formErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo url('admin/stock-receipts.php'); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label" for="product_id">Product</label>
                        <select id="product_id" name="product_id" class="form-control" required>
                            <option value="">Select product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo (int)$product['id']; ?>" <?php echo ((int)($_POST['product_id'] ?? 0) === (int)$product['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($product['name'] . ' | stock: ' . $product['stock'] . ' | ' . $product['category']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="quantity">Quantity Received</label>
                        <input type="number" id="quantity" name="quantity" min="1" max="5000" class="form-control" value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="note">Delivery Note</label>
                        <textarea id="note" name="note" rows="3" class="form-control" placeholder="Supplier, invoice ref, or shipment note"><?php echo htmlspecialchars($_POST['note'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="record_receipt" value="1" class="btn btn-primary">Record Incoming Stock</button>
                </form>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="dashboard-card mb-4">
                <h4 class="mb-3">Low Stock Report</h4>
                <?php if (!$lowStockProducts): ?>
                    <p class="text-muted mb-0">No products are currently under the low-stock threshold.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowStockProducts as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td><span class="badge badge-warning"><?php echo (int)$product['stock']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dashboard-card">
                <h4 class="mb-3">Recent Incoming Orders</h4>
                <?php if (!$recentReceipts): ?>
                    <p class="text-muted mb-0">No stock receipts recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Admin</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReceipts as $receipt): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($receipt['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($receipt['product_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($receipt['category']); ?></small></td>
                                        <td><strong>+<?php echo (int)$receipt['quantity']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($receipt['admin_name']); ?></td>
                                        <td><?php echo htmlspecialchars($receipt['note'] ?? ''); ?></td>
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

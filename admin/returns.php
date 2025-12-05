<?php

$pageTitle = 'Manage Returns - Admin';
require_once '../includes/header.php';

if (!isAdmin()) { setFlash('error', 'Unauthorized'); redirect(url('index.php')); }

$conn = getDBConnection();

// Ensure `returns` table exists (graceful bootstrap)
try {
    $conn->query("SELECT 1 FROM `returns` LIMIT 1");
} catch (Throwable $e) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS `returns` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            user_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            details TEXT NULL,
            status ENUM('requested','approved','rejected','processing','completed') NOT NULL DEFAULT 'requested',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_returns_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            CONSTRAINT fk_returns_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
    } catch (Throwable $e2) {
        setFlash('error', 'Returns table is missing and could not be created. Please import database/patches/returns.sql');
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    verify_csrf_or_abort();
    $id = (int)($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'requested';
    $allowed = ['requested','approved','rejected','processing','completed'];
    if ($id > 0 && in_array($status, $allowed, true)) {
        $stmt = $conn->prepare("UPDATE `returns` SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        // Notify user
        $uStmt = $conn->prepare("SELECT user_id, order_id FROM `returns` WHERE id = ?");
        $uStmt->execute([$id]);
        if ($row = $uStmt->fetch()) {
            notify_user_by_id((int)$row['user_id'], 'Return status updated', 'Your return for order #' . (int)$row['order_id'] . ' is now ' . $status . '.');
        }
        setFlash('success', 'Return updated');
    }
    redirect(url('admin/returns.php'));
}

// List returns with order + user
$q = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($q !== '') { $where = 'WHERE r.reason LIKE ? OR u.email LIKE ? OR u.name LIKE ?'; $like = "%$q%"; $params = [$like,$like,$like]; }

$stmt = $conn->prepare("SELECT r.*, o.total_amount, o.created_at as order_date, u.name as user_name, u.email as user_email
                         FROM `returns` r
                         JOIN orders o ON r.order_id = o.id
                         JOIN users u ON r.user_id = u.id
                         $where
                         ORDER BY r.created_at DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<aside class="admin-sidebar">
  <div class="text-center ">
       <img src="../assets/images/whitelogo.png" class="admin_logo">
    </div>
    <nav class="nav flex-column" aria-label="Admin sidebar">
        <a class="nav-link" href="<?php echo url('admin/index.php'); ?>"><i class="fas fa-home me-2"></i>Dashboard</a>
        <a class="nav-link" href="<?php echo url('admin/products.php'); ?>"><i class="fas fa-laptop me-2"></i>Products</a>
        <a class="nav-link" href="<?php echo url('admin/orders.php'); ?>"><i class="fas fa-shopping-bag me-2"></i>Orders</a>
        <a class="nav-link" href="<?php echo url('admin/customers.php'); ?>"><i class="fas fa-users me-2"></i>Customers</a>
        <a class="nav-link active" href="<?php echo url('admin/returns.php'); ?>"><i class="fas fa-undo me-2"></i>Returns</a>
         <hr style="border-color: #666;">
        <a class="nav-link" href="<?php echo url('index.php'); ?>"><i class="fas fa-globe me-2"></i>View Site</a>
        <a class="nav-link" href="<?php echo url('logout.php'); ?>"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </nav>
</aside>

<main class="admin-main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Return Requests</h1>
        <form class="d-flex gap-2" method="GET" action="<?php echo url('admin/returns.php'); ?>">
            <input class="form-control" name="q" placeholder="Search reason/name/email" value="<?php echo htmlspecialchars($q); ?>">
            <button class="btn btn-outline" type="submit">Search</button>
        </form>
    </div>
    <div class="dashboard-card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>User</th>
                        <th>Reason</th>
                        <th>Details</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="text-center text-muted">No return requests</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td>
                                <strong>#<?php echo $r['order_id']; ?></strong><br>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($r['order_date'])); ?> • <?php echo formatPrice($r['total_amount']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($r['user_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($r['user_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($r['reason']); ?></td>
                            <td class="clamp-3" style="max-width: 240px;"><?php echo nl2br(htmlspecialchars($r['details'] ?? '')); ?></td>
                            <td><span class="badge badge-<?php echo $r['status']==='rejected'?'danger':($r['status']==='approved'?'success':($r['status']==='processing'?'info':'warning')); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                            <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="d-flex gap-2 align-items-center">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                    <select name="status" class="form-select form-select-sm">
                                        <?php foreach (['requested','approved','processing','completed','rejected'] as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo $r['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-outline" name="update_status" value="1" type="submit">Update</button>
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

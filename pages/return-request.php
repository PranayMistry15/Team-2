<?php

$pageTitle = 'Request a Return - Laptro';
require_once __DIR__ . '/../includes/header.php';

if (!isLoggedIn()) { redirect(url('login.php')); }

$conn = getDBConnection();
$userId = getUserId();

// Ensure `returns` table exists gracefully
try { $conn->query("SELECT 1 FROM `returns` LIMIT 1"); } catch (Throwable $e) {
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
        setFlash('error', 'Returns feature not installed. Please try again later.');
        redirect(url('dashboard.php#orders'));
    }
}

// Fetch order
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    setFlash('error', 'Invalid order.');
    redirect(url('dashboard.php#orders'));
}

$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();
if (!$order) {
    setFlash('error', 'Order not found.');
    redirect(url('dashboard.php#orders'));
}

// Only allow returns for completed orders (basic policy)
if ($order['status'] !== 'completed') {
    setFlash('error', 'Only completed orders can be returned.');
    redirect(url('dashboard.php#orders'));
}

// Return eligibility: allow once order is completed (no time window enforced)

// Handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $reason = trim($_POST['reason'] ?? '');
    $details = trim($_POST['details'] ?? '');

    $errors = [];
    if ($reason === '') { $errors[] = 'Please select a reason'; }
    if (mb_strlen($details) > 1000) { $errors[] = 'Details are too long'; }

    if (!$errors) {
$stmt = $conn->prepare("INSERT INTO `returns` (order_id, user_id, reason, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$orderId, $userId, $reason, $details]);
        notify_user_by_id($userId, 'Return request received', 'We have received your return request for order #' . $orderId . '.');
        setFlash('success', 'Return request submitted. We\'ll email you with next steps.');
        redirect(url('dashboard.php#orders'));
    } else {
        setFlash('error', implode('\n', $errors));
    }
}
?>

<div class="container section-padding">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="dashboard-card">
                <h3 class="mb-3">Request a Return</h3>
                <p class="text-muted">Order #<?php echo $order['id']; ?> &middot; Placed <?php echo date('M d, Y', strtotime($order['created_at'])); ?> &middot; Total <?php echo formatPrice($order['total_amount']); ?></p>
                <form method="POST" action="<?php echo url('return-request.php?order_id=' . $order['id']); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason</label>
                        <select id="reason" name="reason" class="form-select" required>
                            <option value="">Select a reason</option>
                            <option value="Damaged / faulty">Damaged / faulty</option>
                            <option value="Wrong item received">Wrong item received</option>
                            <option value="Not as described">Not as described</option>
                            <option value="Changed mind">Changed mind</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="details" class="form-label">Details (optional)</label>
                        <textarea id="details" name="details" class="form-control" rows="4" placeholder="Add any details that can help us process your request"></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Submit Return Request</button>
                        <a href="<?php echo url('dashboard.php#orders'); ?>" class="btn btn-outline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

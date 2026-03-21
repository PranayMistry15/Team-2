<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url-helper.php';

// Require login before any output
if (!isLoggedIn()) {
    redirect(url('login.php'));
}

$pageTitle = 'My Dashboard - Laptro';
$conn = getDBConnection();
$userId = getUserId();
user_security_ensure_columns();
service_reviews_ensure_table();
$mustChangePassword = requires_password_change();
$profileErrors = [];
$passwordErrors = [];
$deleteErrors = [];
$serviceReviewErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if ($mustChangePassword) {
        setFlash('error', 'Change your password before updating other account details.');
        redirect(url('dashboard.php#security'));
    }
    verify_csrf_or_abort();

    $name = clean($_POST['name'] ?? '');
    $email = clean($_POST['email'] ?? '');
    $phone = clean($_POST['phone'] ?? '');
    $address = clean($_POST['address'] ?? '');
    $city = clean($_POST['city'] ?? '');
    $postalCode = clean($_POST['postal_code'] ?? '');

    $userRules = constraints('user');
    $nameRule = $userRules['name'] ?? ['min' => 2, 'max' => 100];

    v_required($name, 'Full name', $profileErrors);
    v_string_length($name, 'Full name', $nameRule['min'], $nameRule['max'], $profileErrors);
    v_required($email, 'Email', $profileErrors);
    if ($email !== '') {
        v_email($email, 'email address', $profileErrors);
    }
    if ($phone !== '') {
        v_matches($phone, 'Phone', '/^[0-9\+\-\s\(\)]{7,20}$/', $profileErrors, 'Phone number format is invalid');
    }
    if ($address !== '') {
        v_string_length($address, 'Address', 5, 200, $profileErrors);
    }
    if ($city !== '') {
        v_string_length($city, 'City', 2, 100, $profileErrors);
    }
    if ($postalCode !== '') {
        v_matches($postalCode, 'Postal code', '/^[A-Za-z0-9 \-]{3,12}$/', $profileErrors, 'Postal code should be 3-12 letters/digits');
    }

    if (empty($profileErrors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) {
            $profileErrors[] = 'Email already registered';
        }
    }

    if (empty($profileErrors)) {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, city = ?, postal_code = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone ?: null, $address ?: null, $city ?: null, $postalCode ?: null, $userId]);
        $_SESSION['user_name'] = $name;
        setFlash('success', 'Your details have been updated.');
        redirect(url('dashboard.php#profile'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf_or_abort();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_new_password'] ?? '';

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $passwordRow = $stmt->fetch();

    if (!$passwordRow || !password_verify($currentPassword, $passwordRow['password'])) {
        $passwordErrors[] = 'Current password is incorrect';
    }

    $userRules = constraints('user');
    $pwdRule = $userRules['password'] ?? ['min' => 6, 'max' => 128];
    v_password_strict($newPassword, $passwordErrors, 'New password', $pwdRule['min'], $pwdRule['max']);
    if ($newPassword !== $confirmPassword) {
        $passwordErrors[] = 'New passwords do not match';
    }
    if ($currentPassword !== '' && $currentPassword === $newPassword) {
        $passwordErrors[] = 'New password must be different from your current password';
    }

    if (empty($passwordErrors)) {
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $stmt->execute([$newHash, $userId]);
        $mustChangePassword = false;
        setFlash('success', 'Your password has been changed.');
        redirect(url('dashboard.php#security'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    if ($mustChangePassword) {
        setFlash('error', 'Change your password before using other account actions.');
        redirect(url('dashboard.php#security'));
    }
    verify_csrf_or_abort();

    $deletePassword = $_POST['delete_password'] ?? '';
    $deleteConfirm = clean($_POST['delete_confirm'] ?? '');

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $passwordRow = $stmt->fetch();

    if (!$passwordRow || !password_verify($deletePassword, $passwordRow['password'])) {
        $deleteErrors[] = 'Password is incorrect';
    }
    if ($deleteConfirm !== 'DELETE') {
        $deleteErrors[] = 'Type DELETE to confirm account removal';
    }

    if (empty($deleteErrors)) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        session_unset();
        session_destroy();
        session_start();
        setFlash('success', 'Your account has been deleted.');
        redirect(url('index.php'));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_service_review'])) {
    if ($mustChangePassword) {
        setFlash('error', 'Change your password before using other account actions.');
        redirect(url('dashboard.php#security'));
    }
    verify_csrf_or_abort();

    $serviceRating = (int)($_POST['service_rating'] ?? 0);
    $serviceComment = clean($_POST['service_comment'] ?? '');
    $sr = constraints('service_review');
    v_int_range($serviceRating, 'Service rating', $sr['rating']['min'], $sr['rating']['max'], $serviceReviewErrors);
    v_string_length($serviceComment, 'Service review', $sr['comment']['min'], $sr['comment']['max'], $serviceReviewErrors);

    if (empty($serviceReviewErrors)) {
        $stmt = $conn->prepare("INSERT INTO service_reviews (user_id, rating, comment) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$userId, $serviceRating, $serviceComment]);
        setFlash('success', 'Your service review has been saved.');
        redirect(url('dashboard.php#service-review'));
    }
}

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Get order history with items
$stmt = $conn->prepare("SELECT orders.*, COUNT(order_items.id) as item_count FROM orders LEFT JOIN order_items ON orders.id = order_items.order_id WHERE orders.user_id = ? GROUP BY orders.id ORDER BY orders.created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// Get order statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total_orders, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders, SUM(total_amount) as total_spent FROM orders WHERE user_id = ?");
$stmt->execute([$userId]);
$stats = $stmt->fetch();

$stmt = $conn->prepare("SELECT * FROM service_reviews WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$myServiceReview = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
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
                    <a class="nav-link active" href="<?php echo url('dashboard.php#overview'); ?>" data-dashboard-link="overview">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#profile'); ?>" data-dashboard-link="profile">
                        <i class="fas fa-user-edit me-2"></i>My Details
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#security'); ?>" data-dashboard-link="security">
                        <i class="fas fa-lock me-2"></i>Password
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#service-review'); ?>" data-dashboard-link="service-review">
                        <i class="fas fa-star me-2"></i>Service Review
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#orders'); ?>" data-dashboard-link="orders">
                        <i class="fas fa-shopping-bag me-2"></i>My Orders
                    </a>
                    <a class="nav-link" href="<?php echo url('dashboard.php#returns'); ?>" data-dashboard-link="returns">
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

        <div class="col-lg-9" data-dashboard-content>
            <div class="dashboard-panel" id="overview" data-dashboard-panel>
            <h2 class="mb-4">Welcome back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h2>
            <?php if ($mustChangePassword): ?>
                <div class="alert alert-warning">
                    This is your first login. Change your password now before using the rest of the account features.
                </div>
            <?php endif; ?>
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
            </div>

            <div class="dashboard-panel" id="profile" data-dashboard-panel>
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">My Details</h4>
                </div>

                <?php if (!empty($profileErrors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($profileErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo url('dashboard.php#profile'); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="profile_name">Full Name</label>
                            <input type="text" id="profile_name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="profile_email">Email Address</label>
                            <input type="email" id="profile_email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="profile_phone">Phone</label>
                            <input type="text" id="profile_phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="profile_postal_code">Postal Code</label>
                            <input type="text" id="profile_postal_code" name="postal_code" class="form-control" value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="profile_address">Address</label>
                        <input type="text" id="profile_address" name="address" class="form-control" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="profile_city">City</label>
                        <input type="text" id="profile_city" name="city" class="form-control" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                    </div>
                    <button type="submit" name="update_profile" value="1" class="btn btn-primary">Save Details</button>
                </form>
            </div>
            </div>

            <div class="dashboard-panel" id="security" data-dashboard-panel>
            <div class="dashboard-card mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Change Password</h4>
                </div>

                <?php if (!empty($passwordErrors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($passwordErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo url('dashboard.php#security'); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="confirm_new_password">Confirm New Password</label>
                            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="change_password" value="1" class="btn btn-primary">Update Password</button>
                </form>
            </div>

            <div class="dashboard-card mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Delete Account</h4>
                </div>

                <?php if (!empty($deleteErrors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($deleteErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <p class="text-muted">This permanently removes your account and associated records.</p>
                <form method="POST" action="<?php echo url('dashboard.php'); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="delete_password">Password</label>
                            <input type="password" id="delete_password" name="delete_password" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="delete_confirm">Type DELETE to confirm</label>
                            <input type="text" id="delete_confirm" name="delete_confirm" class="form-control" required>
                        </div>
                    </div>
                    <button type="submit" name="delete_account" value="1" class="btn btn-outline">Delete Account</button>
                </form>
            </div>
            </div>

            <div class="dashboard-panel" id="service-review" data-dashboard-panel>
            <div class="dashboard-card mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">Rate Our Service</h4>
                </div>

                <?php if (!empty($serviceReviewErrors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($serviceReviewErrors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo url('dashboard.php#service-review'); ?>">
                    <?php echo csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label" for="service_rating">Overall Rating</label>
                        <select id="service_rating" name="service_rating" class="form-control" required>
                            <option value="">Select a rating</option>
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                <option value="<?php echo $r; ?>" <?php echo ((int)($myServiceReview['rating'] ?? 0) === $r) ? 'selected' : ''; ?>><?php echo $r; ?> star<?php echo $r === 1 ? '' : 's'; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="service_comment">Review</label>
                        <textarea id="service_comment" name="service_comment" class="form-control" rows="4" required><?php echo htmlspecialchars($myServiceReview['comment'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" name="submit_service_review" value="1" class="btn btn-primary">Save Review</button>
                </form>
            </div>
            </div>

            <div class="dashboard-panel" id="orders" data-dashboard-panel>
            <div class="dashboard-card mt-4">
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
                                $hasReturn = [];
                                try {
                                    $rs = $conn->prepare("SELECT DISTINCT order_id FROM `returns` WHERE user_id = ?");
                                    $rs->execute([$userId]);
                                    foreach ($rs->fetchAll() as $rowR) {
                                        $hasReturn[(int)$rowR['order_id']] = true;
                                    }
                                } catch (Throwable $e) { }

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
                                                        <img src="<?php echo htmlspecialchars(resolve_product_image($item['main_image'], $item['name'])); ?>"
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                             style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                                             data-fallback="https://via.placeholder.com/50">
                                                        <div class="ms-3">
                                                            <div><strong><?php echo htmlspecialchars($item['name']); ?></strong></div>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($item['brand']); ?> -
                                                                Qty: <?php echo $item['quantity']; ?> -
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
            </div>

            <div class="dashboard-panel" id="returns" data-dashboard-panel>
            <div class="dashboard-card mt-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">My Returns</h4>
                </div>
                <?php
                $myReturns = [];
                try {
                    $rStmt = $conn->prepare("SELECT r.*, o.total_amount, o.created_at as order_date FROM `returns` r JOIN orders o ON r.order_id = o.id WHERE r.user_id = ? ORDER BY r.created_at DESC");
                    $rStmt->execute([$userId]);
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
                                        <td>#<?php echo (int)$r['order_id']; ?><br><small class="text-muted"><?php echo date('M d, Y', strtotime($r['order_date'])); ?> - <?php echo formatPrice($r['total_amount']); ?></small></td>
                                        <td><?php echo htmlspecialchars($r['reason']); ?></td>
                                        <td><span class="badge badge-<?php echo $r['status'] === 'rejected' ? 'danger' : ($r['status'] === 'approved' ? 'success' : ($r['status'] === 'processing' ? 'info' : 'warning')); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

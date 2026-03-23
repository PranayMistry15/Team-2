<?php

$pageTitle = 'Checkout - Laptro';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/url-helper.php';

// Require login before any output
if (!isLoggedIn()) {
    redirect(url('login.php?redirect=checkout'));
}

$conn = getDBConnection();

// Get cart items 
$stmt = $conn->prepare("SELECT cart.*, products.* FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = ?");
$stmt->execute([getUserId()]);
$cartItems = $stmt->fetchAll();
if (empty($cartItems)) {
    redirect(url('cart.php'));
}

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) { $subtotal += $item['price'] * $item['quantity']; }
$tax = $subtotal * 0.1;
$total = $subtotal + $tax;

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([getUserId()]);
$user = $stmt->fetch();

$errors = [];
// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_abort();
    $address = clean($_POST['address']);
    $city = clean($_POST['city']);
    $postalCode = clean($_POST['postal_code']);
    $paymentMethod = clean($_POST['payment_method']);
    $phone = clean($_POST['phone'] ?? '');

    $cr = constraints('checkout');
    v_required($address, 'Address', $errors);
    v_string_length($address, 'Address', $cr['address']['min'], $cr['address']['max'], $errors);
    v_required($city, 'City', $errors);
    v_string_length($city, 'City', $cr['city']['min'], $cr['city']['max'], $errors);
    v_required($postalCode, 'Postal code', $errors);
    v_string_length($postalCode, 'Postal code', $cr['postal_code']['min'], $cr['postal_code']['max'], $errors);
    v_required($paymentMethod, 'Payment method', $errors);
    $allowedPayments = ['card','paypal','bank_transfer'];
    if (!in_array($paymentMethod, $allowedPayments, true)) { $errors[] = 'Select a valid payment method'; }
    if ($phone !== '') {
        v_matches($phone, 'Phone', '/^[0-9\+\-\s\(\)]{7,20}$/', $errors, 'Phone number format is invalid');
    }
    v_matches($postalCode, 'Postal code', '/^[A-Za-z0-9 \-]{3,12}$/', $errors, 'Postal code should be 3-12 letters/digits');

    if (empty($errors)) {
        try {
            $conn->beginTransaction();
            $shippingAddress = "$address, $city, $postalCode";
            $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status, shipping_address, payment_method) VALUES (?, ?, 'pending', ?, ?)");
            $stmt->execute([getUserId(), $total, $shippingAddress, $paymentMethod]);
            $orderId = $conn->lastInsertId();

            foreach ($cartItems as $item) {
                $lock = $conn->prepare('SELECT stock FROM products WHERE id = ? FOR UPDATE');
                $lock->execute([$item['product_id']]);
                $row = $lock->fetch();
                $currentStock = (int)($row['stock'] ?? 0);
                if ($currentStock < (int)$item['quantity']) {
                    throw new Exception('Insufficient stock for product ID ' . (int)$item['product_id']);
                }
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
                $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([getUserId()]);
            $conn->commit();
            setFlash('success', 'Order placed successfully. Order #' . $orderId . ' is now pending admin processing.');
            redirect(url('dashboard.php'));
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = 'Error placing order. Please try again.';
        }
    }
}

// Only render header + page after handling redirects
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container section-padding">
    <h1 class="mb-4">Checkout</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" id="checkoutForm">
        <?php echo csrf_field(); ?>
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="mb-4">Shipping Information</h4>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Street Address *</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" 
                                   required placeholder="123 Main Street">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" 
                                       required placeholder="London">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal Code *</label>
                                <input type="text" name="postal_code" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>" 
                                       required placeholder="SW1A 1AA">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" autocomplete="tel" inputmode="tel"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   placeholder="+44 20 1234 5678">
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <h4 class="mb-4">Payment Method</h4>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This is a demo. No actual payment will be processed.
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="card" value="card" checked required>
                            <label class="form-check-label" for="card">
                                <i class="fas fa-credit-card me-2"></i>Credit/Debit Card
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="paypal" value="paypal">
                            <label class="form-check-label" for="paypal">
                                <i class="fab fa-paypal me-2"></i>PayPal
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="payment_method" 
                                   id="bank" value="bank_transfer">
                            <label class="form-check-label" for="bank">
                                <i class="fas fa-university me-2"></i>Bank Transfer
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="order-summary">
                    <h4 class="mb-4">Order Summary</h4>
                    
                    <?php foreach ($cartItems as $item): ?>
                        <div class="d-flex mb-3">
                            <img src="<?php echo htmlspecialchars(resolve_product_image($item['main_image'], $item['name'])); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;"
                                 onerror="this.src='https://via.placeholder.com/60'">
                            <div class="ms-3 flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                            </div>
                            <div class="text-end">
                                <strong><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <hr>
                    
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Tax (10%)</span>
                        <span><?php echo formatPrice($tax); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span class="text-success">FREE</span>
                    </div>
                    
                    <hr>
                    
                    <div class="summary-row total">
                        <span>Total</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mt-4">
                        <i class="fas fa-lock me-2"></i>Place Order
                    </button>
                    
                    <p class="text-center text-muted mt-3" style="font-size: 0.85rem;">
                        <i class="fas fa-shield-alt me-1"></i>Secure checkout
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

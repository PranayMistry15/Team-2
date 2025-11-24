<?php

$pageTitle = 'Shopping Cart - Laptro';
require_once __DIR__ . '/../includes/header.php';

$conn = getDBConnection();

// Get cart items
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT cart.*, products.* FROM cart JOIN products ON cart.product_id = products.id WHERE cart.user_id = ?");
    $stmt->execute([getUserId()]);
} else {
    $stmt = $conn->prepare("SELECT cart.*, products.* FROM cart JOIN products ON cart.product_id = products.id WHERE cart.session_id = ?");
    $stmt->execute([getCartSessionId()]);
}

$cartItems = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cartItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.1; // 10% tax tho can adjust to UK limit idk what the tax regulations for UK are will research
$total = $subtotal + $tax;
?>

<div class="container section-padding">
    <h1 class="mb-4">Shopping Cart</h1>
    
    <?php if (empty($cartItems)): ?>
        <div class="text-center py-5">
            <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
            <h3>Your cart is empty</h3>
            <p class="text-muted mb-4">Looks like you haven't added anything to your cart yet.</p>
            <a href="<?php echo url('products.php'); ?>" class="btn btn-primary">Start Shopping</a>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-body">
                        <table class="cart-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cartItems as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="/assets/products/<?php echo $item['main_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     class="cart-item-image me-3"
                                                     onerror="this.src='https://via.placeholder.com/80?text=Product'">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo formatPrice($item['price']); ?></td>
                                        <td>
                                            <form action="<?php echo url('cart-handler.php'); ?>" method="POST" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="update">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                                       min="1" max="<?php echo $item['stock']; ?>" 
                                                       class="quantity-input"
                                                       onchange="this.form.submit()">
                                            </form>
                                        </td>
                                        <td><strong><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong></td>
                                        <td>
                                            <form action="<?php echo url('cart-handler.php'); ?>" method="POST" class="d-inline">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                        onclick="return confirm('Remove this item?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="<?php echo url('products.php'); ?>" class="btn btn-outline">
                        <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="order-summary">
                    <h4 class="mb-4">Order Summary</h4>
                    
                    <div class="summary-row">
                        <span>Subtotal (<?php echo count($cartItems); ?> items)</span>
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
                    
                    <?php if (isLoggedIn()): ?>
                        <a href="<?php echo url('checkout.php'); ?>" class="btn btn-primary w-100 mt-4">
                            Proceed to Checkout
                        </a>
                    <?php else: ?>
                        <a href="<?php echo url('login.php?redirect=checkout'); ?>" class="btn btn-primary w-100 mt-4">
                            Login to Checkout
                        </a>
                    <?php endif; ?>
                    
                    <div class="mt-4 p-3" style="background-color: var(--off-white); border-radius: 8px;">
                        <h6>We Accept</h6>
                        <div class="d-flex gap-2 mt-2">
                            <i class="fab fa-cc-visa fa-2x"></i>
                            <i class="fab fa-cc-mastercard fa-2x"></i>
                            <i class="fab fa-cc-paypal fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
       </tr>
   @endforeach

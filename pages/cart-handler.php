<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

initSession();
$conn = getDBConnection();

$action = $_POST['action'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_abort();
    }
    switch ($action) {
        case 'add':
            $conn->beginTransaction();
            $productId = (int)$_POST['product_id'];
            $quantity = (int)($_POST['quantity'] ?? 1);
            $valErrors = [];
            v_int_range($quantity, 'Quantity', 1, 99, $valErrors);
            if (!empty($valErrors)) {
                setFlash('error', implode('. ', $valErrors));
                $conn->rollBack();
                redirect($_SERVER['HTTP_REFERER'] ?? url('products.php'));
            }
            
            // Check if product exists and has stock
            $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                setFlash('error', 'Product not found');
                $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('products.php');
                redirect($back);
            }
            
            if ($product['stock'] < $quantity) {
                setFlash('error', 'Not enough stock available');
                $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('products.php');
                redirect($back);
            }
            
            // Check if item alr in cart
            if (isLoggedIn()) {
                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([getUserId(), $productId]);
            } else {
                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ?");
                $stmt->execute([getCartSessionId(), $productId]);
            }
            
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update quantity
                $newQuantity = $existing['quantity'] + $quantity;
                if ($newQuantity > $product['stock']) {
                    setFlash('error', 'Cannot add more than available stock');
                    $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('products.php');
                    redirect($back);
                }
                
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $stmt->execute([$newQuantity, $existing['id']]);
            } else {
                // Insert new
                if (isLoggedIn()) {
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([getUserId(), $productId, $quantity]);
                } else {
                    $stmt = $conn->prepare("INSERT INTO cart (session_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([getCartSessionId(), $productId, $quantity]);
                }
            }
            
            setFlash('success', 'Product added to cart!');
            $conn->commit();
            $back = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('products.php');
            redirect($back);
            break;
            
        case 'update':
            $conn->beginTransaction();
            $cartId = (int)$_POST['cart_id'];
            $quantity = (int)$_POST['quantity'];
            $valErrors = [];
            v_int_range($quantity, 'Quantity', 1, 99, $valErrors);
            if (!empty($valErrors)) {
                setFlash('error', implode('. ', $valErrors));
                $conn->rollBack();
                redirect(url('cart.php'));
            }
            
            if ($quantity < 1) {
                setFlash('error', 'Quantity must be at least 1');
                redirect(url('cart.php'));
            }
            
            // Get stock
            if (isLoggedIn()) {
                $stmt = $conn->prepare("
                    SELECT products.stock
                    FROM cart
                    JOIN products ON cart.product_id = products.id
                    WHERE cart.id = ? AND cart.user_id = ?
                ");
                $stmt->execute([$cartId, getUserId()]);
            } else {
                $stmt = $conn->prepare("
                    SELECT products.stock
                    FROM cart
                    JOIN products ON cart.product_id = products.id
                    WHERE cart.id = ? AND cart.session_id = ?
                ");
                $stmt->execute([$cartId, getCartSessionId()]);
            }
            $result = $stmt->fetch();

            if (!$result) {
                setFlash('error', 'Cart item not found');
                $conn->rollBack();
                redirect(url('cart.php'));
            }
            
            if ($quantity > $result['stock']) {
                setFlash('error', 'Not enough stock available');
                $conn->rollBack();
                redirect(url('cart.php'));
            }
            
            if (isLoggedIn()) {
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$quantity, $cartId, getUserId()]);
            } else {
                $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND session_id = ?");
                $stmt->execute([$quantity, $cartId, getCartSessionId()]);
            }
            
            setFlash('success', 'Cart updated!');
            $conn->commit();
            redirect(url('cart.php'));
            break;
            
        case 'remove':
            $cartId = (int)$_POST['cart_id'];

            if (isLoggedIn()) {
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cartId, getUserId()]);
            } else {
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
                $stmt->execute([$cartId, getCartSessionId()]);
            }
            
            if ($stmt->rowCount() > 0) {
                setFlash('success', 'Item removed from cart');
            } else {
                setFlash('error', 'Cart item not found');
            }
            redirect(url('cart.php'));
            break;
            
        default:
            setFlash('error', 'Invalid action');
            redirect(url('cart.php'));
    }
} catch (Exception $e) {
    setFlash('error', 'An error occurred. Please try again.');
    redirect(url('cart.php'));
}


?>

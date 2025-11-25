<?php

$pageTitle = 'Product Details - Laptro';
require_once __DIR__ . '/../includes/header.php';

$productId = $_GET['id'] ?? 0;

$conn = getDBConnection();

// Get product details
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    redirect(url('products.php'));
}

// Get reviews
$reviewsStmt = $conn->prepare("
    SELECT reviews.*, users.name as user_name 
    FROM reviews 
    JOIN users ON reviews.user_id = users.id 
    WHERE reviews.product_id = ? 
    ORDER BY reviews.created_at DESC
");
$reviewsStmt->execute([$productId]);
$reviews = $reviewsStmt->fetchAll();

$rating = getAverageRating($productId);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    verify_csrf_or_abort();
    if (!isLoggedIn()) {
        setFlash('error', 'Please login to submit a review');
    } else {
        $userId = getUserId();
        $ratingValue = (int)$_POST['rating'];
        $title = clean($_POST['title']);
        $comment = clean($_POST['comment']);
        
        $revErrors = [];
        $rr = constraints('review');
        v_int_range($ratingValue, 'Rating', $rr['rating']['min'], $rr['rating']['max'], $revErrors);
        v_string_length($title, 'Review title', $rr['title']['min'], $rr['title']['max'], $revErrors);
        v_string_length($comment, 'Review', $rr['comment']['min'], $rr['comment']['max'], $revErrors);
        if (!empty($revErrors)) {
            setFlash('error', implode('. ', $revErrors));
        } else {
        $insertStmt = $conn->prepare("
            INSERT INTO reviews (product_id, user_id, rating, title, comment) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($insertStmt->execute([$productId, $userId, $ratingValue, $title, $comment])) {
            setFlash('success', 'Review submitted successfully!');
            redirect(url('product.php?id=' . $productId));
        } else {
            setFlash('error', 'Error submitting review');
        }
        }
    }
}

$pageTitle = $product['name'] . ' - Laptro';
?>

<div class="container section-padding">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?php echo url('products.php'); ?>">Laptops</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['name']); ?></li>
        </ol>
    </nav>
    
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="product-detail-image">
                <img src="/assets/products/<?php echo $product['main_image']; ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                     id="mainImage" width="600" height="450" sizes="(min-width: 992px) 600px, 100vw"
                     onerror="this.src='https://via.placeholder.com/600x450?text=<?php echo urlencode($product['name']); ?>'">
            </div>
            
            <div class="product-thumbnails">
                <div class="thumbnail active" onclick="changeImage('/assets/products/<?php echo $product['main_image']; ?>')">
                    <img src="/assets/products/<?php echo $product['main_image']; ?>" loading="lazy" width="150" height="150"
                         alt="Thumbnail 1"
                         onerror="this.src='https://via.placeholder.com/150?text=1'">
                </div>
                <?php if ($product['image_2']): ?>
                    <div class="thumbnail" onclick="changeImage('/assets/products/<?php echo $product['image_2']; ?>')">
                        <img src="/assets/products/<?php echo $product['image_2']; ?>" loading="lazy" width="150" height="150"
                             alt="Thumbnail 2"
                             onerror="this.src='https://via.placeholder.com/150?text=2'">
                    </div>
                <?php endif; ?>
                <?php if ($product['image_3']): ?>
                    <div class="thumbnail" onclick="changeImage('/assets/products/<?php echo $product['image_3']; ?>')">
                        <img src="/assets/products/<?php echo $product['image_3']; ?>" loading="lazy" width="150" height="150"
                             alt="Thumbnail 3"
                             onerror="this.src='https://via.placeholder.com/150?text=3'">
                    </div>
                <?php endif; ?>
                <?php if ($product['image_4']): ?>
                    <div class="thumbnail" onclick="changeImage('/assets/products/<?php echo $product['image_4']; ?>')">
                        <img src="/assets/products/<?php echo $product['image_4']; ?>" loading="lazy" width="150" height="150"
                             alt="Thumbnail 4"
                             onerror="this.src='https://via.placeholder.com/150?text=4'">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="product-brand text-uppercase mb-2"><?php echo htmlspecialchars($product['brand']); ?></div>
            <h1 class="h2"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="product-rating mb-3">
                <?php echo renderStars($rating['average']); ?>
                <span class="ms-2"><?php echo $rating['average']; ?> out of 5</span>
                <span class="text-muted ms-2">(<?php echo $rating['total']; ?> reviews)</span>
            </div>
            
            <div class="product-price mb-4">
                <h2><?php echo formatPrice($product['price']); ?></h2>
            </div>
            
            <div class="mb-4">
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
            
            <div class="mb-4">
                <?php if ($product['stock'] > 0): ?>
                    <span class="badge badge-success">In Stock (<?php echo $product['stock']; ?> available)</span>
                <?php else: ?>
                    <span class="badge badge-danger">Out of Stock</span>
                <?php endif; ?>
            </div>
            
            <?php if ($product['stock'] > 0): ?>
                <form action="<?php echo url('cart-handler.php'); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    
                    <div class="row g-3 mb-4">
                        <div class="col-auto">
                            <label class="form-label" for="qty">Quantity</label>
                            <input type="number" id="qty" name="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock']; ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg me-2">
                        <i class="fas fa-shopping-cart"></i> Add to Cart
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12">
            <h3>Technical Specifications</h3>
            <table class="spec-table">
                <tr>
                    <td>Processor (CPU)</td>
                    <td><?php echo htmlspecialchars($product['cpu']); ?></td>
                </tr>
                <tr>
                    <td>Memory (RAM)</td>
                    <td><?php echo htmlspecialchars($product['ram']); ?></td>
                </tr>
                <tr>
                    <td>Storage</td>
                    <td><?php echo htmlspecialchars($product['storage']); ?></td>
                </tr>
                <tr>
                    <td>Graphics (GPU)</td>
                    <td><?php echo htmlspecialchars($product['gpu']); ?></td>
                </tr>
                <tr>
                    <td>Display</td>
                    <td><?php echo htmlspecialchars($product['screen_size']); ?> - <?php echo htmlspecialchars($product['resolution']); ?></td>
                </tr>
                <tr>
                    <td>Battery</td>
                    <td><?php echo htmlspecialchars($product['battery']); ?></td>
                </tr>
                <tr>
                    <td>Weight</td>
                    <td><?php echo htmlspecialchars($product['weight']); ?></td>
                </tr>
                <tr>
                    <td>Operating System</td>
                    <td><?php echo htmlspecialchars($product['os']); ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="row mt-5">
        <div class="col-12">
            <h3>Customer Reviews</h3>
            <?php if (isLoggedIn()): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Write a Review</h5>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Rating *</label>
                                <div class="rating-input">
                                    <input type="radio" name="rating" value="5" id="star5" required>
                                    <label for="star5">★</label>
                                    <input type="radio" name="rating" value="4" id="star4">
                                    <label for="star4">★</label>
                                    <input type="radio" name="rating" value="3" id="star3">
                                    <label for="star3">★</label>
                                    <input type="radio" name="rating" value="2" id="star2">
                                    <label for="star2">★</label>
                                    <input type="radio" name="rating" value="1" id="star1">
                                    <label for="star1">★</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Review Title *</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Your Review *</label>
                                <textarea name="comment" class="form-control" rows="4" required></textarea>
                            </div>
                            
                            <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <a href="<?php echo url('login.php'); ?>">Login</a> to write a review
                </div>
            <?php endif; ?>
            
            <?php if (empty($reviews)): ?>
                <p class="text-muted">No reviews yet. Be the first to review this product!</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="review-card">
                        <div class="review-header">
                            <div>
                                <div class="reviewer-name"><?php echo htmlspecialchars($review['user_name']); ?></div>
                                <div class="product-rating">
                                    <?php echo renderStars($review['rating']); ?>
                                </div>
                            </div>
                            <div class="review-date">
                                <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                            </div>
                        </div>
                        
                        <?php if ($review['title']): ?>
                            <h5><?php echo htmlspecialchars($review['title']); ?></h5>
                        <?php endif; ?>
                        
                        <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changeImage(src) {
    document.getElementById('mainImage').src = src;
    document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
    event.currentTarget.classList.add('active');
}
</script>

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    font-size: 2rem;
}

.rating-input input {
    display: none;
}

.rating-input label {
    cursor: pointer;
    color: #ddd;
    transition: color 0.3s;
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffa500;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


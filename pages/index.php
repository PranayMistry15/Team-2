<?php

$pageTitle = 'Laptro - Premium Laptops for Tech Students';
require_once __DIR__ . '/../includes/header.php';

// Get featured products
$conn = getDBConnection();
$stmt = $conn->query("SELECT * FROM products WHERE is_featured = 1 LIMIT 6");
$featuredProducts = $stmt->fetchAll();
?>

<section class="hero">
        <div class="hero-slide active">
            <div class="container">
                <div class="hero-content">

                    <div class="row">
                      <div class="col-md-6 col-lg-6 col-12">
                            <h1 class="slides_heading mt-5">Find Your Perfect Laptop</h1>
                    <p>Premium laptops curated specifically for tech-intensive students. Performance meets portability.</p>
                    <div class="mt-4">
                        <a href="<?php echo url('products.php'); ?>" class="btn btn-primary btn-lg me-3">Shop Now</a>
                        <a href="<?php echo url('buying-guide.php'); ?>" class="btn btn-outline btn-lg">Take the Quiz</a>
                    </div>
                      </div>  
                      <div class="col-md-6 col-lg-6 col-12">
                          <img src="<?php echo asset('images/slide1.png'); ?>" class="hero_image" width="800" height="600" sizes="(min-width: 992px) 800px, 100vw">
                      </div>  
                    </div>
                  
                </div>
            </div>
        </div>

        <div class="hero-slide">
            <div class="container">
                <div class="hero-content">

                    <div class="row">
                        <div class="col-md-6 col-lg-6 col-12">
                             <h1 class="slides_heading mt-5">Power That Keeps Up With You</h1>
                    <p>High-performance processors, stunning displays, and all-day battery life. Built for students who demand more.</p>
                    <div class="mt-4">
                        <a href="<?php echo url('products.php'); ?>" class="btn btn-primary btn-lg me-3">Explore Laptops</a>
                        <a href="<?php echo url('buying-guide.php'); ?>" class="btn btn-outline btn-lg">Learn More</a>
                    </div>
                        </div>
                        <div class="col-md-6 col-lg-6 col-12">  <img src="<?php echo asset('images/hero-laptop-2.jpg'); ?>" class="hero_image" width="800" height="600" sizes="(min-width: 992px) 800px, 100vw"></div>
                    </div>
                   
                </div>
            </div>
        </div>

        <div class="hero-slide">
            <div class="container">
                <div class="hero-content">

                    <div class="row">
                        <div class="col-md-6 col-lg-6 col-12">
                             <h1 class="slides_heading mt-5">Built for Your Success</h1>
                    <p>Get exclusive deals on top brands. Save big on laptops perfect for coding, design, and everything in between.</p>
                    <div class="mt-4">
                        <a href="<?php echo url('products.php'); ?>" class="btn btn-primary btn-lg me-3">View Deals</a>
                        <a href="<?php echo url('buying-guide.php'); ?>" class="btn btn-outline btn-lg">Find Your Match</a>
                    </div>
                        </div>
                        <div class="col-md-6 col-lg-6 col-12"><img src="<?php echo asset('images/hero-laptop-2.jpg'); ?>" class="hero_image" width="800" height="600" sizes="(min-width: 992px) 800px, 100vw"></div>
                    </div>
                   
                </div>
            </div>
        </div>
        <div class="slider-indicators">
            <button class="slider-indicator active" data-slide="0"></button>
            <button class="slider-indicator" data-slide="1"></button>
            <button class="slider-indicator" data-slide="2"></button>
        </div>
    </section>

<section class="section-padding">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Featured Laptops</h2>
            <p class="text-muted">Handpicked for students who demand the best</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($featuredProducts as $product): 
                $rating = getAverageRating($product['id']);
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="product-card fade-in">
                        <a href="<?php echo url('product.php?id=' . $product['id']); ?>">
                            <img src="<?php echo asset('products/' . $product['main_image']); ?>" loading="lazy" width="400" height="300"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='<?php echo asset('images/hero-laptop-1.jpg'); ?>'">
                        </a>
                        <div class="product-card-body">
                            <div class="product-brand"><?php echo htmlspecialchars($product['brand']); ?></div>
                            <h3 class="product-title">
                                <a href="<?php echo url('product.php?id=' . $product['id']); ?>">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h3>
                            <div class="product-specs">
                                <?php echo htmlspecialchars($product['cpu']); ?> • 
                                <?php echo htmlspecialchars($product['ram']); ?>
                            </div>
                            <div class="product-rating">
                                <?php echo renderStars($rating['average']); ?>
                                <span class="text-muted ms-2">(<?php echo $rating['total']; ?>)</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="product-price"><?php echo formatPrice($product['price']); ?></div>
                                <a href="<?php echo url('product.php?id=' . $product['id']); ?>" class="btn btn-outline btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <a href="<?php echo url('products.php'); ?>" class="btn btn-primary">View All Laptops</a>
        </div>
    </div>
</section>

<section class="section-padding" style="background-color: var(--off-white);">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-md-4">
                <i class="fas fa-truck fa-3x mb-3"></i>
                <h4>Fast Delivery</h4>
                <p class="text-muted">Get your laptop delivered within 2-3 business days</p>
            </div>
            <div class="col-md-4">
                <i class="fas fa-shield-alt fa-3x mb-3"></i>
                <h4>Student Warranty</h4>
                <p class="text-muted">Extended warranty coverage for students</p>
            </div>
            <div class="col-md-4">
                <i class="fas fa-headset fa-3x mb-3"></i>
                <h4>24/7 Support</h4>
                <p class="text-muted">Expert support whenever you need it</p>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h2>Why Tech Students Choose Laptro</h2>
                <p class="lead">We understand the unique demands of tech-intensive programs.</p>
                <ul class="list-unstyled mt-4">
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Laptops tested for coding, design, and engineering software</li>
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Performance specifications that meet academic requirements</li>
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Honest reviews from fellow students</li>
                    <li class="mb-3"><i class="fas fa-check-circle me-2"></i> Expert buying guide tailored to your field of study</li>
                </ul>
                <a href="<?php echo url('buying-guide.php'); ?>" class="btn btn-primary mt-3">Find Your Perfect Match</a>
            </div>
            <div class="col-lg-6 text-center">
                <img src="<?php echo asset('images/hero-laptop-2.jpg'); ?>" alt="Students Using Laptops" class="img-fluid rounded shadow-lg">
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>

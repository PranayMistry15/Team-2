<?php

$pageTitle = 'About Us - Laptro';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="page-hero-copy">
                    <span class="eyebrow">About Laptro</span>
                    <h1>About our store</h1>
                    <p class="lead">Laptro is a student-focused laptop store built to make browsing and ordering simpler.</p>
                    <div class="d-flex gap-3 flex-wrap mt-4">
                        <a href="<?php echo url('products.php'); ?>" class="btn btn-primary">Browse Laptops</a>
                        <a href="<?php echo url('contact.php'); ?>" class="btn btn-outline">Contact Us</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <img src="<?php echo asset('images/hero-laptop-2.jpg'); ?>" alt="Laptro laptop showcase" class="img-fluid rounded shadow-lg">
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="eyebrow">Vision</span>
                <h2>Help students choose the right laptop more easily.</h2>
                <p class="mb-0">The site is designed to help users compare products, check stock, and order without unnecessary steps.</p>
            </div>
            <div class="col-lg-5">
                <div class="brand-panel">
                    <span class="eyebrow">Brand</span>
                    <h3>Simple and clear</h3>
                    <p class="mb-0">A black, white, and grey layout keeps the store clean and easy to use.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding" style="background-color: var(--off-white);">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="info-card">
                    <span class="eyebrow">Audience</span>
                    <h3>Who we serve</h3>
                    <p>Mainly university students.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-card">
                    <span class="eyebrow">Scope</span>
                    <h3>What we offer</h3>
                    <p>Browsing, filtering, ordering, and support.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-card">
                    <span class="eyebrow">Promise</span>
                    <h3>What matters</h3>
                    <p>Clear product details and simple navigation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-padding">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-6">
                <span class="eyebrow">Products</span>
                <h2>The store focuses on laptops that suit student work.</h2>
                <ul class="check-list">
                    <li>Laptops for study, coding, and everyday work</li>
                    <li>Portable options for moving between classes</li>
                    <li>Product pages with images, prices, reviews, and stock</li>
                </ul>
                <a href="<?php echo url('products.php'); ?>" class="btn btn-primary">View Products</a>
            </div>
            <div class="col-lg-6">
                <div class="about-image-stack">
                    <img src="<?php echo asset('images/dell-xps-13.jpg'); ?>" alt="Featured Dell XPS laptop" class="about-stack-main">
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

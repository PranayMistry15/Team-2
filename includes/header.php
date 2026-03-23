<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/url-helper.php';

// Security headers before any output
set_security_headers();

initSession();
user_security_ensure_columns();
product_schema_ensure_columns();
enforce_password_change_if_needed();
$cartCount = getCartCount();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isAdminPage = strpos($_SERVER['PHP_SELF'] ?? '', '/admin/') !== false;
$styleVersion = file_exists(__DIR__ . '/../css/style.css') ? filemtime(__DIR__ . '/../css/style.css') : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Laptro - Premium laptops curated for tech students. Find the perfect laptop for coding, design, and engineering.">
    <meta name="keywords" content="laptops, tech students, programming laptops, engineering laptops, student laptops">
    <title><?php echo $pageTitle ?? 'Laptro - Laptops for Tech Students'; ?></title>
    
    <link rel="icon" type="image/png" href="<?php echo asset('images/favicon.png'); ?>">
    <link rel="preload" as="style" href="<?php echo url('css/style.css?v=' . $styleVersion); ?>">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo url('css/style.css?v=' . $styleVersion); ?>">
    <?php if ($isAdminPage): ?>
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/webfonts/fa-solid-900.woff2" as="font" type="font/woff2" crossorigin>
    <?php endif; ?>
    <?php if ($isAdminPage): ?>
    <style>
        .admin-sidebar { width: 260px; position: fixed; }
        .admin-main { margin-left: 260px; }
        .admin-sidebar .nav-link { display: flex; align-items: center; gap: 1rem; white-space: nowrap; min-height: 44px; }
        .admin-sidebar .nav-link i { display: inline-block; min-width: 1.25em; width: 1.25em; line-height: 1; }
        .fas, .fa-solid, .far, .fa-regular, .fab, .fa-brands { display: inline-block; width: 1.25em; min-width: 1.25em; text-align: center; }
    </style>
    <?php endif; ?>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <?php if (!$isAdminPage): ?>
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container">
                <a class="navbar-brand" href="<?php echo url('index.php'); ?>">
                    <img src="<?php echo asset('images/logo.png'); ?>" alt="Laptro Logo">
                    LAPTRO
                </a>
                
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'index' ? 'active' : ''; ?>" href="<?php echo url('index.php'); ?>">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'about' ? 'active' : ''; ?>" href="<?php echo url('about.php'); ?>">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'products' ? 'active' : ''; ?>" href="<?php echo url('products.php'); ?>">Laptops</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'buying-guide' ? 'active' : ''; ?>" href="<?php echo url('buying-guide.php'); ?>">Buying Guide</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $currentPage == 'contact' ? 'active' : ''; ?>" href="<?php echo url('contact.php'); ?>">Contact</a>
                        </li>
                        
                        <?php if (isLoggedIn()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage == 'dashboard' ? 'active' : ''; ?>" href="<?php echo url('dashboard.php'); ?>">
                                    <i class="fas fa-user"></i> Dashboard
                                </a>
                            </li>
                            <?php if (isAdmin()): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?php echo url('admin/index.php'); ?>">
                                        <i class="fas fa-cog"></i> Admin
                                    </a>
                                </li>
                            <?php endif; ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?php echo url('logout.php'); ?>">Logout</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo $currentPage == 'login' ? 'active' : ''; ?>" href="<?php echo url('login.php'); ?>">Login</a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="nav-item ms-3">
                            <a class="nav-link cart-icon" href="<?php echo url('cart.php'); ?>">
                                <i class="fas fa-shopping-cart"></i>
                                <?php if ($cartCount > 0): ?>
                                    <span class="cart-count"><?php echo $cartCount; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <?php
    $successMsg = getFlash('success');
    $errorMsg = getFlash('error');
    ?>
    
    <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show<?php echo $isAdminPage ? '' : ' mx-auto'; ?>" 
             style="<?php echo $isAdminPage 
                    ? 'margin: 1rem 1rem 0 270px; width: calc(100% - 290px);' 
                    : 'max-width: 1140px; margin-top: 1rem;'; ?>" 
             role="alert" aria-live="polite">
            <?php echo htmlspecialchars($successMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show<?php echo $isAdminPage ? '' : ' mx-auto'; ?>" 
             style="<?php echo $isAdminPage 
                    ? 'margin: 1rem 1rem 0 270px; width: calc(100% - 290px);' 
                    : 'max-width: 1140px; margin-top: 1rem;'; ?>" 
             role="alert" aria-live="assertive">
            <?php echo htmlspecialchars($errorMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <main id="main-content" tabindex="-1">

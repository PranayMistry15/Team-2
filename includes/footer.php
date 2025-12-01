<?php
?>

    </main>
    <?php if (!isset($isAdminPage) || !$isAdminPage): ?>
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>About Laptro</h5>
                    <p class="text-muted">
                        Premium laptops curated for tech-intensive students. 
                        We understand your needs and help you find the perfect device for your studies.
                    </p>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="<?php echo url('index.php'); ?>">Home</a></li>
                        <li><a href="<?php echo url('products.php'); ?>">Shop Laptops</a></li>
                        <li><a href="<?php echo url('buying-guide.php'); ?>">Buying Guide</a></li>
                    </ul>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h5>Account</h5>
                    <ul class="footer-links">
                        <?php if (isLoggedIn()): ?>
                            <li><a href="<?php echo url('dashboard.php'); ?>">My Account</a></li>
                            <li><a href="<?php echo url('cart.php'); ?>">Shopping Cart</a></li>
                            <li><a href="<?php echo url('logout.php'); ?>">Logout</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo url('login.php'); ?>">Login</a></li>
                            <li><a href="<?php echo url('login.php'); ?>">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5>Contact Us</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-envelope me-2"></i> support@laptro.com</li>
                        <li><i class="fas fa-phone me-2"></i> +44 123 456 7890</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> London, UK</li>
                    </ul>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-lg"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Laptro. All rights reserved. | Built for students, by students.</p>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo url('js/main.js'); ?>"></script>
</body>
</html>

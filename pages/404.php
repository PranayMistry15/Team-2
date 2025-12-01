<?php
require_once __DIR__ . '/../includes/header.php';
http_response_code(404);
?>
<div class="container section-padding">
  <div class="text-center" style="max-width:720px;margin:0 auto;">
    <h1>Page Not Found</h1>
    <p class="text-muted">The page you were looking for doesn&rsquo;t exist or may have moved.</p>
    <a href="<?php echo url('index.php'); ?>" class="btn btn-primary">Go to Homepage</a>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

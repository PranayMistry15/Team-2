<?php

// Error view
require_once __DIR__ . '/header.php';
?>
<div class="container section-padding">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?php echo url('index.php'); ?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($err_title); ?></li>
        </ol>
    </nav>

    <div class="text-center" style="max-width:720px;margin:0 auto;">
        <h1><?php echo htmlspecialchars($err_title); ?></h1>
        <p class="text-muted"><?php echo $err_message; ?></p>
        <a class="btn btn-primary" href="<?php echo url('index.php'); ?>">Go Home</a>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>


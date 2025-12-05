<?php
require_once __DIR__ . '/../includes/url-helper.php';
session_start();
session_destroy();
header('Location: ' . url('index.php'));
exit;
?>

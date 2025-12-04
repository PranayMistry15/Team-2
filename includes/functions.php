<?php

require_once __DIR__ . '/url-helper.php';

// Start session if not started
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $params = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
        } else {
            session_set_cookie_params($params['lifetime'], $params['path'] . '; samesite=' . $params['samesite'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_start();
    }
}

function set_security_headers() {
    if (headers_sent()) return;
    $self = rtrim(BASE_URL, '/');
    $csp = "default-src 'self'; " .
           "img-src 'self' data: https://via.placeholder.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
           "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// Check if user is logged in
function isLoggedIn() {
    initSession();
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    initSession();
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Get current user ID
function getUserId() {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

// Redirect function
function redirect($url) {
    if (preg_match('#^https?://#i', $url)) {
        header("Location: $url");
        exit();
    }

    if (strlen($url) && $url[0] === '/') {
        $basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
        if ($basePath && strpos($url, $basePath . '/') === 0) {
            $url = substr($url, strlen($basePath) + 1); 
        } else {
            $url = ltrim($url, '/');
        }
    }

    $full = url($url);
    header("Location: $full");
    exit();
}

// CSRF helper
function csrf_ensure_token() {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_token() {
    return csrf_ensure_token();
}

function csrf_field() {
    $token = csrf_ensure_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_or_abort() {
    initSession();
    $valid = isset($_POST['csrf_token'], $_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    if (!$valid) {
        setFlash('error', 'Invalid or missing security token. Please try again.');
        header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : url('index.php')));
        exit();
    }
}

// Input validation helpers
function v_required($value, $label, array &$errors) {
    if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
        $errors[] = "$label is required";
    }
}

function v_email($value, $label, array &$errors) {
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Enter a valid $label";
    }
}

function v_string_length($value, $label, $min, $max, array &$errors) {
    $len = mb_strlen((string)$value);
    if ($len < $min || $len > $max) {
        $errors[] = "$label must be between $min and $max characters";
    }
}

function v_int_range($value, $label, $min, $max, array &$errors) {
    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
        $errors[] = "$label must be an integer";
        return;
    }
    $iv = (int)$value;
    if ($iv < $min || $iv > $max) {
        $errors[] = "$label must be between $min and $max";
    }
}

// Session based limiting
function rate_limit_allow($key, $limit, $windowSeconds) {
    initSession();
    $now = time();
    if (!isset($_SESSION['rate'][$key])) {
        $_SESSION['rate'][$key] = ['reset' => $now + $windowSeconds, 'count' => 0];
    }
    $bucket = &$_SESSION['rate'][$key];
    if ($now > $bucket['reset']) {
        $bucket = ['reset' => $now + $windowSeconds, 'count' => 0];
    }
    if ($bucket['count'] >= $limit) {
        return false;
    }
    $bucket['count']++;
    return true;
}

// Sanitize input
function clean($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Flash message
function setFlash($type, $message) {
    initSession();
    $_SESSION['flash'][$type] = $message;
}

function getFlash($type) {
    initSession();
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// Generate session ID for cart
function getCartSessionId() {
    initSession();
    if (!isset($_SESSION['cart_session_id'])) {
        $_SESSION['cart_session_id'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_session_id'];
}

// Get cart count
function getCartCount() {
    $conn = getDBConnection();
    
    if (isLoggedIn()) {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->execute([getUserId()]);
    } else {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ?");
        $stmt->execute([getCartSessionId()]);
    }
    
    $result = $stmt->fetch();
    return $result['total'] ?? 0;
}

// Calculate average rating
function getAverageRating($productId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = ?");
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    
    return [
        'average' => round($result['avg_rating'] ?? 0, 1),
        'total' => $result['total_reviews'] ?? 0
    ];
}

// Render star rating
function renderStars($rating) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5 ? 1 : 0;
    $emptyStars = 5 - $fullStars - $halfStar;
    
    $html = '';
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star"></i>';
    }
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt"></i>';
    }
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star"></i>';
    }
    
    return $html;
}
function constraints($entity) {
    static $cache = null;
    if ($cache === null) {
        $file = __DIR__ . '/validation_rules.php';
        $cache = file_exists($file) ? include $file : [];
        if (!is_array($cache)) { $cache = []; }
    }
    return $cache[$entity] ?? [];
}

// Additional validators
function v_password_strict($value, array &$errors, $label = 'Password', $min = 8, $max = 128) {
    v_string_length($value, $label, $min, $max, $errors);
    if (!preg_match('/[a-z]/', $value)) { $errors[] = "$label must include a lowercase letter"; }
    if (!preg_match('/[A-Z]/', $value)) { $errors[] = "$label must include an uppercase letter"; }
    if (!preg_match('/\d/', $value)) { $errors[] = "$label must include a digit"; }
}

function v_matches($value, $label, $regex, array &$errors, $message) {
    if (!preg_match($regex, (string)$value)) {
        $errors[] = $message ?: ("$label format is invalid");
    }
}

function v_decimal_range($value, $label, $min, $max, array &$errors) {
    if (!is_numeric($value)) {
        $errors[] = "$label must be a number";
        return;
    }
    $dv = (float)$value;
    if ($dv < $min || $dv > $max) {
        $errors[] = "$label must be between $min and $max";
    }
}

// Notif Logger
function notify_user_by_id($userId, $subject, $message) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        $email = $u['email'] ?? '';
        $name = $u['name'] ?? '';
        $line = date('c') . "\tuser_id=" . (int)$userId . "\tto=" . $email . "\tsubject=" . str_replace(["\r","\n"], ' ', $subject) . "\t" . str_replace(["\r","\n"], ' ', $message) . "\n";
        $logDir = __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
        @file_put_contents($logDir . '/notifications.log', $line, FILE_APPEND);
    } catch (Throwable $e) {
    }
}

<?php

require_once __DIR__ . '/url-helper.php';

// Start session if not started
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
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
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $self = rtrim(BASE_URL, '/');
    $csp = "default-src 'self'; " .
           "img-src 'self' data: https://via.placeholder.com; " .
           "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
           "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
           "connect-src 'self' ws: wss:; " .
           "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;";
    header('Content-Security-Policy: ' . $csp);
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: DENY');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Check if user is logged in
function isLoggedIn() {
    initSession();
    return isset($_SESSION['user_id']);
}

function user_security_ensure_columns() {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $conn = getDBConnection();
        $check = $conn->query("SHOW COLUMNS FROM users LIKE 'must_change_password'");
        if (!$check->fetch()) {
            $conn->exec("ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin");
        }
    } catch (Throwable $e) {
        app_log_error('Failed ensuring user security columns: ' . $e->getMessage());
    }

    $ensured = true;
}

function product_schema_ensure_columns() {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    try {
        $conn = getDBConnection();
        $check = $conn->query("SHOW COLUMNS FROM products LIKE 'pictures'");
        if (!$check->fetch()) {
            $conn->exec("ALTER TABLE products ADD COLUMN pictures LONGTEXT NULL AFTER image_4");
        }
    } catch (Throwable $e) {
        app_log_error('Failed ensuring product media columns: ' . $e->getMessage());
    }

    $ensured = true;
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

function requires_password_change() {
    initSession();
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    user_security_ensure_columns();

    static $cache = [];
    $userId = (int)$_SESSION['user_id'];
    if (array_key_exists($userId, $cache)) {
        return $cache[$userId];
    }

    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT must_change_password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        $cache[$userId] = !empty($row['must_change_password']);
    } catch (Throwable $e) {
        $cache[$userId] = false;
        app_log_error('Failed checking password change requirement: ' . $e->getMessage());
    }

    return $cache[$userId];
}

function clear_password_change_requirement($userId) {
    user_security_ensure_columns();
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE users SET must_change_password = 0 WHERE id = ?");
    $stmt->execute([(int)$userId]);
}

function enforce_password_change_if_needed() {
    if (!isLoggedIn() || !requires_password_change()) {
        return;
    }

    $current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $allowed = ['dashboard.php', 'logout.php', 'login.php'];
    if (!in_array($current, $allowed, true)) {
        setFlash('error', 'Please change your password before continuing.');
        redirect(url('dashboard.php#security'));
    }
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
        return '£' . number_format((float)$price, 2);
}

function stock_status_meta($stock) {
    $stock = (int)$stock;

    if ($stock <= 0) {
        return ['label' => 'Out of stock', 'class' => 'danger'];
    }
    if ($stock <= 5) {
        return ['label' => 'Low stock (' . $stock . ')', 'class' => 'warning'];
    }

    return ['label' => 'In stock (' . $stock . ')', 'class' => 'success'];
}

function normalize_image_lookup_key($value) {
    $value = strtolower((string)$value);
    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function find_asset_image_filename($value) {
    static $normalizedFiles = null;
    static $aliases = [
        'thinkpadx1carbongen9i5' => 'thinkpadx1.jpg',
        'lenovothinkpadx1carbongen9i5' => 'thinkpadx1.jpg',
        'razerblade14' => 'razer-blade15.jpg',
        'asustufgaminga16' => 'a15tuf-gaming.jpg',
        'asustufgaminga162024' => 'a15tuf-gaming.jpg',
        'lggram16' => 'LG Gram 16.jpg',
        'lggram162024' => 'LG Gram 16.jpg',
    ];

    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }

    if ($normalizedFiles === null) {
        $normalizedFiles = [];
        $dir = __DIR__ . '/../assets/images';
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $key = normalize_image_lookup_key(pathinfo($file, PATHINFO_FILENAME));
            if ($key !== '' && !isset($normalizedFiles[$key])) {
                $normalizedFiles[$key] = $file;
            }
        }
    }

    $basename = pathinfo(str_replace('\\', '/', $value), PATHINFO_FILENAME);
    $lookupKey = normalize_image_lookup_key($basename);
    if ($lookupKey === '') {
        return null;
    }

    if (isset($aliases[$lookupKey])) {
        return $aliases[$lookupKey];
    }

    if (isset($normalizedFiles[$lookupKey])) {
        return $normalizedFiles[$lookupKey];
    }

    foreach ($normalizedFiles as $key => $file) {
        if (strpos($key, $lookupKey) !== false || strpos($lookupKey, $key) !== false) {
            return $file;
        }
    }

    return null;
}

// Build a usable image URL 
function product_image_url($imageName) {
    $imageName = trim((string)$imageName);
    if ($imageName === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $imageName)) {
        return $imageName;
    }
    if ($imageName[0] === '/') {
        return url(ltrim($imageName, '/'));
    }
    if (stripos($imageName, 'uploads/') === 0) {
        return url($imageName);
    }
    $candidate = 'images/' . ltrim($imageName, '/');
    $path = __DIR__ . '/../assets/' . $candidate;
    if (file_exists($path)) {
        return asset($candidate);
    }

    $matchedFile = find_asset_image_filename($imageName);
    if ($matchedFile) {
        return asset('images/' . $matchedFile);
    }
    return null;
}

// Resolve a product image
function resolve_product_image($imageName, $productName = '') {
    $url = $imageName ? product_image_url($imageName) : null;

    if (!$url && $productName) {
        $known = [
            'asus rog flow z13' => 'rog-flow-z13.jpg',
            'alienware 18 area-51' => 'alienware-area51-18.jpg',
            'msi pulse gl66' => 'msi-pulse-gl66.jpg',
            'dell latitude 5320' => 'dell-latitude-5320.jpg',
            'hp elitebook 850 g5' => 'hp-elitebook-850-g5.jpg',
            'microsoft surface pro 9' => 'surface-pro-9.jpg',
            'thinkpad x1 carbon gen 9 i5' => 'thinkpadx1.jpg',
            'razer blade 14' => 'razer-blade15.jpg',
            'asus tuf gaming a16' => 'a15tuf-gaming.jpg',
            'lg gram 16' => 'LG Gram 16.jpg',
        ];
        $lcName = strtolower($productName);
        foreach ($known as $match => $file) {
            if (strpos($lcName, $match) === 0 || strpos($lcName, $match) !== false) {
                $candidate = "images/{$file}";
                $path = __DIR__ . '/../assets/' . $candidate;
                if (file_exists($path)) {
                    $url = asset($candidate);
                    break;
                }
            }
        }
    }

    if (!$url && $productName) {
        $matchedFile = find_asset_image_filename($productName);
        if ($matchedFile) {
            $url = asset('images/' . $matchedFile);
        }
    }

    if (!$url && $productName) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $productName), '-'));
        $candidate = "images/{$slug}.jpg";
        $path = __DIR__ . '/../assets/' . $candidate;
        if (file_exists($path)) {
            $url = asset($candidate);
        }
    }
    return $url ?: 'https://via.placeholder.com/600x450?text=Image';
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

function service_reviews_ensure_table() {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn = getDBConnection();
    $conn->exec("CREATE TABLE IF NOT EXISTS service_reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        rating INT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_service_reviews_user_id (user_id),
        CONSTRAINT fk_service_reviews_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $ensured = true;
}

function getServiceRatingSummary() {
    service_reviews_ensure_table();
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM service_reviews");
    $result = $stmt->fetch();

    return [
        'average' => round($result['avg_rating'] ?? 0, 1),
        'total' => (int)($result['total_reviews'] ?? 0)
    ];
}

function getRecentServiceReviews($limit = 3) {
    service_reviews_ensure_table();
    $limit = max(1, min(10, (int)$limit));
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT sr.*, u.name as user_name
                          FROM service_reviews sr
                          JOIN users u ON sr.user_id = u.id
                          ORDER BY sr.updated_at DESC
                          LIMIT " . $limit);
    return $stmt->fetchAll();
}

function stock_receipts_ensure_table() {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn = getDBConnection();
    $conn->exec("CREATE TABLE IF NOT EXISTS stock_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        admin_user_id INT NOT NULL,
        quantity INT NOT NULL,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_stock_receipts_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        CONSTRAINT fk_stock_receipts_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $ensured = true;
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

// Simple notification logger 
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

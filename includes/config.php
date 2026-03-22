<?php

require_once __DIR__ . '/error_handler.php';

// Illuminate DB
$USE_LARAVEL_DB = false;
$CAPSULE = null;
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        if (file_exists(__DIR__ . '/../bootstrap/eloquent.php')) {
            require_once __DIR__ . '/../bootstrap/eloquent.php';
            if (!empty($GLOBALS['capsule'])) {
                $CAPSULE = $GLOBALS['capsule'];
                $USE_LARAVEL_DB = true;
            }
        }
    } catch (Throwable $e) {
        app_log_error('Eloquent bootstrap failed: ' . $e->getMessage());
    }
}

// Fallback 
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'laptro_db');
if (!defined('ADMIN_SIGNUP_CODE')) define('ADMIN_SIGNUP_CODE', trim((string)(getenv('ADMIN_SIGNUP_CODE') ?: '')));

// Create database connection
function getDBConnection() {
    global $USE_LARAVEL_DB, $CAPSULE;
    if ($USE_LARAVEL_DB && $CAPSULE) {
        try {
            $pdo = $CAPSULE->getConnection()->getPdo();
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $pdo;
        } catch (Throwable $e) {
            app_log_error('Capsule PDO failed, falling back to native PDO: ' . $e->getMessage());
        }
    }
    // Fallback 
    try {
        $conn = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch (PDOException $e) {
        app_fatal('Database connection failed', $e, true);
    }
}

?>

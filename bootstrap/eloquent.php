<?php

// autoload
if (!class_exists(\Illuminate\Database\Capsule\Manager::class)) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }
}

if (class_exists(\Illuminate\Database\Capsule\Manager::class)) {
    try {
        if (class_exists(\Dotenv\Dotenv::class)) {
            $env = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
            $env->safeLoad();
        }
    } catch (\Throwable $e) {
        // ignore
    }

    $dbHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $dbPort = $_ENV['DB_PORT'] ?? '3306';
    $dbName = $_ENV['DB_DATABASE'] ?? 'laptro_db';
    $dbUser = $_ENV['DB_USERNAME'] ?? 'root';
    $dbPass = $_ENV['DB_PASSWORD'] ?? '';
    $capsule = new \Illuminate\Database\Capsule\Manager();
    $capsule->addConnection([
        'driver'    => 'mysql',
        'host'      => $dbHost,
        'port'      => $dbPort,
        'database'  => $dbName,
        'username'  => $dbUser,
        'password'  => $dbPass,
        'charset'   => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix'    => '',
    ]);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $GLOBALS['capsule'] = $capsule;

    if (!class_exists('DB')) {
        class_alias(\Illuminate\Database\Capsule\Manager::class, 'DB');
    }
    if (!function_exists('db')) {
        function db() {
            return $GLOBALS['capsule'] ?? null;
        }
    }
}

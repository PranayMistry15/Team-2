<?php

// BASE_URL is dynamic dude 
if (!defined('BASE_URL')) {
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;
    $projectRoot = realpath(__DIR__ . '/..');

    $basePath = '';
    if ($docRoot && $projectRoot && strpos($projectRoot, $docRoot) === 0) {
        $basePath = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
    }
    if ($basePath === false) {
        $basePath = '';
    }
    if ($basePath !== '' && $basePath[0] !== '/') {
        $basePath = '/' . $basePath;
    }

    $baseUrl = $scheme . '://' . $host . rtrim($basePath, '/');
    define('BASE_URL', $baseUrl);
}

function url($path = '') {
    $path = ltrim($path, '/');
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim(BASE_URL, '/') . ($path !== '' ? '/' . $path : '');
}

function asset($path = '') {
    $path = ltrim($path, '/');
    return rtrim(BASE_URL, '/') . '/assets/' . $path;
}

function current_url() {
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $uri;
}

function is_current_page($path) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return $currentPage === $path;
}

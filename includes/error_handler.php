<?php

// Error Handling
if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', false);
}

function app_log_error($message) {
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . "] " . $message . "\n";
    @file_put_contents($dir . '/error.log', $line, FILE_APPEND);
}

function app_render_error($code = 500, $details = null, $safeMinimal = false) {
    http_response_code($code);
    $title = ($code === 404) ? 'Page Not Found' : 'Something went wrong';
    $message = ($code === 404)
        ? 'The page you are looking for could not be found.'
        : 'We hit a snag processing your request.';
    if (APP_DEBUG && $details) {
        $message .= '<br><small style="color:#666">' . htmlspecialchars($details) . '</small>';
    }

    if (!$safeMinimal) {
        $ok = false;
        ob_start();
        try {
            $err_title = $title; $err_message = $message; $err_code = $code;
            @require __DIR__ . '/error_view.php';
            $ok = true;
        } catch (Throwable $t) { $ok = false; }
        $out = ob_get_clean();
        if ($ok) { echo $out; return; }
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . $title . '</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font-family:Arial,sans-serif;padding:40px;background:#f7f7f7;color:#222} .card{max-width:720px;margin:40px auto;background:#fff;border:1px solid #e5e5e5;border-radius:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05)} .card h1{margin:0;padding:24px 24px 0;font-size:24px} .card p{padding:16px 24px 24px;margin:0;line-height:1.6} .actions{padding:0 24px 24px} .btn{display:inline-block;padding:10px 16px;border:1px solid #000;border-radius:6px;text-decoration:none;color:#000} </style></head><body>';
    echo '<div class="card"><h1>' . $title . '</h1><p>' . $message . '</p><div class="actions"><a class="btn" href="/laptro-ecommerce/index.php">Go Home</a></div></div>';
    echo '</body></html>';
}

function app_fatal($message, $exception = null, $dbDown = false) {
    if ($exception) {
        app_log_error($message . ' | ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());
    } else {
        app_log_error($message);
    }
    app_render_error(500, $exception ? $exception->getMessage() : $message, $dbDown);
    exit();
}

set_exception_handler(function ($ex) {
    app_fatal('Unhandled exception', $ex);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    app_log_error("PHP error [$severity] $message at $file:$line");
    if (!APP_DEBUG) {
        app_render_error(500, $message);
        return true;
    }
    return false;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        app_log_error('Fatal: ' . $err['message'] . ' at ' . $err['file'] . ':' . $err['line']);
        if (!headers_sent()) {
            app_render_error(500, $err['message']);
        }
    }
});

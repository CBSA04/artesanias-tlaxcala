<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $path = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) require $path;
    }
});
if (is_file(ROOT . '/.env')) {
    foreach (file(ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        if (getenv(trim($key)) === false) putenv(trim($key) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}
date_default_timezone_set('America/Mexico_City');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php.log');
require __DIR__ . '/helpers.php';
if (PHP_SAPI !== 'cli' || defined('START_SESSION')) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('artesanias_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => base_path() ?: '/', 'httponly' => true, 'secure' => env('APP_ENV') === 'production', 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
}

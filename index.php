<?php
declare(strict_types=1);

/**
 * All The Venues — front controller.
 *
 * Responsibilities in U0:
 *   1. Start a hardened session (HttpOnly, Secure on HTTPS, SameSite=Lax).
 *   2. Dispatch on the request path via a tiny router:
 *        /        → views/home.php
 *        unknown  → views/404.php (HTTP 404)
 *
 * All non-file requests are routed here by .htaccess. Business features
 * (venues, leads, admin) arrive in later units and register here.
 */

require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/csrf.php';

/* ---- Hardened session ---------------------------------------------------- */

$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 0,
        'cookie_path'     => '/',
        'cookie_secure'   => $is_https,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/* ---- Router -------------------------------------------------------------- */

// Path only, no query string; collapse trailing slash (except root).
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

$routes = [
    '/' => __DIR__ . '/views/home.php',
];

if (isset($routes[$path])) {
    require $routes[$path];
    exit;
}

http_response_code(404);
require __DIR__ . '/views/404.php';
exit;

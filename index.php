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

// App-owned session save path. The host default
// (/var/cpanel/php/sessions/ea-phpXX) may not exist or be writable, which
// makes session_start() fail and cascades into a fatal in csrf_token(),
// truncating the page. Keeping our own dir under storage/ is portable.
$sess_path = __DIR__ . '/storage/sessions';
if (!is_dir($sess_path)) {
    @mkdir($sess_path, 0700, true);
}
if (is_dir($sess_path) && is_writable($sess_path)) {
    session_save_path($sess_path);
}

if (session_status() === PHP_SESSION_NONE) {
    // secure follows $is_https: true on staging/prod (HTTPS-only), false on
    // local HTTP dev so the session cookie is still sent.
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

/* ---- Router -------------------------------------------------------------- */

// Path only, no query string; collapse trailing slash (except root).
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// Static (exact-match) routes.
$routes = [
    '/'        => __DIR__ . '/views/home.php',
    '/venues'  => __DIR__ . '/views/venues.php',
    '/enquire' => __DIR__ . '/views/enquire.php',
];

if (isset($routes[$path])) {
    require $routes[$path];
    exit;
}

// Dynamic route: /venues/{slug}  (slug is a-z0-9-, validated here).
if (preg_match('#^/venues/([a-z0-9-]+)$#', $path, $m)) {
    $slug = $m[1];
    require __DIR__ . '/views/venue.php';
    exit;
}

// Admin area: /admin and /admin/* → the admin controller ($adminSub = the
// path after /admin). Login is public there; everything else fails closed.
if (preg_match('#^/admin(?:/(.*))?$#', $path, $m)) {
    $adminSub = $m[1] ?? '';
    require __DIR__ . '/views/admin/dispatch.php';
    exit;
}

http_response_code(404);
require __DIR__ . '/views/404.php';
exit;

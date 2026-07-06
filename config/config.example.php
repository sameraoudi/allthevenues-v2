<?php
declare(strict_types=1);

/**
 * Template for config/config.php.
 *
 * Copy this file to config/config.php on each environment and fill in
 * real values. config/config.php is gitignored and never deployed —
 * create it once on the server.
 */

// --- Database (MySQL) -----------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'sameraou_atv2');
define('DB_USER', 'sameraou_atv2');
define('DB_PASS', 'REPLACE_ME');
// define('DB_PORT', 3306);   // optional — only needed for a non-default port

// --- Site -----------------------------------------------------------------
// Base URL, no trailing slash.
define('BASE_URL', 'https://staging.allthevenues.com');

// #3 Provider portal (dark-launch). Leave false until the portal is ready to go
// live (U-P9). While false/undefined, /portal and /portal/* return the branded 404.
define('PORTAL_ENABLED', false);

// --- Cloudflare Turnstile (enquiry anti-bot) ------------------------------
// Dev: Cloudflare's test keys below always pass on any domain. When the
// secret starts with "1x" (test key), server-side verify auto-passes with
// no network call. Staging/prod: real keys from the Cloudflare dashboard.
define('TURNSTILE_SITE_KEY',   '1x00000000000000000000AA');
define('TURNSTILE_SECRET_KEY', '1x0000000000000000000000000000000AA');

// --- Mail -----------------------------------------------------------------
// Transport: 'mail' (PHP mail(), cPanel default) | 'smtp' | 'log' (dev — writes
// .eml files to storage/mail/, sends nothing).
define('MAIL_TRANSPORT', 'mail');
define('MAIL_FROM',      'no-reply@allthevenues.com');
define('MAIL_FROM_NAME', 'All The Venues');
// Where new-enquiry notifications go (comma-separated for multiple).
define('MAIL_ADMIN_RECIPIENTS', 'leads@allthevenues.com');
// SMTP settings (only used when MAIL_TRANSPORT = 'smtp').
define('SMTP_HOST',   '');
define('SMTP_PORT',   587);
define('SMTP_USER',   '');
define('SMTP_PASS',   '');
define('SMTP_SECURE', 'tls');   // 'tls' | 'ssl' | ''

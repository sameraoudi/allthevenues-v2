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

// --- Site -----------------------------------------------------------------
// Base URL, no trailing slash.
define('BASE_URL', 'https://staging.allthevenues.com');

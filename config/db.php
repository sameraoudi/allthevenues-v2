<?php
declare(strict_types=1);

/**
 * PDO factory for All The Venues.
 *
 * Reads DB credentials + BASE_URL from config/config.php (gitignored,
 * environment-specific). Exposes db_pdo() returning a hardened PDO:
 *   - ERRMODE_EXCEPTION      (errors throw, never silently ignored)
 *   - EMULATE_PREPARES false (true prepared statements)
 *   - utf8mb4 charset
 *
 * Usage:
 *   require_once __DIR__ . '/../config/db.php';
 *   $pdo = db_pdo();
 *
 * The connection is not exercised in U0 (no queries yet); U1 adds tables.
 */

require_once __DIR__ . '/config.php';

function db_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $const) {
        if (!defined($const)) {
            throw new RuntimeException(
                "Database config missing constant $const. "
                . 'Copy config/config.example.php to config/config.php and fill it in.'
            );
        }
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

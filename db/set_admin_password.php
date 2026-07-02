<?php
declare(strict_types=1);

// CLI only — this sets an admin password; never expose it over HTTP.
// db/ is also excluded from deploy + web-denied (defence in depth).
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * Set an admin user's password.
 *
 *   php db/set_admin_password.php <email> <new-password>
 *
 * Sets users.password_hash = password_hash($pw, PASSWORD_DEFAULT) for the
 * user with role='admin' and the given email. The migrated admins have
 * unusable passwords (U1b), so run this once per admin to enable login.
 *
 * DB creds come from config/config.php (via config/db.php). For local runs
 * you can override with ATV_NEW_DB_* env vars (host/port/name/user/pass).
 */

const MIN_PASSWORD_LEN = 10;

$email = $argv[1] ?? '';
$pw    = $argv[2] ?? '';

if ($email === '' || $pw === '') {
    fwrite(STDERR, "Usage: php db/set_admin_password.php <email> <new-password>\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}
if (strlen($pw) < MIN_PASSWORD_LEN) {
    fwrite(STDERR, "Password too short (min " . MIN_PASSWORD_LEN . " characters).\n");
    exit(1);
}

/* ---- Connect (env override for local testing, else config/config.php) ---- */
try {
    if (getenv('ATV_NEW_DB_HOST')) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            getenv('ATV_NEW_DB_HOST'),
            (int)(getenv('ATV_NEW_DB_PORT') ?: 3306),
            getenv('ATV_NEW_DB_NAME') ?: 'sameraou_atv2'
        );
        $pdo = new PDO($dsn, getenv('ATV_NEW_DB_USER') ?: 'root', getenv('ATV_NEW_DB_PASS') ?: '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } else {
        require_once __DIR__ . '/../config/db.php';
        $pdo = db_pdo();
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

/* ---- Look up the admin ---------------------------------------------------- */
$stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = :email AND role = 'admin' LIMIT 1");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();
if (!$user) {
    fwrite(STDERR, "No admin user found with email: $email\n");
    exit(1);
}

/* ---- Set the password ----------------------------------------------------- */
$hash = password_hash($pw, PASSWORD_DEFAULT);
$upd  = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
$upd->execute([':h' => $hash, ':id' => (int)$user['id']]);

echo "Password set for admin #{$user['id']} <{$user['email']}> (status: {$user['status']}).\n";
if ($user['status'] !== 'active') {
    echo "NOTE: this account's status is '{$user['status']}' — set it to 'active' to allow login.\n";
}

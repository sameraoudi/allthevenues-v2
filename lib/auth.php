<?php
declare(strict_types=1);

/**
 * Admin authentication for All The Venues.
 *
 * Session + role gate, fail-CLOSED. Sessions are started by index.php
 * (app-owned save path under storage/sessions). Passwords are bcrypt
 * (password_hash / password_verify).
 *
 * Login is admin-only: only users with role='admin' and status='active'
 * can authenticate here. Failures are generic (no user enumeration).
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ratelimit.php';
require_once __DIR__ . '/../config/db.php';

/** Establish an authenticated session for a verified user row. */
function auth_login(array $user): void
{
    // New session id at the auth boundary (prevents fixation).
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['role']     = (string)$user['role'];
    $_SESSION['name']     = (string)$user['name'];
    $_SESSION['login_at'] = time();
}

/** Is there an authenticated user in this session? */
function auth_is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** Display-layer check: is the current session an admin? */
function auth_is_admin(): bool
{
    return auth_is_logged_in() && (($_SESSION['role'] ?? '') === 'admin');
}

/** Current session user (for chrome display). */
function auth_current_user(): array
{
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['name'] ?? '',
        'role' => $_SESSION['role'] ?? '',
    ];
}

/**
 * Canonical gate for every /admin route. Fails CLOSED: not logged in OR
 * not an admin → redirect to /admin/login (never reveals the page).
 */
function auth_require_admin(): void
{
    if (!auth_is_admin()) {
        redirect('admin/login');
    }
}

/** Full teardown: clear session data, cookie, and destroy the session. */
function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/**
 * Verify admin credentials. Returns the user row on success, null otherwise.
 * Generic failure for every mismatch (unknown email, bad password, non-admin,
 * disabled) — no enumeration. On success, stamps last_login_at.
 */
function auth_login_attempt(PDO $pdo, string $email, string $password): ?array
{
    $email = trim($email);
    if ($email === '' || $password === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role, status
         FROM users WHERE email = :email LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // Always run password_verify (even on miss) to keep timing uniform.
    $hash = is_array($user) ? (string)$user['password_hash'] : '$2y$10$'
        . str_repeat('.', 53);
    $ok = password_verify($password, $hash);

    if (!$ok || !is_array($user)
        || $user['role'] !== 'admin' || $user['status'] !== 'active') {
        return null;
    }

    $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $upd->execute([':id' => (int)$user['id']]);

    return $user;
}

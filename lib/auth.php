<?php
declare(strict_types=1);

/**
 * Admin authentication + role model (RBAC) for All The Venues.
 *
 * Session + role gates, fail-CLOSED. Sessions are started by index.php
 * (app-owned save path under storage/sessions). Passwords are bcrypt
 * (password_hash / password_verify).
 *
 * The admin area is staff-only: only users with role IN ('admin','editor')
 * and status='active' can authenticate. role='partner' is reserved for the
 * future partner portal and has NO admin access. Failures are generic (no
 * user enumeration).
 *
 * CAPABILITY MATRIX (see auth_capability_roles()):
 *   Administrator (role=admin) — full access: venues, providers, enquiries,
 *     USERS/ROLES, availability/settings, and future monetization.
 *   Editor / Assistant (role=editor) — venues + provider content + enquiries
 *     (view/manage/forward). NOT: user/role management, settings/availability,
 *     or any destructive config.
 *   Partner (role=partner) — reserved for the future portal; treated here as
 *     NOT an admin user (no capabilities).
 *
 * The current user is loaded once per request from the DB (auth_user()), so
 * a mid-session demotion/disable takes effect immediately (fail closed).
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

/**
 * The current user row (id, name, email, role, status), loaded ONCE per
 * request from the DB and cached. Returns null when there is no active staff
 * session — not logged in, the row vanished, or status is not 'active'.
 * @return array{id:int,name:string,email:string,role:string,status:string}|null
 */
function auth_user(): ?array
{
    static $loaded = false;
    static $user   = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;

    $id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($id <= 0) {
        return $user = null;
    }
    try {
        $stmt = db_pdo()->prepare(
            'SELECT id, name, email, role, status FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
    } catch (Throwable $e) {
        error_log('auth_user load failed: ' . $e->getMessage());
        return $user = null;
    }
    if (!is_array($row) || ($row['status'] ?? '') !== 'active') {
        return $user = null;   // fail closed on disabled / missing
    }
    $row['id'] = (int)$row['id'];
    return $user = $row;
}

/** Current user's role ('' when not authenticated). */
function auth_role(): string
{
    $u = auth_user();
    return $u ? (string)$u['role'] : '';
}

/** Is there an authenticated staff session? */
function auth_is_logged_in(): bool
{
    return auth_user() !== null;
}

/** Is the current user an administrator? */
function auth_is_admin(): bool
{
    return auth_role() === 'admin';
}

/** Human role label for the UI. */
function auth_role_label(string $role): string
{
    return [
        'admin'   => 'Administrator',
        'editor'  => 'Editor / Assistant',
        'partner' => 'Partner',
    ][$role] ?? ($role !== '' ? ucfirst($role) : '');
}

/**
 * Capability → allowed roles. The single source of truth for what each role
 * may do. Add capabilities here as new admin surfaces land.
 * @return array<string,string[]>
 */
function auth_capability_roles(): array
{
    return [
        'venues.manage'       => ['admin', 'editor'],
        'providers.manage'    => ['admin', 'editor'],
        'enquiries.manage'    => ['admin', 'editor'],
        'users.manage'        => ['admin'],
        'settings.manage'     => ['admin'],
        'monetization.manage' => ['admin'],
    ];
}

/** Does the current user hold $capability? */
function auth_can(string $capability): bool
{
    $role = auth_role();
    if ($role === '') {
        return false;
    }
    return in_array($role, auth_capability_roles()[$capability] ?? [], true);
}

/** Current user (for chrome display). Sourced from the DB-backed auth_user(). */
function auth_current_user(): array
{
    $u = auth_user();
    return [
        'id'    => $u['id']    ?? null,
        'name'  => $u['name']  ?? '',
        'email' => $u['email'] ?? '',
        'role'  => $u['role']  ?? '',
    ];
}

/**
 * Canonical gate for every /admin route. Fails CLOSED: not a staff session →
 * redirect to /admin/login (never reveals the page).
 */
function auth_require_admin(): void
{
    if (!auth_is_logged_in()) {
        redirect('admin/login');
    }
}

/**
 * Role gate. Fails CLOSED:
 *   - no staff session       → redirect to /admin/login.
 *   - session lacks a role   → HTTP 403 (rendered in the admin shell).
 * Use e.g. auth_require_role(['admin']) or auth_require_role(['admin','editor']).
 */
function auth_require_role(array $roles): void
{
    $u = auth_user();
    if ($u === null) {
        redirect('admin/login');
    }
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        // Render the 403 inside the admin shell (locals become layout scope).
        $page_title         = 'Not authorized — Admin';
        $admin_page_title   = 'Not authorized';
        $admin_active       = '';
        $admin_forbidden    = true;
        $admin_content_view = __DIR__ . '/../views/admin/forbidden.php';
        require __DIR__ . '/../views/admin/layout.php';
        exit;
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
 * Verify staff credentials. Returns the user row on success, null otherwise.
 * Generic failure for every mismatch (unknown email, bad password, non-staff
 * role, disabled) — no enumeration. On success, stamps last_login_at.
 * Staff = role IN ('admin','editor'); role='partner' is denied here.
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
        || !in_array($user['role'], ['admin', 'editor'], true)
        || $user['status'] !== 'active') {
        return null;
    }

    $upd = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
    $upd->execute([':id' => (int)$user['id']]);

    return $user;
}

<?php
declare(strict_types=1);

/**
 * Admin user & role management data layer (U4). Admin-only surface — the
 * controller gates with auth_require_role(['admin']). Prepared statements
 * throughout. NEVER selects password_hash for display.
 *
 * Roles assignable from the admin UI are 'admin' and 'editor' only; 'partner'
 * is reserved for the future portal and is not created/assigned here (no schema
 * change — the enum already has it).
 */

require_once __DIR__ . '/../config/db.php';

/** Assignable roles → label. #3 U-P9a adds Venue Provider (portal accounts). */
function user_admin_roles(): array
{
    return ['admin' => 'Administrator', 'editor' => 'Editor / Assistant', 'partner' => 'Venue Provider'];
}

/** Approved providers (id, org_name) for the partner-account selector. */
function user_admin_provider_options(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, org_name FROM partners WHERE status = 'approved' ORDER BY org_name ASC"
    )->fetchAll();
}

/** Is $partnerId an approved provider? (partner-account guard) */
function user_admin_provider_is_approved(PDO $pdo, int $partnerId): bool
{
    if ($partnerId <= 0) { return false; }
    $stmt = $pdo->prepare("SELECT 1 FROM partners WHERE id = :id AND status = 'approved' LIMIT 1");
    $stmt->execute([':id' => $partnerId]);
    return $stmt->fetchColumn() !== false;
}

/** Account statuses → label. */
function user_admin_statuses(): array
{
    return ['active' => 'Active', 'disabled' => 'Disabled'];
}

/** All users for the list view (no password hash). */
function user_admin_list(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name, email, role, status, last_login_at, created_at
         FROM users
         ORDER BY (status = \'active\') DESC, name ASC'
    )->fetchAll();
}

/** One user by id (no password hash), or null. */
function user_admin_get(PDO $pdo, int $id): ?array
{
    // password_hash is selected only to derive a "set / not set" status — the
    // view never renders it (see password_status_for_user()).
    $stmt = $pdo->prepare(
        'SELECT id, name, email, role, status, last_login_at, created_at, partner_id, password_hash
         FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Is $email free (optionally excluding one user id)? */
function user_email_available(PDO $pdo, string $email, int $exceptId = 0): bool
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $stmt->execute([':email' => $email, ':id' => $exceptId]);
    return $stmt->fetchColumn() === false;
}

/** Count of active administrators, optionally excluding one user id. */
function user_count_active_admins(PDO $pdo, int $exceptId = 0): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> :id"
    );
    $stmt->execute([':id' => $exceptId]);
    return (int)$stmt->fetchColumn();
}

/** Active admin+editor staff with an email → [['name','email','role'], …]. */
function user_active_staff(PDO $pdo): array
{
    return $pdo->query(
        "SELECT name, email, role FROM users
          WHERE role IN ('admin','editor') AND status = 'active'
            AND email IS NOT NULL AND email <> ''
          ORDER BY name ASC"
    )->fetchAll();
}

/**
 * Generate a readable temporary password (>= 10 chars). Avoids ambiguous
 * characters. Uses random_int (CSPRNG).
 */
function user_generate_temp_password(int $len = 14): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;
    $out = '';
    for ($i = 0; $i < max(10, $len); $i++) {
        $out .= $alphabet[random_int(0, $max)];
    }
    return $out;
}

<?php
declare(strict_types=1);

/**
 * PU-A2 — Partner portal Account: read-only profile (admin-managed) + in-portal
 * change-password. Reached via dispatch (/portal/account), already
 * auth_require_partner-gated. The owner is ALWAYS the logged-in user (auth_user id)
 * — never a user id from the client. Change-password verifies the CURRENT password,
 * then applies the shared PU-B policy (password_policy_error) before updating.
 * CSRF + rate-limited. Expects in scope: $pdo.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ratelimit.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/password_token.php';   // password_policy_error()

$me     = auth_user();
$userId = (int)($me['id'] ?? 0);

// Load the LOGGED-IN user's profile + current hash (owner-scoped by id).
$row = null;
try {
    $s = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.password_hash, u.partner_id, p.org_name AS provider_name
         FROM users u LEFT JOIN partners p ON p.id = u.partner_id WHERE u.id = :id LIMIT 1'
    );
    $s->execute([':id' => $userId]);
    $row = $s->fetch() ?: null;
} catch (Throwable $e) {
    error_log('portal account load failed (user=' . $userId . '): ' . $e->getMessage());
}

$orgName = (string)($row['provider_name'] ?? '');
$errors  = [];
$flash   = $_SESSION['portal_flash'] ?? null;
unset($_SESSION['portal_flash']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $cur  = (string)($_POST['current_password'] ?? '');
    $new  = (string)($_POST['new_password'] ?? '');
    $conf = (string)($_POST['confirm_password'] ?? '');

    if (!csrf_validate()) {
        $errors['_form'] = 'Your session expired. Please try again.';
    } elseif (!ratelimit_hit('portal_pwchange_' . $userId, (string)$userId, 8, 900)
           || !ratelimit_hit('portal_pwchange_ip', client_ip(), 20, 900)) {
        $errors['_form'] = 'Too many attempts. Please try again in a few minutes.';
    } elseif ($row === null || !password_verify($cur, (string)($row['password_hash'] ?? ''))) {
        $errors['current_password'] = 'Current password is incorrect.';
    } elseif (($pe = password_policy_error($new, $conf, [
                'email'         => (string)($row['email'] ?? ''),
                'name'          => (string)($row['name'] ?? ''),
                'provider_name' => $orgName,
              ])) !== null) {
        $errors['new_password'] = $pe;
    } else {
        try {
            // Owner-scoped update — id is the session user, never client input.
            $upd = $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
            $upd->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $userId]);
            audit_log($pdo, $userId, 'password_change', 'user', $userId, null, ['via' => 'portal']);
            $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Your password has been updated.'];
            redirect('portal/account');
        } catch (Throwable $e) {
            error_log('portal password change failed (user=' . $userId . '): ' . $e->getMessage());
            $errors['_form'] = 'Something went wrong updating your password. Please try again.';
        }
    }
}

$page_title          = 'Account — Partner Portal';
$portal_active       = 'account';
$portal_content_view = __DIR__ . '/account-content.php';
require __DIR__ . '/layout.php';

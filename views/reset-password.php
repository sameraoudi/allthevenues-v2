<?php
declare(strict_types=1);

/**
 * PU-B — Public password RESET (consumes a purpose='reset' token). Mirrors
 * /set-password: no auth (the token is the credential), CSRF + rate-limit +
 * Turnstile + the shared password policy, generic errors, single-use token,
 * race-safe re-check at commit. On success signs the user in and redirects BY
 * ROLE (partner → portal, staff → admin). Noindex.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/ratelimit.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/audit.php';
require_once __DIR__ . '/../lib/password_token.php';

$pdo    = db_pdo();
$robots = 'noindex, nofollow';

$loadUser = static function (PDO $pdo, int $userId): ?array {
    $s = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.role, u.status, u.partner_id, p.org_name AS provider_name
         FROM users u LEFT JOIN partners p ON p.id = u.partner_id WHERE u.id = :id LIMIT 1'
    );
    $s->execute([':id' => $userId]);
    $r = $s->fetch();
    return $r === false ? null : $r;
};

$rpMode  = 'invalid';   // form | expired | used | invalid
$rpUser  = null;
$rpError = null;
$rpToken = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

$look = pt_lookup($pdo, $rpToken, 'reset');
if ($look['state'] !== 'valid') {
    $rpMode = $look['state'];   // expired | used | invalid
} else {
    $rpUser = $loadUser($pdo, (int)$look['row']['user_id']);
    if ($rpUser === null) {
        $rpMode = 'invalid';
    } else {
        $rpMode = 'form';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $pw  = (string)($_POST['password'] ?? '');
            $pw2 = (string)($_POST['password_confirm'] ?? '');

            if (!csrf_validate()) {
                $rpError = 'Your session expired. Please try again.';
            } elseif (!ratelimit_hit('pwreset_set_ip', client_ip(), 20, 900)) {
                $rpError = 'Too many attempts. Please try again in a few minutes.';
            } elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
                $rpError = 'Please complete the verification and try again.';
            } elseif (($rpError = password_policy_error($pw, $pw2, $rpUser)) !== null) {
                // shared policy (PU-B)
            } else {
                // Re-check the token is still valid at commit time (race-safe).
                $recheck = pt_lookup($pdo, $rpToken, 'reset');
                if ($recheck['state'] !== 'valid') {
                    $rpMode = $recheck['state'];
                } else {
                    try {
                        $pdo->beginTransaction();
                        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                            ->execute([':h' => password_hash($pw, PASSWORD_DEFAULT), ':id' => (int)$rpUser['id']]);
                        if (!pt_consume($pdo, (int)$recheck['row']['id'])) {
                            throw new RuntimeException('token already consumed');
                        }
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) { $pdo->rollBack(); }
                        error_log('reset-password commit failed (user=' . (int)$rpUser['id'] . '): ' . $e->getMessage());
                        $rpError = 'Something went wrong. Please try again.';
                    }
                    if ($rpError === null) {
                        session_regenerate_id(true);
                        audit_log($pdo, (int)$rpUser['id'], 'password_reset', 'user', (int)$rpUser['id'], null, ['via' => 'reset']);
                        auth_login($rpUser);   // regenerates again + sets the session user
                        redirect((string)$rpUser['role'] === 'partner' ? 'portal' : 'admin');
                    }
                }
            }
        }
    }
}

$page_title   = 'Reset your password — All The Venues';
$content_view = __DIR__ . '/reset-password-content.php';
require __DIR__ . '/layout.php';

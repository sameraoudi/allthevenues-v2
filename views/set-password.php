<?php
declare(strict_types=1);

/**
 * #3 U-P9a — Public set-password page (invite token flow). No auth: the one-time
 * token IS the credential. CSRF + rate-limit + Turnstile on POST; generic errors
 * that never reveal token internals or whether an email exists. On success the
 * user's password is set, the token consumed, and they're signed in. Handles
 * /set-password (GET form / POST submit) and /set-password/request (new link).
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
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isRequest = ($path === '/set-password/request');

// Common-password denylist (small; the ≥10 rule + identity checks do the rest).
$SP_DENY = ['password', 'password1', 'password123', 'passw0rd', '1234567890', '12345678',
            'qwertyuiop', 'qwerty123', 'letmein123', 'iloveyou1', 'welcome123', 'admin1234',
            'allthevenues', 'changeme123'];

/** Load the token's user (public-safe fields + provider name), or null. */
$loadUser = static function (PDO $pdo, int $userId): ?array {
    $s = $pdo->prepare(
        'SELECT u.id, u.name, u.email, u.role, u.status, u.partner_id, p.org_name AS provider_name
         FROM users u LEFT JOIN partners p ON p.id = u.partner_id WHERE u.id = :id LIMIT 1'
    );
    $s->execute([':id' => $userId]);
    $r = $s->fetch();
    return $r === false ? null : $r;
};

$spMode   = 'invalid';   // form | expired | used | invalid | request | requested
$spUser   = null;
$spError  = null;
$spToken  = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));

/* ============================ REQUEST A NEW LINK ======================== */
if ($isRequest) {
    $spMode = 'request';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $email = trim((string)($_POST['email'] ?? ''));
        $okForm = csrf_validate()
            && ratelimit_hit('set_password_request_ip', client_ip(), 10, 3600)
            && ratelimit_hit('set_password_request_email', $email !== '' ? strtolower($email) : 'none', 3, 3600);
        // ALWAYS the same response — no account enumeration.
        if ($okForm && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $s = $pdo->prepare("SELECT id, name, email, partner_id, password_hash FROM users
                                WHERE email = :e AND role = 'partner' LIMIT 1");
            $s->execute([':e' => $email]);
            $u = $s->fetch();
            if ($u !== false && password_status_for_user($u) === 'not_set') {
                try {
                    $providerName = '';
                    if (!empty($u['partner_id'])) {
                        $ps = $pdo->prepare('SELECT org_name FROM partners WHERE id = :id');
                        $ps->execute([':id' => (int)$u['partner_id']]);
                        $providerName = (string)($ps->fetchColumn() ?: '');
                    }
                    $tok = pt_create($pdo, (int)$u['id'], 'invite', null, (string)$u['email']);
                    send_invite_email($u, $tok['raw'], $providerName);
                } catch (Throwable $e) {
                    error_log('set-password request failed: ' . $e->getMessage());
                }
            }
        }
        $spMode = 'requested';
    }
    $page_title   = 'Request a link — All The Venues';
    $content_view = __DIR__ . '/set-password-content.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ SET PASSWORD ============================== */
$look = pt_lookup($pdo, $spToken, 'invite');
if ($look['state'] !== 'valid') {
    $spMode = $look['state'];   // expired | used | invalid
} else {
    $spUser = $loadUser($pdo, (int)$look['row']['user_id']);
    if ($spUser === null) {
        $spMode = 'invalid';
    } else {
        $spMode = 'form';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $pw  = (string)($_POST['password'] ?? '');
            $pw2 = (string)($_POST['password_confirm'] ?? '');

            if (!csrf_validate()) {
                $spError = 'Your session expired. Please try again.';
            } elseif (!ratelimit_hit('set_password_ip', client_ip(), 20, 900)) {
                $spError = 'Too many attempts. Please try again in a few minutes.';
            } elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
                $spError = 'Please complete the “I’m human” check and try again.';
            } elseif (mb_strlen($pw) < 10) {
                $spError = 'Use at least 10 characters.';
            } elseif ($pw !== $pw2) {
                $spError = 'The two passwords don’t match.';
            } else {
                $lc = mb_strtolower($pw);
                $identity = array_filter([
                    mb_strtolower((string)$spUser['email']),
                    mb_strtolower((string)$spUser['name']),
                    mb_strtolower((string)($spUser['provider_name'] ?? '')),
                ]);
                if (in_array($lc, array_map('mb_strtolower', $identity), true) || in_array($lc, $SP_DENY, true)) {
                    $spError = 'Please choose a stronger password (not your email, name, or a common password).';
                } else {
                    // Re-check the token is still valid at commit time (race-safe).
                    $recheck = pt_lookup($pdo, $spToken, 'invite');
                    if ($recheck['state'] !== 'valid') {
                        $spMode = $recheck['state'];
                    } else {
                        try {
                            $pdo->beginTransaction();
                            $pdo->prepare('UPDATE users SET password_hash = :h, status = :s WHERE id = :id')
                                ->execute([':h' => password_hash($pw, PASSWORD_DEFAULT), ':s' => 'active', ':id' => (int)$spUser['id']]);
                            if (!pt_consume($pdo, (int)$recheck['row']['id'])) {
                                throw new RuntimeException('token already consumed');
                            }
                            $pdo->commit();
                        } catch (Throwable $e) {
                            if ($pdo->inTransaction()) { $pdo->rollBack(); }
                            error_log('set-password commit failed (user=' . (int)$spUser['id'] . '): ' . $e->getMessage());
                            $spError = 'Something went wrong. Please try again.';
                        }
                        if ($spError === null) {
                            audit_log($pdo, (int)$spUser['id'], 'password_set', 'user', (int)$spUser['id'], null, ['via' => 'invite']);
                            auth_login($spUser);   // regenerates the session id
                            require_once __DIR__ . '/../config/config.php';
                            redirect(portal_enabled() ? 'portal' : 'portal/login');
                        }
                    }
                }
            }
        }
    }
}

$page_title   = 'Set your password — All The Venues';
$content_view = __DIR__ . '/set-password-content.php';
require __DIR__ . '/layout.php';

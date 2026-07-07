<?php
declare(strict_types=1);

/**
 * PU-B — Public "forgot password": request a reset link. No auth. CSRF +
 * rate-limit + Turnstile. ALWAYS the same confirmation whether or not the email
 * exists (no enumeration); the token+email work happens only for a real ACTIVE
 * user. Reuses the U-P9a token infra with purpose='reset'. Noindex.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/ratelimit.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/password_token.php';

$pdo    = db_pdo();
$robots = 'noindex, nofollow';

$fpMode  = 'request';   // request | requested
$fpError = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string)($_POST['email'] ?? ''));

    if (!csrf_validate()) {
        $fpError = 'Your session expired. Please try again.';
    } elseif (!ratelimit_hit('pwreset_ip', client_ip(), 5, 900)
           || !ratelimit_hit('pwreset_email', $email !== '' ? mb_strtolower($email) : 'none', 3, 900)) {
        $fpError = 'Too many attempts. Please try again in a few minutes.';
    } elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
        $fpError = 'Please complete the verification and try again.';
    } else {
        // Do the token + email work only for a real ACTIVE user — but NEVER
        // branch the response (same confirmation shown either way).
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $s = $pdo->prepare("SELECT id, name, email FROM users WHERE email = :e AND status = 'active' LIMIT 1");
            $s->execute([':e' => $email]);
            $u = $s->fetch();
            if ($u !== false) {
                try {
                    $tok = pt_create($pdo, (int)$u['id'], 'reset', null, (string)$u['email']);
                    send_reset_email($u, $tok['raw']);
                } catch (Throwable $e) {
                    error_log('forgot-password request failed: ' . $e->getMessage());
                }
            }
        }
        $fpMode = 'requested';
    }
}

$page_title   = 'Reset your password — All The Venues';
$content_view = __DIR__ . '/forgot-password-content.php';
require __DIR__ . '/layout.php';

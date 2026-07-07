<?php
declare(strict_types=1);

/**
 * Delist-1 — Provider portal: RE-LIST a delisted venue (POST-only, SELF-SERVE).
 * Reached via dispatch (/portal/venues/{id}/relist), already auth_require_partner-
 * gated. No admin approval — the venue was already reviewed before it went live, so
 * re-listing restores it immediately. Owner-scoped + status='delisted' guarded in
 * portal_relist(). Best-effort notifies the team (mail failure never blocks the
 * restore). Expects in scope: $pdo, int $vid, int $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/mail.php';
require_once __DIR__ . '/../../lib/portal.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    redirect('portal/venues/' . $vid);
}

$userId = (int)(auth_user()['id'] ?? 0);
try {
    $res = portal_relist($pdo, $vid, $partnerId, $userId);
} catch (Throwable $e) {
    error_log('portal relist failed (venue=' . $vid . '): ' . $e->getMessage());
    $res = ['ok' => false, 'error' => 'Something went wrong. Please try again.'];
}

if (!empty($res['ok'])) {
    // Best-effort: let the team know a venue was self-serve re-listed. Never blocks.
    $recips = defined('MAIL_ADMIN_RECIPIENTS') ? (string)MAIL_ADMIN_RECIPIENTS : '';
    if ($recips !== '') {
        $name    = (string)($res['name'] ?? ('Venue #' . $vid));
        $subject = 'Venue re-listed: ' . $name;
        $body    = '<p>A provider has re-listed a previously delisted venue.</p>'
                 . '<table cellpadding="4"><tr><td>Venue</td><td><strong>' . e($name) . '</strong> (#' . (int)$vid . ')</td></tr>'
                 . '<tr><td>Action</td><td>Self-serve re-list &mdash; venue is public again</td></tr></table>';
        foreach (array_filter(array_map('trim', explode(',', $recips))) as $to) {
            if (!send_mail($to, $subject, $body)) {
                error_log('relist notify to ' . $to . ' failed (venue=' . $vid . ')');
            }
        }
    }
    $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Your venue is live again on All The Venues.'];
} else {
    $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => (string)($res['error'] ?? 'Could not re-list this venue.')];
}
redirect('portal/venues/' . $vid);

<?php
declare(strict_types=1);

/**
 * PU-D1-fix-2 #1C — Withdraw an owned venue that is awaiting first review back to
 * a private draft (POST-only). Reached via dispatch (/portal/venues/{id}/withdraw),
 * already auth_require_partner-gated. Owner-scoped + state-guarded inside
 * portal_withdraw_to_draft() (only from venue 'pending' + its new_venue CR
 * 'pending'); a no-op from any other state. Expects: $pdo, int $vid, int $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/portal.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    redirect('portal/venues/' . $vid);
}

$userId = (int)(auth_user()['id'] ?? 0);
try {
    $ok = portal_withdraw_to_draft($pdo, $vid, $partnerId, $userId);
} catch (Throwable $e) {
    error_log('portal withdraw failed (venue=' . $vid . '): ' . $e->getMessage());
    $ok = false;
}

$_SESSION['portal_flash'] = $ok
    ? ['type' => 'success', 'msg' => 'Withdrawn to draft. You can edit and re-submit any time.']
    : ['type' => 'error', 'msg' => 'This venue could not be withdrawn.'];
redirect('portal/venues/' . $vid);

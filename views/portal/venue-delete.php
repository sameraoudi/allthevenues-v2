<?php
declare(strict_types=1);

/**
 * PU-D1-fix Part F — Delete an owned DRAFT venue (POST-only). Reached via dispatch
 * (/portal/venues/{id}/delete), already auth_require_partner-gated. The delete is
 * owner-scoped AND status='draft' ONLY inside portal_delete_draft_venue() — a
 * pending/needs_changes/published/archived venue can never be removed here. On
 * success redirects to My Venues (the venue no longer exists); otherwise flashes
 * and returns to the detail hub. Expects in scope: $pdo, int $vid, int $partnerId.
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
    $ok = portal_delete_draft_venue($pdo, $vid, $partnerId, $userId);
} catch (Throwable $e) {
    error_log('portal draft delete failed (venue=' . $vid . '): ' . $e->getMessage());
    $ok = false;
}

if ($ok) {
    $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Draft deleted.'];
    redirect('portal');   // the venue is gone — back to My Venues
}

// Not a deletable draft (wrong status / not owned) or an error — stay put.
$_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'This venue could not be deleted.'];
redirect('portal/venues/' . $vid);

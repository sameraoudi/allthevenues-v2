<?php
declare(strict_types=1);

/**
 * Delist-1 — Provider portal: request delisting of a PUBLISHED venue (submit side)
 * + withdraw the pending request. Reached via dispatch
 * (/portal/venues/{id}/delist[/withdraw]), already auth_require_partner-gated. A
 * delist is a REVERSIBLE take-down requested here (admin-approved in Delist-2) —
 * the venue stays published until approved. The venues table is never changed
 * here; only a venue_change_requests row (type='delist') is written. Ownership is
 * re-checked on GET and POST; create/withdraw are owner-scoped in the query WHERE.
 * Expects in scope: $pdo, int $vid, string $delistAction ('' | 'withdraw'), $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/portal.php';

$userId = (int)(auth_user()['id'] ?? 0);

$venue = portal_venue_for_partner($pdo, $vid, $partnerId);
if ($venue === null) {
    http_response_code(404);   // not owned / not found — existence never revealed
    $page_title          = 'Not found — Partner Portal';
    $portal_content_view = __DIR__ . '/../content/404_content.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ---- (A) Withdraw the provider's own pending delist request (POST only) ------ */
if ($delistAction === 'withdraw') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_validate()) {
        $rid = (int)($_POST['request_id'] ?? 0);
        if (portal_withdraw_delist($pdo, $rid, $vid, $partnerId)) {
            audit_log($pdo, $userId ?: null, 'withdraw', 'change_request', $rid);
            $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Delisting request withdrawn.'];
        }
    } else {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    }
    redirect('portal/venues/' . $vid);
    return;
}

/* ---- (B) The delist request form / submit ------------------------------------ */
// Guardrails mirrored from portal_request_delist so the form only shows when valid.
if ((string)$venue['status'] !== 'published') {
    $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Only a published venue can be delisted.'];
    redirect('portal/venues/' . $vid);
    return;
}
if (portal_pending_delist_request($pdo, $vid, $partnerId) !== null) {
    $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'You already have a delisting request pending for this venue.'];
    redirect('portal/venues/' . $vid);
    return;
}

$errors = [];
$old    = ['reason' => '', 'details' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = [
        'reason'  => (string)($_POST['reason'] ?? ''),
        'details' => (string)($_POST['details'] ?? ''),
    ];
    if (!csrf_validate()) {
        $errors['_form'] = 'Your session expired. Please review and submit again.';
    } elseif (!isset(portal_delist_reasons()[$old['reason']])) {
        $errors['reason'] = 'Please choose a reason for delisting.';
    } else {
        $res = portal_request_delist($pdo, $vid, $partnerId, $userId, $old['reason'], $old['details']);
        if (!empty($res['ok'])) {
            $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Delisting request submitted. Your venue stays live until it is reviewed.'];
            redirect('portal/venues/' . $vid);
            return;
        }
        $errors['_form'] = (string)($res['error'] ?? 'Could not submit your request.');
    }
}

$page_title          = 'Request delisting — Partner Portal';
$portal_active       = 'venues';
$portal_content_view = __DIR__ . '/venue-delist-content.php';
require __DIR__ . '/layout.php';

<?php
declare(strict_types=1);

/**
 * #3 U-P8a — Provider portal: claim an existing venue (submit side). Reached via
 * dispatch (/portal/claim[/{venueId}] and /portal/claim/{requestId}/withdraw|proof),
 * already auth_require_partner-gated. Submitting writes a venue_change_requests
 * row type='claim' (pending) — the venue is NEVER modified here (admin reviews in
 * U-P8b). Validation iterates an allowlist, never $_POST. Expects in scope: $pdo,
 * int $partnerId, int $claimTarget, string $claimAction, int $claimRequestId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ratelimit.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/portal.php';

$userId    = (int)(auth_user()['id'] ?? 0);
$userName  = trim((string)(auth_user()['name'] ?? ''));
$userEmail = trim((string)(auth_user()['email'] ?? ''));
$claimRoles = ['Owner', 'General manager', 'Marketing / events', 'Agency or representative', 'Other'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate()) {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
        redirect('portal/claim');
    }

    // ---- Withdraw a pending claim ----
    if ($claimAction === 'withdraw') {
        $ok = portal_withdraw_claim($pdo, $claimRequestId, $partnerId);
        $_SESSION['portal_flash'] = $ok
            ? ['type' => 'success', 'msg' => 'Claim withdrawn.']
            : ['type' => 'error', 'msg' => 'That claim could not be withdrawn.'];
        redirect('portal/claim');
    }

    $plain = static fn(string $k, int $max): string => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);
    $proofRaw = trim((string)($_POST['proof_url'] ?? ''));
    $proof    = (preg_match('~^https?://~i', $proofRaw) && mb_strlen($proofRaw) <= 255) ? $proofRaw : '';

    // ---- Add proof to a needs_changes claim ----
    if ($claimAction === 'proof') {
        $clean = ['message' => $plain('message', 2000), 'proof_url' => $proof, 'actor' => $userName];
        try {
            $ok = portal_add_claim_proof($pdo, $claimRequestId, $partnerId, $clean);
        } catch (Throwable $e) {
            error_log('portal add-proof failed (req=' . $claimRequestId . '): ' . $e->getMessage());
            $ok = false;
        }
        $_SESSION['portal_flash'] = $ok
            ? ['type' => 'success', 'msg' => 'Thanks — your claim is back with our team for review.']
            : ['type' => 'error', 'msg' => 'That claim could not be updated.'];
        redirect('portal/claim');
    }

    // ---- Create a new claim ----
    if (!ratelimit_hit('portal_claim', (string)$partnerId, 10, 3600)) {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => "You've submitted several claims recently; please try again later."];
        redirect('portal/claim');
    }

    $venueId = (int)($_POST['venue_id'] ?? 0);
    $role    = (string)($_POST['role'] ?? '');
    $email   = trim((string)($_POST['work_email'] ?? ''));
    $clean = [
        'role'           => in_array($role, $claimRoles, true) ? $role : 'Other',
        'work_email'     => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '',
        'message'        => $plain('message', 2000),
        'proof_url'      => $proof,
        'requester_name' => $userName,
    ];

    if ((string)($_POST['consent'] ?? '') !== '1') {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Please confirm you are authorised to manage this venue.'];
        redirect($venueId > 0 ? 'portal/claim/' . $venueId : 'portal/claim');
    }
    if ($clean['message'] === '' && $clean['proof_url'] === '') {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Please add a short message or a proof link so our team can review your claim.'];
        redirect($venueId > 0 ? 'portal/claim/' . $venueId : 'portal/claim');
    }

    try {
        portal_create_claim($pdo, $venueId, $partnerId, $userId, $clean);
        $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Claim submitted for review.'];
        redirect('portal/claim');
    } catch (Throwable $e) {
        error_log('portal create-claim failed (venue=' . $venueId . ', partner=' . $partnerId . '): ' . $e->getMessage());
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'That venue can’t be claimed (it may be yours already, unpublished, or you already have an open claim).'];
        redirect('portal/claim');
    }
}

// ---- GET: claim detail (#11) — owner-scoped timeline view ----
if ($claimAction === 'view' && $claimRequestId > 0) {
    $claim = portal_claim_for_partner($pdo, $claimRequestId, $partnerId);
    if ($claim === null) {
        http_response_code(404);
        $page_title          = 'Not found — Partner Portal';
        $portal_content_view = __DIR__ . '/../content/404_content.php';
        require __DIR__ . '/layout.php';
        return;
    }
    $events              = portal_claim_timeline($claim);
    $flash               = $_SESSION['portal_flash'] ?? null;
    unset($_SESSION['portal_flash']);
    $page_title          = 'Claim — Partner Portal';
    $portal_active       = 'claims';
    $portal_content_view = __DIR__ . '/claim-detail.php';
    require __DIR__ . '/layout.php';
    return;
}

// ---- GET: roomy "Add proof" screen (#9) — only while awaiting proof ----
if ($claimAction === 'proof' && $claimRequestId > 0) {
    $claim = portal_claim_for_partner($pdo, $claimRequestId, $partnerId);
    if ($claim === null || (string)$claim['status'] !== 'needs_changes') {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'That claim is not awaiting proof.'];
        redirect('portal/claim');
    }
    // The latest proof_requested note (what All The Venues asked for).
    $reqNote = '';
    foreach (portal_claim_timeline($claim) as $ev) {
        if (($ev['type'] ?? '') === 'proof_requested') { $reqNote = (string)($ev['note'] ?? ''); }
    }
    $flash               = $_SESSION['portal_flash'] ?? null;
    unset($_SESSION['portal_flash']);
    $page_title          = 'Add proof — Partner Portal';
    $portal_active       = 'claims';
    $portal_content_view = __DIR__ . '/claim-proof.php';
    require __DIR__ . '/layout.php';
    return;
}

// ---- GET: search + optional selected target + your claims ----
$q       = trim((string)($_GET['q'] ?? ''));
$results = $q !== '' ? portal_claimable_search($pdo, $partnerId, $q) : [];
$target  = $claimTarget > 0 ? portal_claim_target($pdo, $claimTarget, $partnerId) : null;
$openOnTarget = $target !== null ? portal_open_claim($pdo, (int)$target['id'], $partnerId) : null;
$myClaims = portal_my_claims($pdo, $partnerId);
$flash    = $_SESSION['portal_flash'] ?? null;
unset($_SESSION['portal_flash']);

$page_title          = 'Claim a venue — Partner Portal';
$portal_active       = 'claims';
$portal_content_view = __DIR__ . '/claim-content.php';
require __DIR__ . '/layout.php';

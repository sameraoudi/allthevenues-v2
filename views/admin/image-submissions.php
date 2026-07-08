<?php
declare(strict_types=1);

/**
 * #3 U-P7b — Admin controller for provider photo submissions. Gated
 * auth_require_role(['admin','editor']) by dispatch; re-asserted here. Queue +
 * approve/reject decisions (CSRF, PRG). A dedicated lane, separate from the #9
 * /admin/image-review backlog. Expects $pdo and $sub in scope.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/image_submission_admin.php';

auth_require_role(['admin', 'editor']);   // defence in depth

$me  = auth_current_user();
$uid = (int)($me['id'] ?? 0);
$listUrl = base_url('admin/image-submissions');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // PU-E (#20) — keep the venue filter across a decision so the admin stays in
    // this venue's photos (until it clears) instead of bouncing to the full queue.
    $postVenueId = (int)($_POST['venue_id'] ?? 0);
    $backTo = 'admin/image-submissions' . ($postVenueId > 0 ? '?venue_id=' . $postVenueId : '');

    if (!csrf_validate()) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
        redirect($backTo);
    }
    $action  = (string)($_POST['action'] ?? '');
    $imageId = (int)($_POST['image_id'] ?? 0);
    $img     = image_submission_get($pdo, $imageId);

    if ($img === null) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'That photo could not be found.'];
        redirect($backTo);
    }

    if ($action === 'approve') {
        $res = image_submission_approve($pdo, $img, $uid,
            (string)($_POST['permission_status'] ?? ''), ((string)($_POST['set_primary'] ?? '') === '1'));
        $msg = !empty($res['ok']) ? 'Photo approved and published.' : ($res['error'] ?? 'Could not approve.');
        if (!empty($res['warning'])) { $msg .= ' ' . $res['warning']; }
    } elseif ($action === 'reject') {
        $res = image_submission_reject($pdo, $img, $uid,
            (string)($_POST['reason'] ?? ''), (string)($_POST['note'] ?? ''));
        $msg = !empty($res['ok']) ? 'Photo rejected.' : ($res['error'] ?? 'Could not reject.');
        if (!empty($res['warning'])) { $msg .= ' ' . $res['warning']; }
    } else {
        $res = ['ok' => false];
        $msg = 'Unknown action.';
    }

    $_SESSION['admin_flash'] = [
        'type' => (!empty($res['ok']) ? (!empty($res['warning']) ? 'warning' : 'success') : 'error'),
        'msg'  => $msg,
    ];
    redirect($backTo);
}

// PU-E (#20) — optional venue filter (?venue_id=N) linked from the new-venue
// review. A blank/invalid id falls back to the full queue. The name is resolved
// for the "filtering" banner; a valid id with no pending photos shows an empty
// filtered view (with the back-to-all link) rather than the whole queue.
$filterVenueId   = (int)($_GET['venue_id'] ?? 0);
$filterVenueName = '';
if ($filterVenueId > 0) {
    $vn = $pdo->prepare('SELECT name FROM venues WHERE id = :id LIMIT 1');
    $vn->execute([':id' => $filterVenueId]);
    $filterVenueName = (string)($vn->fetchColumn() ?: '');
    if ($filterVenueName === '') { $filterVenueId = 0; }   // unknown venue → full queue
}

$groups = image_submissions_grouped($pdo, $filterVenueId > 0 ? $filterVenueId : null);
$count  = image_submissions_count($pdo);

// KPIs: photos awaiting, venues with submissions, oldest waiting (days).
$oldestDays = null;
foreach ($groups as $g) {
    foreach ($g['pending'] as $p) {
        $t = strtotime((string)($p['created_at'] ?? ''));
        if ($t !== false) {
            $d = (int)floor((time() - $t) / 86400);
            if ($oldestDays === null || $d > $oldestDays) { $oldestDays = $d; }
        }
    }
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$admin_active       = 'image-submissions';
$page_title         = 'Provider photos — Admin';
$admin_page_title   = 'Provider photo submissions';
$admin_content_view = __DIR__ . '/image-submissions-content.php';
require __DIR__ . '/layout.php';

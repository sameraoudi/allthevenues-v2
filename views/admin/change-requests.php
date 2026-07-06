<?php
declare(strict_types=1);

/**
 * #3 U-P5b — Admin controller for provider EDIT change requests. Gated
 * auth_require_role(['admin']) by dispatch; re-asserted here defensively. Routes:
 *   ''            → the review queue (status/type filters; pending default)
 *   {id}          → the diff detail + decision actions
 *   {id} (POST)   → approve / reject / needs_changes  (CSRF; note required on the
 *                   two non-approving decisions)
 * Only type='edit' requests are actionable; other types render read-only with a
 * "reviewed in a later unit" note. Expects $pdo and $sub ('change-requests…').
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/change_request_admin.php';

auth_require_role(['admin']);   // defence in depth

$me   = auth_current_user();
$uid  = (int)($me['id'] ?? 0);
$rest = trim(substr((string)$sub, strlen('change-requests')), '/');   // '' | '5'

/* ============================ LIST ======================================= */
if ($rest === '') {
    $filters = [
        'status' => trim((string)($_GET['status'] ?? 'pending')),
        'type'   => trim((string)($_GET['type'] ?? '')),
    ];
    $rows = cr_admin_list($pdo, $filters);

    $admin_active       = 'change-requests';
    $page_title         = 'Change requests — Admin';
    $admin_page_title   = 'Change requests';
    $admin_content_view = __DIR__ . '/change-requests-list.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ DETAIL / DECISION ========================== */
if (preg_match('#^(\d+)$#', $rest, $m)) {
    $id  = (int)$m[1];
    $req = cr_admin_get($pdo, $id);
    if ($req === null) {
        http_response_code(404);
        $admin_active = 'change-requests'; $page_title = 'Not found — Admin';
        $admin_page_title = 'Not found'; $admin_notfound = true;
        $admin_content_view = __DIR__ . '/placeholder-content.php';
        require __DIR__ . '/layout.php';
        return;
    }

    $detailUrl = base_url('admin/change-requests/' . $id);
    $noteError = null;
    $note      = '';

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $decision = trim((string)($_POST['decision'] ?? ''));
        $note     = trim((string)($_POST['review_note'] ?? ''));

        if (!csrf_validate()) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
            redirect($detailUrl);
        }

        if (($req['type'] ?? '') !== 'edit') {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'This request type is reviewed in a later unit.'];
            redirect($detailUrl);
        }

        if (in_array($decision, ['reject', 'needs_changes'], true) && $note === '') {
            $noteError = 'A note to the provider is required for this decision.';
        } else {
            switch ($decision) {
                case 'approve':        $res = cr_approve($pdo, $req, $uid, $note); break;
                case 'reject':         $res = cr_reject($pdo, $req, $uid, $note); break;
                case 'needs_changes':  $res = cr_needs_changes($pdo, $req, $uid, $note); break;
                default:               $res = ['ok' => false, 'error' => 'Unknown decision.'];
            }

            if (!empty($res['ok'])) {
                $labels = ['approve' => 'Change request approved and applied.',
                           'reject' => 'Change request rejected.',
                           'needs_changes' => 'Changes requested from the provider.'];
                $msg = $labels[$decision] ?? 'Done.';
                if (!empty($res['warning'])) { $msg .= ' ' . $res['warning']; }
                $_SESSION['admin_flash'] = ['type' => (!empty($res['warning']) ? 'warning' : 'success'), 'msg' => $msg];
                redirect(base_url('admin/change-requests'));
            }
            $noteError = $res['error'] ?? 'Could not complete that action.';
        }
    }

    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    $admin_active       = 'change-requests';
    $page_title         = 'Change request — Admin';
    $admin_page_title   = 'Change request';
    $admin_content_view = __DIR__ . '/change-request-detail.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ 404 ======================================== */
http_response_code(404);
$admin_active = 'change-requests'; $page_title = 'Not found — Admin';
$admin_page_title = 'Not found'; $admin_notfound = true;
$admin_content_view = __DIR__ . '/placeholder-content.php';
require __DIR__ . '/layout.php';

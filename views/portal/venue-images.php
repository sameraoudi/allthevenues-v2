<?php
declare(strict_types=1);

/**
 * #3 U-P7a — Provider portal: venue-image uploads (submit + withdraw). Reached
 * via dispatch (/portal/venues/{id}/images), already auth_require_partner-gated.
 * Uploads land review_status='pending_review' + status='hidden' — invisible
 * publicly and unable to satisfy any publish gate — until admin approval (U-P7b).
 * Ownership is re-checked here (portal_venue_for_partner) and again inside every
 * data-layer write. Expects in scope: $pdo, int $vid, int $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ratelimit.php';
require_once __DIR__ . '/../../lib/upload.php';    // upload_venue_image()
require_once __DIR__ . '/../../lib/audit.php';      // audit_log()
require_once __DIR__ . '/../../lib/portal.php';

$venue = portal_venue_for_partner($pdo, $vid, $partnerId);
if ($venue === null) {
    http_response_code(404);   // not owned / not found — existence never revealed
    $page_title          = 'Not found — Partner Portal';
    $portal_content_view = __DIR__ . '/../content/404_content.php';
    require __DIR__ . '/layout.php';
    return;
}

$userId       = (int)(auth_user()['id'] ?? 0);
$uploaderName = trim((string)(auth_user()['name'] ?? ''));
$imagesUrl    = 'portal/venues/' . $vid . '/images';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_validate()) {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
        redirect($imagesUrl);
    }

    if ($action === 'upload') {
        if (!ratelimit_hit('portal_img_upload', (string)$partnerId, 40, 3600)) {
            $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => "You've uploaded a lot of photos recently; please try again later."];
            redirect($imagesUrl);
        }
        if ((string)($_POST['rights_confirm'] ?? '') !== '1') {
            $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Please confirm you have the rights to these images.'];
            redirect($imagesUrl);
        }

        $alt   = mb_substr(trim(strip_tags((string)($_POST['alt'] ?? ''))), 0, 255);
        $files = $_FILES['images'] ?? null;
        $ok = 0; $failed = 0; $firstErr = '';

        if (isset($files['name']) && is_array($files['name'])) {
            for ($i = 0, $n = count($files['name']); $i < $n; $i++) {
                if ((int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;   // empty slot
                }
                $entry = [
                    'name'     => $files['name'][$i]     ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error'    => $files['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $files['size'][$i]     ?? 0,
                ];
                try {
                    $res = portal_add_pending_image($pdo, $vid, $partnerId, $userId, $uploaderName, $entry, $alt);
                } catch (Throwable $e) {
                    error_log('portal image upload failed (venue=' . $vid . '): ' . $e->getMessage());
                    $res = ['ok' => false, 'error' => 'Something went wrong saving that photo.'];
                }
                if (!empty($res['ok'])) {
                    $ok++;
                } else {
                    $failed++;
                    if ($firstErr === '') { $firstErr = (string)($res['error'] ?? 'Upload failed.'); }
                }
            }
        }

        if ($ok === 0 && $failed === 0) {
            $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'No photos were selected.'];
        } else {
            $msg = $ok . ' photo' . ($ok === 1 ? '' : 's') . ' submitted for review.';
            if ($failed > 0) { $msg .= ' ' . $failed . ' could not be added: ' . $firstErr; }
            $_SESSION['portal_flash'] = ['type' => ($ok > 0 ? 'success' : 'error'), 'msg' => $msg];
        }
        redirect($imagesUrl);
    }

    if ($action === 'withdraw') {
        try {
            $done = portal_withdraw_image($pdo, (int)($_POST['image_id'] ?? 0), $vid, $partnerId);
        } catch (Throwable $e) {
            error_log('portal image withdraw failed (venue=' . $vid . '): ' . $e->getMessage());
            $done = false;
        }
        $_SESSION['portal_flash'] = $done
            ? ['type' => 'success', 'msg' => 'Photo withdrawn.']
            : ['type' => 'error', 'msg' => 'That photo could not be withdrawn.'];
        redirect($imagesUrl);
    }

    redirect($imagesUrl);
}

$pending  = portal_venue_images_pending($pdo, $vid, $partnerId);
$rejected = portal_venue_images_rejected($pdo, $vid, $partnerId);
$live     = portal_venue_images_live($pdo, $vid, $partnerId);
$flash    = $_SESSION['portal_flash'] ?? null;
unset($_SESSION['portal_flash']);

$page_title          = 'Venue photos — Partner Portal';
$portal_active       = 'venues';
$portal_content_view = __DIR__ . '/venue-images-content.php';
require __DIR__ . '/layout.php';

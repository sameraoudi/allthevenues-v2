<?php
declare(strict_types=1);

/**
 * Venue image manager — mutating actions (U4b). Required from venues.php when
 * $rest starts with 'images/'. Already gated admin+editor by dispatch.
 * POST-only, CSRF on every write, audit_log on every change. Redirects back to
 * the venue edit page. Expects $pdo, $me, $rest in scope.
 */

require_once __DIR__ . '/../../lib/upload.php';
require_once __DIR__ . '/../../lib/venue_images_admin.php';

$action = substr((string)$rest, strlen('images/'));   // upload|primary|reorder|alt|delete
$uid    = (int)($me['id'] ?? 0) ?: null;
$venueId = (int)($_POST['venue_id'] ?? 0);

/** Flash + redirect helper. */
$done = static function (string $type, string $msg) use ($venueId): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
    if ($venueId > 0) {
        redirect('admin/venues/edit?id=' . $venueId);
    }
    redirect('admin/venues');
};

// POST + CSRF (fail closed).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    $done('error', 'Your session expired. Please try again.');
}

// Venue must exist (ownership root for every image action).
$venue = $venueId > 0 ? venue_admin_get($pdo, $venueId) : null;
if ($venue === null) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'That venue no longer exists.'];
    redirect('admin/venues');
}

$imageId = (int)($_POST['image_id'] ?? 0);

switch ($action) {

    /* ---- Upload one or more images ---- */
    case 'upload':
        $files = $_FILES['images'] ?? null;
        if (!isset($files['name']) || !is_array($files['name'])) {
            $done('error', 'No files were selected.');
        }
        $ok = 0; $fail = 0; $firstErr = '';
        for ($i = 0, $n = count($files['name']); $i < $n; $i++) {
            if ((int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;   // empty file input slot
            }
            $entry = [
                'name'     => $files['name'][$i]     ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i]     ?? 0,
            ];
            $res = upload_venue_image($entry, $venueId);
            if (!empty($res['ok'])) {
                $imgId = venue_images_add($pdo, $venueId, $res['file_path'], $res['thumb_path'], '');
                audit_log($pdo, $uid, 'venue_image.upload', 'venue_image', $imgId,
                    null, ['venue_id' => $venueId, 'file_path' => $res['file_path']]);
                $ok++;
            } else {
                $fail++;
                if ($firstErr === '') { $firstErr = (string)($res['error'] ?? 'Upload failed.'); }
            }
        }
        if ($ok === 0 && $fail === 0) {
            $done('error', 'No files were selected.');
        }
        if ($fail === 0) {
            $done('success', $ok . ' image' . ($ok === 1 ? '' : 's') . ' uploaded.');
        }
        $done($ok > 0 ? 'success' : 'error',
            $ok . ' uploaded, ' . $fail . ' rejected — ' . $firstErr);
        break;

    /* ---- Set primary ---- */
    case 'primary':
        if (venue_images_set_primary($pdo, $venueId, $imageId)) {
            audit_log($pdo, $uid, 'venue_image.set_primary', 'venue_image', $imageId,
                null, ['venue_id' => $venueId]);
            $done('success', 'Primary image updated.');
        }
        $done('error', 'That image could not be updated.');
        break;

    /* ---- Reorder (move up/down) ---- */
    case 'reorder':
        $dir = ((string)($_POST['dir'] ?? '') === 'up') ? -1 : 1;
        if (venue_images_move($pdo, $venueId, $imageId, $dir)) {
            audit_log($pdo, $uid, 'venue_image.reorder', 'venue_image', $imageId,
                null, ['venue_id' => $venueId, 'dir' => $dir < 0 ? 'up' : 'down']);
            $done('success', 'Image order updated.');
        }
        $done('error', 'That image could not be moved.');
        break;

    /* ---- Edit alt text ---- */
    case 'alt':
        $img = venue_images_get($pdo, $venueId, $imageId);
        if ($img === null) {
            $done('error', 'That image could not be found.');
        }
        $alt = mb_substr(trim(strip_tags((string)($_POST['alt_text'] ?? ''))), 0, 255);
        venue_images_update_alt($pdo, $venueId, $imageId, $alt);
        audit_log($pdo, $uid, 'venue_image.alt', 'venue_image', $imageId,
            ['alt_text' => $img['alt_text']], ['venue_id' => $venueId, 'alt_text' => $alt]);
        $done('success', 'Alt text saved.');
        break;

    /* ---- Image rights / provenance (#9b) ---- */
    case 'provenance':
        $img = venue_images_get($pdo, $venueId, $imageId);
        if ($img === null) {
            $done('error', 'That image could not be found.');
        }
        $fields = [
            'permission_status'    => (string)($_POST['permission_status'] ?? ''),
            'image_source'         => (string)($_POST['image_source'] ?? ''),
            'source_url'           => (string)($_POST['source_url'] ?? ''),
            'provider_approved_by' => (string)($_POST['provider_approved_by'] ?? ''),
            'approval_date'        => (string)($_POST['approval_date'] ?? ''),
            'expires_at'           => (string)($_POST['expires_at'] ?? ''),
            'usage_notes'          => (string)($_POST['usage_notes'] ?? ''),
        ];
        if (!venue_images_update_provenance($pdo, $venueId, $imageId, $fields)) {
            $done('error', 'Could not save image rights (check the permission status).');
        }
        audit_log($pdo, $uid, 'venue_image.provenance', 'venue_image', $imageId,
            ['permission_status' => $img['permission_status'] ?? null],
            ['venue_id' => $venueId, 'permission_status' => $fields['permission_status'] ?: null]);
        $done('success', 'Image rights saved.');
        break;

    /* ---- Delete ---- */
    case 'delete':
        $deleted = venue_images_delete($pdo, $venueId, $imageId);
        if ($deleted !== null) {
            audit_log($pdo, $uid, 'venue_image.delete', 'venue_image', $imageId,
                ['file_path' => $deleted['file_path'], 'is_primary' => $deleted['is_primary']],
                ['venue_id' => $venueId]);
            $done('success', 'Image deleted.');
        }
        $done('error', 'That image could not be deleted.');
        break;

    default:
        $done('error', 'Unknown action.');
}

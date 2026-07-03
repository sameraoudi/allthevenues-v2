<?php
declare(strict_types=1);

/**
 * Provider cover-image manager — mutating actions (U4d-3c). Required from
 * partners.php when $rest starts with 'cover/'. Already gated admin+editor by
 * dispatch. POST-only, CSRF on every write, audit_log on every change. Redirects
 * back to the provider edit page. Expects $pdo, $me, $rest in scope.
 *
 * A provider has exactly ONE cover — no primary, no reorder, no gallery.
 */

require_once __DIR__ . '/../../lib/upload.php';
require_once __DIR__ . '/../../lib/partner_admin.php';

$action    = substr((string)$rest, strlen('cover/'));   // upload|alt|delete
$uid       = (int)($me['id'] ?? 0) ?: null;
$partnerId = (int)($_POST['partner_id'] ?? 0);

/** Flash + redirect helper. */
$done = static function (string $type, string $msg) use ($partnerId): void {
    $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg];
    if ($partnerId > 0) {
        redirect('admin/partners/edit?id=' . $partnerId);
    }
    redirect('admin/partners');
};

// POST + CSRF (fail closed).
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    $done('error', 'Your session expired. Please try again.');
}

// Ownership root: the partner must exist.
$partner = $partnerId > 0 ? partner_admin_get($pdo, $partnerId) : null;
if ($partner === null) {
    $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'That provider no longer exists.'];
    redirect('admin/partners');
}

/** Fail-safe unlink of an app-relative path (skips empty / a path still in use). */
$unlinkRel = static function (?string $rel, string $keep = ''): void {
    $rel = trim((string)$rel);
    if ($rel === '' || $rel === $keep) {
        return;
    }
    $abs = app_path($rel);
    if (is_file($abs)) {
        @unlink($abs);
    }
};

switch ($action) {

    /* ---- Upload / replace the single cover ---- */
    case 'upload':
        $file = $_FILES['cover'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $done('error', 'No file was selected.');
        }
        $res = upload_partner_cover($file, $partnerId);
        if (empty($res['ok'])) {
            $done('error', (string)($res['error'] ?? 'Upload failed.'));
        }
        $oldFull  = (string)($partner['cover_image_path'] ?? '');
        $oldThumb = (string)($partner['cover_thumb_path'] ?? '');
        partner_cover_replace($pdo, $partnerId, $res['file_path'], $res['thumb_path']);
        // Remove the superseded files (fail-safe; never the just-written ones).
        $unlinkRel($oldFull, $res['file_path']);
        $unlinkRel($oldThumb, $res['thumb_path']);
        audit_log($pdo, $uid, 'partner_cover.upload', 'partner_cover', $partnerId,
            ['file_path' => $oldFull], ['file_path' => $res['file_path']]);
        $done('success', 'Cover image updated.');
        break;

    /* ---- Edit alt text ---- */
    case 'alt':
        $alt = mb_substr(trim(strip_tags((string)($_POST['alt_text'] ?? ''))), 0, 255);
        partner_cover_update_alt($pdo, $partnerId, $alt);
        audit_log($pdo, $uid, 'partner_cover.alt', 'partner_cover', $partnerId,
            ['alt' => $partner['cover_image_alt']], ['alt' => $alt]);
        $done('success', 'Alt text saved.');
        break;

    /* ---- Remove the cover ---- */
    case 'delete':
        $oldFull  = (string)($partner['cover_image_path'] ?? '');
        $oldThumb = (string)($partner['cover_thumb_path'] ?? '');
        partner_cover_clear($pdo, $partnerId);
        $unlinkRel($oldFull);
        $unlinkRel($oldThumb);
        audit_log($pdo, $uid, 'partner_cover.delete', 'partner_cover', $partnerId,
            ['file_path' => $oldFull], null);
        $done('success', 'Cover image removed.');
        break;

    default:
        $done('error', 'Unknown action.');
}

<?php
declare(strict_types=1);

/**
 * Secure image upload → WebP (U4b).
 *
 * Allowlist: jpg / jpeg / png / webp ONLY, validated by getimagesize() (a real
 * image) AND the file extension. Server-generated RANDOM filenames — the user's
 * name is never used, so no double-extension / path tricks survive.
 *
 * Every accepted image is re-encoded to WebP for BOTH the full image and a
 * thumbnail. This single step is the security re-encode (drops EXIF / any
 * embedded payload — only pixels survive) AND the size optimization. PNG/WebP
 * transparency is preserved.
 *
 * Files land under uploads/venues/{venueId}/ — uploads/.htaccess disables PHP
 * execution across that whole subtree, so uploads are never web-executable.
 *
 * Requires GD WebP support (imagewebp + decoders). If absent we FAIL with a
 * clear error rather than silently storing the original.
 */

require_once __DIR__ . '/helpers.php';   // app_path()

const UPLOAD_MAX_BYTES    = 12 * 1024 * 1024;   // 12 MB per file
const UPLOAD_MAX_PIXELS   = 40000000;           // ~40 MP — decompression-bomb guard
const UPLOAD_FULL_DIM     = 2000;               // max full-image longest edge
const UPLOAD_THUMB_DIM    = 600;                // max thumbnail longest edge
const UPLOAD_WEBP_QUALITY = 82;

/** GD WebP availability (encode + all decoders we use). */
function upload_webp_supported(): bool
{
    return function_exists('imagewebp')
        && function_exists('imagecreatefromwebp')
        && function_exists('imagecreatefromjpeg')
        && function_exists('imagecreatefrompng');
}

/**
 * Core: process one uploaded image → WebP full + thumbnail under $relDir
 * (app-relative, e.g. "uploads/venues/12" or "uploads/partners/5"). Shared by
 * the venue-image and provider-cover entry points below — same validation,
 * same result shape.
 *
 * @param array  $file   one $_FILES entry (name, tmp_name, error, size)
 * @param string $relDir app-relative storage directory (no trailing slash)
 * @return array{ok:bool, file_path?:string, thumb_path?:string, error?:string}
 *         paths are app-relative (e.g. uploads/venues/12/ab….webp).
 */
function _upload_process_image(array $file, string $relDir): array
{
    if (!upload_webp_supported()) {
        error_log('upload: GD WebP support missing on this host');
        return ['ok' => false, 'error' => 'The server can’t process images right now (WebP support missing). Please contact the administrator.'];
    }

    // --- PHP upload-level errors ---
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was received.'];
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'That file is too large.'];
    }
    if ($err !== UPLOAD_ERR_OK) {
        error_log('upload: PHP upload error code ' . $err);
        return ['ok' => false, 'error' => 'The upload failed. Please try again.'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'The upload could not be verified.'];
    }
    if ((int)($file['size'] ?? 0) > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'That file is too large (max ' . (int)(UPLOAD_MAX_BYTES / 1048576) . ' MB).'];
    }

    // --- extension allowlist (case-insensitive) ---
    $ext = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG and WebP images are allowed.'];
    }

    // --- real-image validation (content, not name) ---
    $info = @getimagesize($tmp);
    if ($info === false) {
        return ['ok' => false, 'error' => 'That file isn’t a valid image.'];
    }
    $w    = (int)$info[0];
    $h    = (int)$info[1];
    $type = (int)$info[2];
    if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG and WebP images are allowed.'];
    }
    if ($w < 1 || $h < 1 || ($w * $h) > UPLOAD_MAX_PIXELS) {
        return ['ok' => false, 'error' => 'That image’s dimensions are out of range.'];
    }

    // --- decode source ---
    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp);  break;
        case IMAGETYPE_WEBP: $src = @imagecreatefromwebp($tmp); break;
        default:             $src = false;
    }
    if (!$src) {
        error_log('upload: decode failed for type ' . $type);
        return ['ok' => false, 'error' => 'That image couldn’t be processed.'];
    }

    // --- storage dir (covered by uploads/.htaccess across the whole subtree) ---
    $absDir = app_path($relDir);
    if (!is_dir($absDir) && !@mkdir($absDir, 0755, true) && !is_dir($absDir)) {
        error_log('upload: mkdir failed for ' . $absDir);
        return ['ok' => false, 'error' => 'Could not store the image.'];
    }

    $base     = bin2hex(random_bytes(16));
    $relFull  = $relDir . '/' . $base . '.webp';
    $relThumb = $relDir . '/' . $base . '_thumb.webp';

    $okFull  = _upload_write_webp($src, $w, $h, UPLOAD_FULL_DIM,  app_path($relFull));
    $okThumb = _upload_write_webp($src, $w, $h, UPLOAD_THUMB_DIM, app_path($relThumb));

    if (!$okFull || !$okThumb) {
        @unlink(app_path($relFull));
        @unlink(app_path($relThumb));
        return ['ok' => false, 'error' => 'That image couldn’t be saved.'];
    }
    return ['ok' => true, 'file_path' => $relFull, 'thumb_path' => $relThumb];
}

/**
 * Venue image: one $_FILES entry → WebP full + thumb under
 * uploads/venues/{venueId}/. Behaviour unchanged from before the refactor.
 * @return array{ok:bool, file_path?:string, thumb_path?:string, error?:string}
 */
function upload_venue_image(array $file, int $venueId): array
{
    return _upload_process_image($file, 'uploads/venues/' . $venueId);
}

/**
 * Provider cover: one $_FILES entry → WebP full + thumb under
 * uploads/partners/{partnerId}/ (same pipeline, provider folder).
 * @return array{ok:bool, file_path?:string, thumb_path?:string, error?:string}
 */
function upload_partner_cover(array $file, int $partnerId): array
{
    return _upload_process_image($file, 'uploads/partners/' . $partnerId);
}

/**
 * Scale (only down, preserving aspect) + re-encode a GD image to WebP at
 * $absPath. Transparency-safe. Returns success.
 */
function _upload_write_webp($src, int $sw, int $sh, int $maxDim, string $absPath): bool
{
    $scale = min(1.0, $maxDim / max($sw, $sh));
    $nw = max(1, (int)round($sw * $scale));
    $nh = max(1, (int)round($sh * $scale));

    $dst = imagecreatetruecolor($nw, $nh);
    if (!$dst) {
        return false;
    }
    // Preserve PNG/WebP alpha into the WebP output.
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $sw, $sh);

    $ok = imagewebp($dst, $absPath, UPLOAD_WEBP_QUALITY);
    if ($ok) {
        @chmod($absPath, 0644);
    }
    return $ok;
}

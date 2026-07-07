<?php
declare(strict_types=1);

/**
 * #3 U-P7b — Admin review of provider-submitted venue photos. Read + decide
 * (approve & publish / reject) over venue_images rows with review_status=
 * 'pending_review' (created by the U-P7a provider upload side). Prepared
 * statements throughout; every decision re-checks the pending guard, is audited,
 * and emails the provider. A SERVER-SIDE publish gate refuses to publish an image
 * whose chosen rights classification is not cleared (#9 permission_status).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venues.php';              // venue_images() (live gallery strip)
require_once __DIR__ . '/venue_images_admin.php';  // permission options + cleared statuses + set_primary
require_once __DIR__ . '/audit.php';                // audit_log()
require_once __DIR__ . '/mail.php';                 // send_mail()

/** Count of photos awaiting review (nav pill). */
function image_submissions_count(PDO $pdo): int
{
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM venue_images WHERE review_status='pending_review'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** All pending submissions grouped by venue, each with its current live gallery. */
function image_submissions_grouped(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT vi.*, v.name AS venue_name, v.slug AS venue_slug, v.partner_id,
                p.org_name AS provider_name, u.name AS uploader_name, u.email AS uploader_email
         FROM venue_images vi
         JOIN venues v        ON v.id = vi.venue_id
         LEFT JOIN partners p ON p.id = v.partner_id
         LEFT JOIN users u    ON u.id = vi.uploaded_by
         WHERE vi.review_status = 'pending_review'
         ORDER BY v.name ASC, vi.id ASC"
    );
    $rows = $stmt->fetchAll();

    $groups = [];
    foreach ($rows as $r) {
        $vid = (int)$r['venue_id'];
        if (!isset($groups[$vid])) {
            $groups[$vid] = [
                'venue'   => [
                    'id'            => $vid,
                    'name'          => (string)$r['venue_name'],
                    'slug'          => (string)$r['venue_slug'],
                    'provider_name' => (string)($r['provider_name'] ?? ''),
                ],
                'live'    => venue_images($pdo, $vid),
                'pending' => [],
            ];
        }
        $groups[$vid]['pending'][] = $r;
    }
    return array_values($groups);
}

/** One pending image with venue/provider/uploader context, or null. */
function image_submission_get(PDO $pdo, int $imageId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT vi.*, v.name AS venue_name, v.slug AS venue_slug, v.partner_id,
                p.org_name AS provider_name, p.email AS provider_email,
                u.name AS uploader_name, u.email AS uploader_email
         FROM venue_images vi
         JOIN venues v        ON v.id = vi.venue_id
         LEFT JOIN partners p ON p.id = v.partner_id
         LEFT JOIN users u    ON u.id = vi.uploaded_by
         WHERE vi.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $imageId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Fixed rejection reasons (value => label). */
function image_submission_reject_reasons(): array
{
    return [
        'low_quality'        => 'Low image quality',
        'watermark_text'     => 'Watermark or text overlay',
        'rights_unclear'     => 'Image rights unclear',
        'people_unconfirmed' => 'People visible without confirmation',
        'not_venue'          => 'Does not clearly show the venue',
        'duplicate'          => 'Duplicate image',
    ];
}

/**
 * The rights classifications offered on this screen, mapped to #9 permission_status
 * ENUM keys (no new enum values). approved_by_provider is the default.
 */
function image_submission_classify_options(): array
{
    return [
        'approved_by_provider'            => 'Rights confirmed by provider',
        'owned_by_atv'                    => 'ATV-owned',
        'licensed_stock'                  => 'Licensed stock',
        'public_website_needs_permission' => 'Public website source, permission required',
        'remove_replace'                  => 'Unknown source — do not publish',
    ];
}

/**
 * Approve & publish a pending image with the chosen rights classification.
 * Refuses (no write) when the classification is not cleared (server publish gate)
 * or not a known option. Optionally sets it as the venue's main photo (atomic in
 * the same transaction). Audits + emails the provider (mail failure never rolls
 * back the committed approve).
 * @return array{ok:bool,error?:string,warning?:string}
 */
function image_submission_approve(PDO $pdo, array $img, int $adminUserId, string $permissionStatus, bool $setPrimary): array
{
    $imageId = (int)$img['id'];
    $fresh   = image_submission_get($pdo, $imageId);
    if ($fresh === null || (string)$fresh['review_status'] !== 'pending_review') {
        return ['ok' => false, 'error' => 'This photo has already been reviewed.'];
    }

    if (!isset(image_submission_classify_options()[$permissionStatus])) {
        return ['ok' => false, 'error' => 'Choose a valid rights classification.'];
    }
    if (!in_array($permissionStatus, venue_images_cleared_statuses(), true)) {
        return ['ok' => false, 'error' => 'Rights are not cleared — this image can’t be published. Reject it or ask the provider to resolve the source.'];
    }

    $venueId = (int)$fresh['venue_id'];

    try {
        $pdo->beginTransaction();

        $upd = $pdo->prepare(
            "UPDATE venue_images
                SET review_status='approved', status='active', permission_status=:ps,
                    provider_approved_by = COALESCE(rights_confirmed_by, :adminname),
                    approval_date = COALESCE(DATE(rights_confirmed_at), CURDATE()),
                    reviewed_by=:uid, reviewed_at=NOW()
              WHERE id=:id AND review_status='pending_review'"
        );
        $adminName = (string)(auth_user()['name'] ?? '');
        $upd->execute([
            ':ps' => $permissionStatus, ':adminname' => $adminName !== '' ? $adminName : null,
            ':uid' => $adminUserId, ':id' => $imageId,
        ]);
        if ($upd->rowCount() < 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'This photo has already been reviewed.'];
        }

        // Set as sole primary — inlined (venue_images_set_primary opens its OWN
        // transaction, so it can't be called inside this one).
        if ($setPrimary) {
            $pdo->prepare('UPDATE venue_images SET is_primary = 0 WHERE venue_id = :vid')
                ->execute([':vid' => $venueId]);
            $pdo->prepare('UPDATE venue_images SET is_primary = 1 WHERE id = :id AND venue_id = :vid')
                ->execute([':id' => $imageId, ':vid' => $venueId]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('image_submission_approve failed (image=' . $imageId . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong publishing the photo. Please try again.'];
    }

    $mailOk = _image_submission_notify($fresh, 'approved', '', '');
    audit_log($pdo, $adminUserId, 'approve', 'venue_image', $imageId,
        ['review_status' => 'pending_review'],
        ['review_status' => 'approved', 'status' => 'active', 'permission_status' => $permissionStatus,
         'set_primary' => $setPrimary, 'notified' => $mailOk]);

    $out = ['ok' => true];
    if (!$mailOk) { $out['warning'] = 'Published, but the provider notification email failed to send.'; }
    return $out;
}

/**
 * Reject a pending image (reason + note both required). The row is RETAINED (feeds
 * the provider "Not approved" list); files are not unlinked here. Audits + emails.
 * @return array{ok:bool,error?:string,warning?:string}
 */
function image_submission_reject(PDO $pdo, array $img, int $adminUserId, string $reason, string $note): array
{
    $imageId = (int)$img['id'];
    $note    = trim($note);
    if (!isset(image_submission_reject_reasons()[$reason])) {
        return ['ok' => false, 'error' => 'Choose a valid rejection reason.'];
    }
    if ($note === '') {
        return ['ok' => false, 'error' => 'A note to the provider is required when rejecting.'];
    }

    $fresh = image_submission_get($pdo, $imageId);
    if ($fresh === null || (string)$fresh['review_status'] !== 'pending_review') {
        return ['ok' => false, 'error' => 'This photo has already been reviewed.'];
    }

    try {
        $upd = $pdo->prepare(
            "UPDATE venue_images
                SET review_status='rejected', review_reason=:reason, review_note=:note,
                    reviewed_by=:uid, reviewed_at=NOW()
              WHERE id=:id AND review_status='pending_review'"
        );
        $upd->execute([':reason' => $reason, ':note' => $note, ':uid' => $adminUserId, ':id' => $imageId]);
        if ($upd->rowCount() < 1) {
            return ['ok' => false, 'error' => 'This photo has already been reviewed.'];
        }
    } catch (Throwable $e) {
        error_log('image_submission_reject failed (image=' . $imageId . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong rejecting the photo. Please try again.'];
    }

    $mailOk = _image_submission_notify($fresh, 'rejected', image_submission_reject_reasons()[$reason], $note);
    audit_log($pdo, $adminUserId, 'reject', 'venue_image', $imageId,
        ['review_status' => 'pending_review'],
        ['review_status' => 'rejected', 'reason' => $reason, 'note' => $note, 'notified' => $mailOk]);

    $out = ['ok' => true];
    if (!$mailOk) { $out['warning'] = 'Rejected, but the provider notification email failed to send.'; }
    return $out;
}

/** Email the provider about a photo decision. Returns false (and logs) on no recipient / send failure. */
function _image_submission_notify(array $img, string $decision, string $reasonLabel, string $note): bool
{
    $to = trim((string)($img['uploader_email'] ?? '')) !== ''
        ? (string)$img['uploader_email'] : (string)($img['provider_email'] ?? '');
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('_image_submission_notify: no valid recipient for image ' . (int)($img['id'] ?? 0));
        return false;
    }
    $esc   = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $venue = (string)($img['venue_name'] ?? 'your venue');
    $link  = base_url('portal/venues/' . (int)($img['venue_id'] ?? 0) . '/images');

    if ($decision === 'approved') {
        $subject = 'Your photo is now live — ' . $venue;
        $intro   = 'Good news — a photo you submitted for <strong>' . $esc($venue)
                 . '</strong> has been reviewed and published to your listing.';
        $extra   = '';
    } else {
        $subject = 'A photo needs attention — ' . $venue;
        $intro   = 'A photo you submitted for <strong>' . $esc($venue)
                 . '</strong> couldn’t be added to your listing.';
        $extra   = '<p style="margin:16px 0;padding:12px 14px;background:#f4f1ea;border-radius:6px;">'
                 . '<strong>Reason:</strong> ' . $esc($reasonLabel) . '<br>'
                 . '<strong>Reviewer note:</strong><br>' . nl2br($esc($note)) . '</p>';
    }

    $body = '<div style="font-family:Arial,sans-serif;color:#0E1B2A;line-height:1.5;">'
          . '<h2 style="font-size:18px;">All The Venues — Provider Portal</h2>'
          . '<p>Hello,</p>'
          . '<p>' . $intro . '</p>' . $extra
          . '<p><a href="' . $esc($link) . '">View your venue photos</a></p>'
          . '<p style="color:#6b7b88;">— The All The Venues team</p></div>';

    return send_mail($to, $subject, $body);
}

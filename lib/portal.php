<?php
declare(strict_types=1);

/**
 * #3 U-P3 — Provider portal data access. Every query is prepared and scoped by
 * the logged-in provider's partner_id (fail-closed ownership). Read-only for now
 * (editing is U-P4/U-P5). The SELECT deliberately EXCLUDES internal fields never
 * shown to providers: contact_name/email/phone (routing), commission_rate (admin),
 * and management_source / provider_assigned_by / provider_assigned_at (provenance).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venues.php';        // venue_images(), venue_layout_capacity(), option helpers
require_once __DIR__ . '/venue_admin.php';   // venue_admin_statuses()/_label() for status badges

/** Provider-safe venue columns (+ type/emirate labels). No internal fields. */
function _portal_venue_select(): string
{
    return "SELECT v.id, v.slug, v.name, v.status, v.venue_type_id, v.emirate_id,
                   v.area, v.address, v.indoor_outdoor, v.capacity_min, v.capacity_max,
                   v.pricing_level, v.minimum_spend, v.floor_area, v.floor_area_unit,
                   v.website, v.video_url, v.map_embed, v.is_featured, v.highlights,
                   v.description, v.best_for, v.facilities, v.food_beverage, v.av_support,
                   v.restrictions, v.packages, v.special_offer, v.created_at, v.updated_at,
                   vt.name AS venue_type_name, em.name AS emirate_name
            FROM venues v
            LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
            LEFT JOIN emirates    em ON em.id = v.emirate_id";
}

/**
 * #3 U-P4 — the SECURITY BOUNDARY: DB columns a provider may write LIVE from the
 * portal. Any field outside this list is ignored on save (validation iterates the
 * allowlist, never $_POST). NOT here (never portal-writable): name, slug,
 * venue_type_id, emirate_id (→ change requests, U-P5); is_featured, is_verified,
 * status, partner_id (locked); contact_name/email/phone (internal). Layout
 * capacities are LIVE-editable too, but via venue_layout_capacity_save().
 * @return string[]
 */
function portal_venue_live_columns(): array
{
    return array_merge(
        ['area', 'address', 'video_url', 'website', 'indoor_outdoor',
         'capacity_min', 'capacity_max', 'minimum_spend', 'pricing_level',
         'floor_area', 'floor_area_unit', 'map_embed'],
        venue_richtext_fields()   // description, best_for, highlights, facilities, …
    );
}

/**
 * #3 U-P5a — sensitive venue fields a provider may REQUEST to change (never edits
 * live). These write ONE venue_change_requests row (type='edit', status='pending')
 * holding the diff; the venue itself is untouched until admin approval (U-P5b).
 * @return string[]
 */
function portal_venue_request_fields(): array
{
    return ['name', 'slug', 'venue_type_id', 'emirate_id'];
}

/** The single pending 'edit' request for an owned venue (or null). Owner-scoped. */
function portal_pending_edit_request(PDO $pdo, int $venueId, int $partnerId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM venue_change_requests
         WHERE venue_id = :vid AND partner_id = :pid AND type = 'edit' AND status = 'pending'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Insert a pending edit request. $changes = ['field'=>['old'=>..,'new'=>..], ...]
 * (changed fields only). Returns the new request id.
 */
function portal_create_edit_request(PDO $pdo, int $venueId, int $partnerId, int $userId, array $changes): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO venue_change_requests
            (venue_id, partner_id, submitted_by, type, proposed_changes_json, status)
         VALUES (:vid, :pid, :uid, 'edit', :json, 'pending')"
    );
    $stmt->execute([
        ':vid'  => $venueId,
        ':pid'  => $partnerId,
        ':uid'  => $userId,
        ':json' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * #3 U-P5b — the most recent request for an owned venue, ANY status (for the
 * provider-side decision reflection: needs_changes / rejected banners). Owner-scoped.
 */
function portal_latest_request(PDO $pdo, int $venueId, int $partnerId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM venue_change_requests
         WHERE venue_id = :vid AND partner_id = :pid AND type = 'edit'
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Withdraw the provider's OWN pending request. True if a row was withdrawn. */
function portal_withdraw_request(PDO $pdo, int $requestId, int $venueId, int $partnerId): bool
{
    $stmt = $pdo->prepare(
        "UPDATE venue_change_requests
         SET status = 'withdrawn', updated_at = NOW()
         WHERE id = :rid AND venue_id = :vid AND partner_id = :pid AND status = 'pending'"
    );
    $stmt->execute([':rid' => $requestId, ':vid' => $venueId, ':pid' => $partnerId]);
    return $stmt->rowCount() > 0;
}

/**
 * #3 U-P6a — fields a provider may set when CREATING a new venue. Identity fields
 * (name/type/emirate) ARE allowed here (unlike the live-edit allowlist) because the
 * whole venue is born status='pending' and cannot go live until an admin approves.
 * NOT settable here (forced by portal_create_new_venue): slug (auto), status
 * ('pending'), is_featured/is_verified (0), partner_id (the provider), contact_*
 * (internal), commission (n/a).
 * @return string[]
 */
function portal_new_venue_fields(): array
{
    return array_merge(
        ['name', 'venue_type_id', 'emirate_id', 'area', 'address', 'indoor_outdoor',
         'capacity_min', 'capacity_max', 'minimum_spend', 'pricing_level',
         'floor_area', 'floor_area_unit', 'website', 'video_url', 'map_embed'],
        venue_richtext_fields()
    );
}

/**
 * Create a pending, provider-owned venue + its 'new_venue' change request in one
 * transaction. $clean holds the already-validated fields keyed by column (name
 * required). Returns [venueId, requestId]. Throws on failure (caller handles).
 */
function portal_create_new_venue(PDO $pdo, int $partnerId, int $userId, array $clean): array
{
    // Auto unique slug from the name.
    $base = slugify((string)($clean['name'] ?? '')) ?: 'venue';
    $slug = $base; $n = 2;
    while (!venue_slug_available($pdo, $slug, 0)) { $slug = $base . '-' . $n; $n++; }

    try {
        $pdo->beginTransaction();

        $insCols = array_keys($clean);
        $colSql  = implode(', ', $insCols);
        $valSql  = implode(', ', array_map(static fn($c) => ':' . $c, $insCols));

        $sql = "INSERT INTO venues ($colSql, slug, status, partner_id, is_featured, is_verified,
                    management_source, provider_assigned_at, provider_assigned_by, created_at)
                VALUES ($valSql, :slug, 'pending', :pid, 0, 0, 'provider_created', NOW(), :uid, NOW())";
        $stmt = $pdo->prepare($sql);
        foreach ($clean as $c => $val) { $stmt->bindValue(':' . $c, $val); }
        $stmt->bindValue(':slug', $slug);
        $stmt->bindValue(':pid', $partnerId, PDO::PARAM_INT);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $venueId = (int)$pdo->lastInsertId();

        // Snapshot of what was submitted (for the admin new-venue review, U-P6b).
        $snapshot = $clean;
        $snapshot['slug'] = $slug;
        $cr = $pdo->prepare(
            "INSERT INTO venue_change_requests
                (venue_id, partner_id, submitted_by, type, proposed_changes_json, status)
             VALUES (:vid, :pid, :uid, 'new_venue', :json, 'pending')"
        );
        $cr->execute([
            ':vid'  => $venueId, ':pid' => $partnerId, ':uid' => $userId,
            ':json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $requestId = (int)$pdo->lastInsertId();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }

    audit_log($pdo, $userId, 'create', 'venue', $venueId, null, $clean);
    audit_log($pdo, $userId, 'create', 'change_request', $requestId, null, ['type' => 'new_venue']);
    return [$venueId, $requestId];
}

/** All venues owned by a provider (every status), newest-touched first. [] if none. */
function portal_my_venues(PDO $pdo, int $partnerId): array
{
    $stmt = $pdo->prepare(_portal_venue_select()
        . ' WHERE v.partner_id = :pid ORDER BY v.updated_at DESC, v.id DESC');
    $stmt->execute([':pid' => $partnerId]);
    return $stmt->fetchAll();
}

/** One owned venue, or null if not found OR not owned (caller → 404). */
function portal_venue_for_partner(PDO $pdo, int $venueId, int $partnerId): ?array
{
    $stmt = $pdo->prepare(_portal_venue_select()
        . ' WHERE v.id = :vid AND v.partner_id = :pid LIMIT 1');
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Event-type names linked to a venue (ownership already verified by the caller). */
function portal_venue_event_types(PDO $pdo, int $venueId): array
{
    $stmt = $pdo->prepare(
        'SELECT et.name
         FROM venue_event_types vet JOIN event_types et ON et.id = vet.event_type_id
         WHERE vet.venue_id = :vid
         ORDER BY et.sort_order ASC, et.name ASC'
    );
    $stmt->execute([':vid' => $venueId]);
    return array_map(static fn($r) => (string)$r['name'], $stmt->fetchAll());
}

/* ==========================================================================
 * #3 U-P7a — provider venue-image uploads (submit + withdraw side).
 * Every query is owner-scoped via JOIN venues v ON v.id = vi.venue_id AND
 * v.partner_id = :pid (fail closed — a client id is never trusted). Uploads land
 * review_status='pending_review' + status='hidden' (invisible publicly, cannot
 * satisfy any publish gate) until an admin approves them in U-P7b. The provider's
 * rights confirmation (rights_confirmed*) is SEPARATE from ATV editorial approval
 * (#9 permission_status, admin-owned).
 * ======================================================================== */

/** Pending (awaiting-review) images the provider submitted for this owned venue. */
function portal_venue_images_pending(PDO $pdo, int $venueId, int $partnerId): array
{
    $stmt = $pdo->prepare(
        "SELECT vi.* FROM venue_images vi
         JOIN venues v ON v.id = vi.venue_id AND v.partner_id = :pid
         WHERE vi.venue_id = :vid AND vi.review_status = 'pending_review'
         ORDER BY vi.id DESC"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    return $stmt->fetchAll();
}

/** Rejected images for this owned venue (the "Not approved" section; empty until U-P7b). */
function portal_venue_images_rejected(PDO $pdo, int $venueId, int $partnerId): array
{
    $stmt = $pdo->prepare(
        "SELECT vi.* FROM venue_images vi
         JOIN venues v ON v.id = vi.venue_id AND v.partner_id = :pid
         WHERE vi.venue_id = :vid AND vi.review_status = 'rejected'
         ORDER BY vi.id DESC"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    return $stmt->fetchAll();
}

/** Live (public) images for this owned venue — read-only display. */
function portal_venue_images_live(PDO $pdo, int $venueId, int $partnerId): array
{
    $stmt = $pdo->prepare(
        "SELECT vi.* FROM venue_images vi
         JOIN venues v ON v.id = vi.venue_id AND v.partner_id = :pid
         WHERE vi.venue_id = :vid AND vi.status = 'active' AND vi.review_status = 'approved'
         ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    return $stmt->fetchAll();
}

/**
 * Add ONE provider-submitted image as pending review (hidden). Re-verifies
 * ownership; captures original filename + dimensions + size BEFORE upload
 * re-encodes; records the provider's rights confirmation. Returns
 * ['ok'=>bool, 'id'=>int] or ['ok'=>false,'error'=>string].
 */
function portal_add_pending_image(PDO $pdo, int $venueId, int $partnerId, int $userId,
                                  string $uploaderName, array $file, string $alt): array
{
    // Fail-closed ownership re-check.
    $own = $pdo->prepare('SELECT 1 FROM venues WHERE id = :vid AND partner_id = :pid');
    $own->execute([':vid' => $venueId, ':pid' => $partnerId]);
    if ($own->fetchColumn() === false) {
        return ['ok' => false, 'error' => 'Not found.'];
    }

    // Capture provenance metadata BEFORE upload_venue_image renames/re-encodes.
    $originalName = mb_substr((string)($file['name'] ?? ''), 0, 255);
    $fileSize     = (int)($file['size'] ?? 0);
    $width = $height = null;
    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp !== '' && is_uploaded_file($tmp)) {
        $dim = @getimagesize($tmp);
        if (is_array($dim)) { $width = (int)$dim[0]; $height = (int)$dim[1]; }
    }

    $res = upload_venue_image($file, $venueId);
    if (empty($res['ok'])) {
        return $res;   // verbatim upload-lib error
    }

    // sort_order over ALL statuses to avoid collisions with live/hidden rows.
    $so = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM venue_images WHERE venue_id = :vid');
    $so->execute([':vid' => $venueId]);
    $sort = (int)$so->fetchColumn();

    $ins = $pdo->prepare(
        "INSERT INTO venue_images
            (venue_id, file_path, thumb_path, alt_text, is_primary, sort_order, status,
             review_status, rights_confirmed, rights_confirmed_by, rights_confirmed_at,
             uploaded_by, image_source, original_filename, file_size, img_width, img_height)
         VALUES
            (:vid, :fp, :tp, :alt, 0, :sort, 'hidden',
             'pending_review', 1, :rby, NOW(),
             :uid, 'provider_upload', :ofn, :sz, :w, :h)"
    );
    $ins->execute([
        ':vid'  => $venueId,
        ':fp'   => $res['file_path'],
        ':tp'   => $res['thumb_path'] !== '' ? $res['thumb_path'] : null,
        ':alt'  => $alt !== '' ? $alt : null,
        ':sort' => $sort,
        ':rby'  => $uploaderName !== '' ? $uploaderName : null,
        ':uid'  => $userId ?: null,
        ':ofn'  => $originalName !== '' ? $originalName : null,
        ':sz'   => $fileSize ?: null,
        ':w'    => $width,
        ':h'    => $height,
    ]);
    $newId = (int)$pdo->lastInsertId();

    audit_log($pdo, $userId ?: null, 'create', 'venue_image', $newId, null,
        ['venue_id' => $venueId, 'review_status' => 'pending_review', 'source' => 'provider_upload']);

    return ['ok' => true, 'id' => $newId];
}

/**
 * Withdraw a still-pending provider image (owner-scoped). Marks it withdrawn,
 * unlinks the files (fail-safe), audits. Returns false for a non-owned / already-
 * reviewed image (no-op, no leak).
 */
function portal_withdraw_image(PDO $pdo, int $imageId, int $venueId, int $partnerId): bool
{
    $sel = $pdo->prepare(
        "SELECT vi.file_path, vi.thumb_path FROM venue_images vi
         JOIN venues v ON v.id = vi.venue_id AND v.partner_id = :pid
         WHERE vi.id = :iid AND vi.venue_id = :vid AND vi.review_status = 'pending_review' LIMIT 1"
    );
    $sel->execute([':iid' => $imageId, ':vid' => $venueId, ':pid' => $partnerId]);
    $row = $sel->fetch();
    if ($row === false) {
        return false;
    }

    $upd = $pdo->prepare(
        "UPDATE venue_images
         SET review_status = 'withdrawn', reviewed_at = NOW()
         WHERE id = :iid AND venue_id = :vid AND review_status = 'pending_review'
           AND venue_id IN (SELECT id FROM venues WHERE partner_id = :pid)"
    );
    $upd->execute([':iid' => $imageId, ':vid' => $venueId, ':pid' => $partnerId]);
    if ($upd->rowCount() < 1) {
        return false;
    }

    // Remove files from disk (fail-safe — a missing file is fine).
    foreach ([$row['file_path'] ?? '', $row['thumb_path'] ?? ''] as $rel) {
        $rel = trim((string)$rel);
        if ($rel !== '') {
            $abs = app_path($rel);
            if (is_file($abs)) { @unlink($abs); }
        }
    }

    audit_log($pdo, null, 'update', 'venue_image', $imageId,
        ['review_status' => 'pending_review'], ['venue_id' => $venueId, 'review_status' => 'withdrawn']);
    return true;
}

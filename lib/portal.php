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
        // #13 — 'best_for' is ATV-editorial, not partner-writable (kept in admin + public).
        array_values(array_diff(venue_richtext_fields(), ['best_for']))
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
        // #13 — 'best_for' is ATV-editorial, not partner-writable.
        array_values(array_diff(venue_richtext_fields(), ['best_for']))
    );
}

/**
 * #15 — Create a provider-owned venue as a DRAFT (private, editable). The admin
 * review request is NOT created here — it is created only when the provider
 * SUBMITS (portal_submit_venue_for_review), which requires ≥1 photo. $clean holds
 * the already-validated fields keyed by column (name required). Returns the venue
 * id. Throws on failure (caller handles).
 */
function portal_create_new_venue(PDO $pdo, int $partnerId, int $userId, array $clean): int
{
    // Auto unique slug from the name.
    $base = slugify((string)($clean['name'] ?? '')) ?: 'venue';
    $slug = $base; $n = 2;
    while (!venue_slug_available($pdo, $slug, 0)) { $slug = $base . '-' . $n; $n++; }

    $insCols = array_keys($clean);
    $colSql  = implode(', ', $insCols);
    $valSql  = implode(', ', array_map(static fn($c) => ':' . $c, $insCols));

    $sql = "INSERT INTO venues ($colSql, slug, status, partner_id, is_featured, is_verified,
                management_source, provider_assigned_at, provider_assigned_by, created_at)
            VALUES ($valSql, :slug, 'draft', :pid, 0, 0, 'provider_created', NOW(), :uid, NOW())";
    $stmt = $pdo->prepare($sql);
    foreach ($clean as $c => $val) { $stmt->bindValue(':' . $c, $val); }
    $stmt->bindValue(':slug', $slug);
    $stmt->bindValue(':pid', $partnerId, PDO::PARAM_INT);
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $venueId = (int)$pdo->lastInsertId();

    audit_log($pdo, $userId, 'create', 'venue', $venueId, null, array_merge($clean, ['status' => 'draft']));
    return $venueId;
}

/**
 * PU-D1-fix — the REQUIRED-details set (single source of truth), mirroring
 * cr_newvenue_completeness minus slug/provider (auto/forced) and the photo
 * (handled separately). Returns the labels still MISSING; [] = details complete.
 * @return string[]
 */
function portal_venue_missing_required(array $venue, int $eventTypeCount): array
{
    $nonEmpty = static fn($v): bool => $v !== null && trim((string)$v) !== '';
    $posInt   = static fn($v): bool => (int)$v > 0;

    $checks = [
        ['Name',                    $nonEmpty($venue['name'] ?? null)],
        ['Primary emirate',         $posInt($venue['emirate_id'] ?? 0)],
        ['Area or address',         $nonEmpty($venue['area'] ?? null) || $nonEmpty($venue['address'] ?? null)],
        ['Venue type',              $posInt($venue['venue_type_id'] ?? 0)],
        ['At least one event type', $eventTypeCount >= 1],
        ['Capacity',                ((int)($venue['capacity_min'] ?? 0) > 0) || ((int)($venue['capacity_max'] ?? 0) > 0)],
        ['Description',             $nonEmpty($venue['description'] ?? null)],
    ];
    $missing = [];
    foreach ($checks as [$label, $ok]) { if (!$ok) { $missing[] = $label; } }
    return $missing;
}

/**
 * #15 — Submit an owned DRAFT (or needs_changes) venue for review. Backstop gate:
 * required details must be complete AND ≥1 photo must exist (blocks an incomplete
 * submit even if the UI is bypassed). On success flips the venue to 'pending' and
 * creates the new_venue change request (the U-P6b admin flow takes it from there).
 * @return array{ok:bool,error?:string}
 */
function portal_submit_venue_for_review(PDO $pdo, int $venueId, int $partnerId, int $userId): array
{
    $vs = $pdo->prepare('SELECT * FROM venues WHERE id = :vid AND partner_id = :pid LIMIT 1');
    $vs->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $venue = $vs->fetch();
    if ($venue === false) {
        return ['ok' => false, 'error' => 'Not found.'];
    }
    if (!in_array((string)$venue['status'], ['draft', 'needs_changes'], true)) {
        return ['ok' => false, 'error' => 'This venue has already been submitted.'];
    }

    // BACKSTOP GATE — required details complete AND ≥1 photo (any review_status).
    $etCount = count(portal_venue_event_type_ids($pdo, $venueId));
    $missing = portal_venue_missing_required($venue, $etCount);
    if ($missing !== []) {
        return ['ok' => false, 'error' => 'Please complete these details before submitting: ' . implode(', ', $missing) . '.'];
    }
    $ic = $pdo->prepare('SELECT COUNT(*) FROM venue_images WHERE venue_id = :vid');
    $ic->execute([':vid' => $venueId]);
    if ((int)$ic->fetchColumn() < 1) {
        return ['ok' => false, 'error' => 'Add at least one photo before submitting for review.'];
    }

    // Snapshot the venue's submittable fields for the admin review request.
    $snapCols = array_merge(portal_new_venue_fields(), ['slug']);
    $snapshot = [];
    foreach ($snapCols as $c) { if (array_key_exists($c, $venue)) { $snapshot[$c] = $venue[$c]; } }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE venues SET status = 'pending' WHERE id = :vid AND partner_id = :pid AND status IN ('draft','needs_changes')")
            ->execute([':vid' => $venueId, ':pid' => $partnerId]);
        $cr = $pdo->prepare(
            "INSERT INTO venue_change_requests
                (venue_id, partner_id, submitted_by, type, proposed_changes_json, status)
             VALUES (:vid, :pid, :uid, 'new_venue', :json, 'pending')"
        );
        $cr->execute([
            ':vid' => $venueId, ':pid' => $partnerId, ':uid' => $userId,
            ':json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $requestId = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('portal_submit_venue_for_review failed (venue=' . $venueId . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong submitting your venue. Please try again.'];
    }

    audit_log($pdo, $userId, 'submit', 'venue', $venueId, ['status' => 'draft'], ['status' => 'pending', 'request_id' => $requestId]);
    return ['ok' => true];
}

/**
 * PU-D1-fix — permanently delete an owned DRAFT venue (only a draft — never a
 * pending/needs_changes/published/archived one). Owner + status='draft' guarded
 * in the SELECT, so a forged id on any other status is a no-op. Deletes the
 * junction/layout/image rows + the venue row in a transaction, then unlinks the
 * image files (fail-safe). Returns true iff a draft was deleted.
 */
function portal_delete_draft_venue(PDO $pdo, int $venueId, int $partnerId, int $userId): bool
{
    $sel = $pdo->prepare("SELECT name, slug FROM venues WHERE id = :vid AND partner_id = :pid AND status = 'draft' LIMIT 1");
    $sel->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $venue = $sel->fetch();
    if ($venue === false) {
        return false;   // not owned / not a draft — never delete
    }

    // Collect image files to unlink after a successful commit.
    $imgs = $pdo->prepare('SELECT file_path, thumb_path FROM venue_images WHERE venue_id = :vid');
    $imgs->execute([':vid' => $venueId]);
    $files = [];
    foreach ($imgs->fetchAll() as $r) {
        foreach ([$r['file_path'] ?? '', $r['thumb_path'] ?? ''] as $p) { if (trim((string)$p) !== '') { $files[] = (string)$p; } }
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM venue_event_types WHERE venue_id = :vid')->execute([':vid' => $venueId]);
        $pdo->prepare('DELETE FROM venue_layout_capacity WHERE venue_id = :vid')->execute([':vid' => $venueId]);
        $pdo->prepare('DELETE FROM venue_images WHERE venue_id = :vid')->execute([':vid' => $venueId]);
        // Re-scope the final delete to draft + owner (defence in depth).
        $del = $pdo->prepare("DELETE FROM venues WHERE id = :vid AND partner_id = :pid AND status = 'draft'");
        $del->execute([':vid' => $venueId, ':pid' => $partnerId]);
        if ($del->rowCount() < 1) {
            $pdo->rollBack();
            return false;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('portal_delete_draft_venue failed (venue=' . $venueId . '): ' . $e->getMessage());
        return false;
    }

    // Unlink files from disk (fail-safe — a missing file is fine).
    foreach ($files as $rel) {
        $abs = app_path($rel);
        if (is_file($abs)) { @unlink($abs); }
    }

    audit_log($pdo, $userId ?: null, 'delete', 'venue', $venueId,
        ['name' => $venue['name'], 'slug' => $venue['slug'], 'status' => 'draft'], null);
    return true;
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
 * #3 U-P9b — provider event-type editor (governed). Providers may set
 * venue_event_types DIRECTLY only while a venue is NOT public (status !=
 * 'published') — event types drive public search / event-type pages / SEO /
 * enquiry routing, so a published venue's tags are admin-approved only. The save
 * function is the server-side guard (blocks even a forged POST on a published or
 * non-owned venue).
 * ======================================================================== */

/** The "Primary" event-type slugs (everything else renders under "Additional"). */
function portal_event_type_primary_slugs(): array
{
    return ['wedding', 'corporate-event', 'conference', 'product-launch',
            'private-party', 'exhibition', 'gala-dinner', 'yacht-event'];
}

/** Current event-type ids linked to a venue (for form prefill). */
function portal_venue_event_type_ids(PDO $pdo, int $venueId): array
{
    $stmt = $pdo->prepare('SELECT event_type_id FROM venue_event_types WHERE venue_id = :vid');
    $stmt->execute([':vid' => $venueId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Replace a venue's event-type tags (owner-scoped). Returns false — writing
 * nothing — if the venue isn't owned by $partnerId OR is 'published' (governance
 * gate). Sanitizes ids to distinct active event_types. An empty set is allowed
 * (clears tags; the publish gate still needs ≥1). Transactional + audited.
 */
function portal_venue_event_types_save(PDO $pdo, int $venueId, int $partnerId, array $ids): bool
{
    $vs = $pdo->prepare('SELECT status FROM venues WHERE id = :vid AND partner_id = :pid LIMIT 1');
    $vs->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $status = $vs->fetchColumn();
    if ($status === false) { return false; }                 // not owned
    if ((string)$status === 'published') { return false; }   // governance: published tags are admin-only

    // Sanitize → distinct positive ints that exist as ACTIVE event types.
    $wanted = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
    $valid  = [];
    if ($wanted) {
        $ph = implode(',', array_fill(0, count($wanted), '?'));
        $q  = $pdo->prepare("SELECT id FROM event_types WHERE active = 1 AND id IN ($ph)");
        $q->execute($wanted);
        $valid = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
    }

    $old = portal_venue_event_type_ids($pdo, $venueId);

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM venue_event_types WHERE venue_id = :vid')->execute([':vid' => $venueId]);
        if ($valid) {
            $ins = $pdo->prepare('INSERT IGNORE INTO venue_event_types (venue_id, event_type_id) VALUES (:vid, :eid)');
            foreach ($valid as $eid) { $ins->execute([':vid' => $venueId, ':eid' => $eid]); }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('portal_venue_event_types_save failed (venue=' . $venueId . '): ' . $e->getMessage());
        return false;
    }

    sort($old); $newSorted = $valid; sort($newSorted);
    if ($old !== $newSorted) {
        audit_log($pdo, (int)(auth_user()['id'] ?? 0) ?: null, 'update', 'venue_event_types', $venueId, $old, $valid);
    }
    return true;
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

/* ==========================================================================
 * #3 U-P8a — provider "claim an existing venue" (submit side). Claims are
 * venue_change_requests rows type='claim' targeting a venue the provider does NOT
 * already manage. proposed_changes_json holds a {claim:{...}} object. Every query
 * is claimant-scoped (partner_id); the target is re-validated as claimable on
 * write. The venue itself is never modified here (admin approves in U-P8b).
 * ======================================================================== */

/** Escape LIKE wildcards in a user search term. */
function _portal_like(string $q): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
}

/**
 * Published venues the provider can claim (not already theirs), by name search.
 * Each row carries a badge signal (partner_id NULL = unassigned, else contested)
 * and has_open_claim (a pending/needs_changes claim by THIS provider).
 */
function portal_claimable_search(PDO $pdo, int $partnerId, string $q, int $limit = 20): array
{
    $q = trim($q);
    if ($q === '') { return []; }
    $limit = max(1, min(50, $limit));
    $stmt = $pdo->prepare(
        "SELECT v.id, v.name, v.slug, v.area, v.partner_id,
                em.name AS emirate, vt.name AS venue_type_name,
                EXISTS(SELECT 1 FROM venue_change_requests cr
                       WHERE cr.venue_id = v.id AND cr.partner_id = :pid1 AND cr.type = 'claim'
                         AND cr.status IN ('pending','needs_changes')) AS has_open_claim
         FROM venues v
         LEFT JOIN emirates    em ON em.id = v.emirate_id
         LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
         WHERE v.status = 'published'
           AND (v.partner_id IS NULL OR v.partner_id <> :pid2)
           AND v.name LIKE :q
         ORDER BY v.name ASC
         LIMIT " . $limit
    );
    $stmt->execute([':pid1' => $partnerId, ':pid2' => $partnerId, ':q' => _portal_like($q)]);
    return $stmt->fetchAll();
}

/** One claimable venue by id (or null if own / unpublished / nonexistent). */
function portal_claim_target(PDO $pdo, int $venueId, int $partnerId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT v.id, v.name, v.slug, v.area, v.partner_id,
                em.name AS emirate, vt.name AS venue_type_name,
                (v.partner_id IS NOT NULL) AS contested
         FROM venues v
         LEFT JOIN emirates    em ON em.id = v.emirate_id
         LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
         WHERE v.id = :vid AND v.status = 'published'
           AND (v.partner_id IS NULL OR v.partner_id <> :pid)
         LIMIT 1"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** The provider's existing open (pending/needs_changes) claim for a venue, or null. */
function portal_open_claim(PDO $pdo, int $venueId, int $partnerId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM venue_change_requests
         WHERE venue_id = :vid AND partner_id = :pid AND type = 'claim'
           AND status IN ('pending','needs_changes')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':vid' => $venueId, ':pid' => $partnerId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Create a pending claim. Re-checks the target is claimable AND there is no open
 * claim (dup guard) — throws on failure (caller handles). $clean holds the
 * validated role/work_email/message/proof_url/requester_name. Returns the new id.
 */
function portal_create_claim(PDO $pdo, int $venueId, int $partnerId, int $userId, array $clean): int
{
    $target = portal_claim_target($pdo, $venueId, $partnerId);
    if ($target === null) {
        throw new RuntimeException('venue not claimable');
    }
    if (portal_open_claim($pdo, $venueId, $partnerId) !== null) {
        throw new RuntimeException('open claim exists');
    }

    $payload = ['claim' => [
        'role'              => (string)($clean['role'] ?? ''),
        'work_email'        => (string)($clean['work_email'] ?? ''),
        'message'           => (string)($clean['message'] ?? ''),
        'proof_url'         => (string)($clean['proof_url'] ?? ''),
        'contested'         => (bool)$target['contested'],
        'requester_name'    => (string)($clean['requester_name'] ?? ''),
        'target_venue_name' => (string)$target['name'],
        'target_venue_slug' => (string)$target['slug'],
    ]];

    $stmt = $pdo->prepare(
        "INSERT INTO venue_change_requests
            (venue_id, partner_id, submitted_by, type, proposed_changes_json, status)
         VALUES (:vid, :pid, :uid, 'claim', :json, 'pending')"
    );
    $stmt->execute([
        ':vid' => $venueId, ':pid' => $partnerId, ':uid' => $userId,
        ':json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    $id = (int)$pdo->lastInsertId();

    audit_log($pdo, $userId ?: null, 'create', 'change_request', $id, null,
        ['type' => 'claim', 'venue' => $venueId, 'contested' => (bool)$target['contested']]);
    return $id;
}

/** All claims by this provider (any status), newest first, with venue name. */
function portal_my_claims(PDO $pdo, int $partnerId): array
{
    $stmt = $pdo->prepare(
        "SELECT cr.*, v.name AS venue_name, v.slug AS venue_slug
         FROM venue_change_requests cr
         LEFT JOIN venues v ON v.id = cr.venue_id
         WHERE cr.partner_id = :pid AND cr.type = 'claim'
         ORDER BY cr.id DESC"
    );
    $stmt->execute([':pid' => $partnerId]);
    return $stmt->fetchAll();
}

/** Withdraw the provider's OWN pending claim. True if a row was withdrawn. */
function portal_withdraw_claim(PDO $pdo, int $requestId, int $partnerId): bool
{
    $stmt = $pdo->prepare(
        "UPDATE venue_change_requests
         SET status = 'withdrawn', updated_at = NOW()
         WHERE id = :rid AND partner_id = :pid AND type = 'claim' AND status = 'pending'"
    );
    $stmt->execute([':rid' => $requestId, ':pid' => $partnerId]);
    if ($stmt->rowCount() < 1) { return false; }
    audit_log($pdo, null, 'withdraw', 'change_request', $requestId,
        ['status' => 'pending'], ['status' => 'withdrawn']);
    return true;
}

/**
 * Add proof to a needs_changes claim (admin asked for it): merge new message/
 * proof_url into the claim JSON and set status back to 'pending'. Owner-scoped +
 * type='claim' + status='needs_changes' guard. Returns false if not applicable.
 */
function portal_add_claim_proof(PDO $pdo, int $requestId, int $partnerId, array $clean): bool
{
    $sel = $pdo->prepare(
        "SELECT proposed_changes_json FROM venue_change_requests
         WHERE id = :rid AND partner_id = :pid AND type = 'claim' AND status = 'needs_changes' LIMIT 1"
    );
    $sel->execute([':rid' => $requestId, ':pid' => $partnerId]);
    $row = $sel->fetch();
    if ($row === false) { return false; }

    $data = json_decode((string)$row['proposed_changes_json'], true);
    if (!is_array($data)) { $data = []; }
    if (!isset($data['claim']) || !is_array($data['claim'])) { $data['claim'] = []; }
    $msg   = (string)($clean['message'] ?? '');
    $proof = (string)($clean['proof_url'] ?? '');
    if ($msg !== '')   { $data['claim']['message']   = $msg; }
    if ($proof !== '') { $data['claim']['proof_url'] = $proof; }

    $upd = $pdo->prepare(
        "UPDATE venue_change_requests
         SET proposed_changes_json = :json, status = 'pending', updated_at = NOW()
         WHERE id = :rid AND partner_id = :pid AND type = 'claim' AND status = 'needs_changes'"
    );
    $upd->execute([
        ':json' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':rid'  => $requestId, ':pid' => $partnerId,
    ]);
    if ($upd->rowCount() < 1) { return false; }
    audit_log($pdo, null, 'update', 'change_request', $requestId,
        ['status' => 'needs_changes'], ['status' => 'pending', 'added_proof' => $proof !== '']);
    return true;
}

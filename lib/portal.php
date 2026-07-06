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

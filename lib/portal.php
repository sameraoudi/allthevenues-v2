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

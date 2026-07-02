<?php
declare(strict_types=1);

/**
 * Venue catalogue data access + taxonomy/enum helpers (U2).
 *
 * Public browse only: every query enforces status='published' in SQL, not
 * just in the UI. All filters are bound parameters (prepared statements) —
 * no request data is ever concatenated into SQL.
 */

require_once __DIR__ . '/../config/db.php';

/* ===========================================================================
 * Fixed enums (kept in app config per docs/ATV-SCHEMA.md §1 — not in tables)
 * =========================================================================== */

/** Guest-count bands → the minimum capacity a venue must offer to qualify. */
function venue_guest_bands(): array
{
    // value => [label, min_capacity_required]
    return [
        'lt25'      => ['Up to 25 guests',   1],
        '25-50'     => ['25–50 guests',      25],
        '51-100'    => ['51–100 guests',     51],
        '101-200'   => ['101–200 guests',    101],
        '201-500'   => ['201–500 guests',    201],
        '501-1000'  => ['501–1000 guests',   501],
        '1000plus'  => ['1000+ guests',      1000],
    ];
}

/** Budget / pricing_level options (must match values written by U1b). */
function venue_pricing_levels(): array
{
    return [
        'Price on request',
        'Budget-friendly',
        'Mid-range',
        'Premium',
        'Luxury',
    ];
}

/** indoor_outdoor enum → label. */
function venue_indoor_outdoor_options(): array
{
    return [
        'indoor'  => 'Indoor',
        'outdoor' => 'Outdoor',
        'both'    => 'Indoor & Outdoor',
    ];
}

/* ===========================================================================
 * Taxonomy loaders (for filter controls)
 * =========================================================================== */

function venue_event_types(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name, slug FROM event_types WHERE active=1 ORDER BY sort_order, name'
    )->fetchAll();
}

function venue_types_all(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name, slug FROM venue_types WHERE active=1 ORDER BY sort_order, name'
    )->fetchAll();
}

function venue_emirates(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, name, slug FROM emirates WHERE active=1 ORDER BY sort_order, name'
    )->fetchAll();
}

/* ===========================================================================
 * Filter normalisation
 *
 * Accepts raw $_GET, returns a clean map of only the recognised filters.
 * Slug filters are validated to a safe charset (defence-in-depth; they are
 * bound regardless). Enum filters are whitelisted against known values.
 * =========================================================================== */

function venue_normalise_filters(array $in): array
{
    $out = [];

    foreach (['event_type', 'venue_type', 'emirate'] as $k) {
        $v = trim((string)($in[$k] ?? ''));
        if ($v !== '' && preg_match('/^[a-z0-9-]{1,191}$/', $v)) {
            $out[$k] = $v;
        }
    }

    $guest = trim((string)($in['guest_count'] ?? ''));
    if ($guest !== '' && isset(venue_guest_bands()[$guest])) {
        $out['guest_count'] = $guest;
    }

    $budget = trim((string)($in['budget'] ?? ''));
    if ($budget !== '' && in_array($budget, venue_pricing_levels(), true)) {
        $out['budget'] = $budget;
    }

    $io = trim((string)($in['indoor_outdoor'] ?? ''));
    if ($io !== '' && isset(venue_indoor_outdoor_options()[$io])) {
        $out['indoor_outdoor'] = $io;
    }

    return $out;
}

/**
 * Build the WHERE clause fragment + bound params for a filter set.
 * Returns [sql_fragment, params]. sql_fragment always starts with a leading
 * " AND " for each active filter (or empty string). Uses self-contained
 * subqueries so the COUNT and SELECT queries need no extra JOINs.
 */
function venue_filter_sql(array $f): array
{
    $sql = '';
    $params = [];

    if (isset($f['venue_type'])) {
        $sql .= ' AND v.venue_type_id = (SELECT id FROM venue_types WHERE slug = :venue_type)';
        $params[':venue_type'] = $f['venue_type'];
    }
    if (isset($f['emirate'])) {
        $sql .= ' AND v.emirate_id = (SELECT id FROM emirates WHERE slug = :emirate)';
        $params[':emirate'] = $f['emirate'];
    }
    if (isset($f['event_type'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM venue_event_types vet
                              JOIN event_types et ON et.id = vet.event_type_id
                              WHERE vet.venue_id = v.id AND et.slug = :event_type)';
        $params[':event_type'] = $f['event_type'];
    }
    if (isset($f['indoor_outdoor'])) {
        $sql .= ' AND v.indoor_outdoor = :indoor_outdoor';
        $params[':indoor_outdoor'] = $f['indoor_outdoor'];
    }
    if (isset($f['budget'])) {
        $sql .= ' AND v.pricing_level = :budget';
        $params[':budget'] = $f['budget'];
    }
    if (isset($f['guest_count'])) {
        $bands = venue_guest_bands();
        $sql .= ' AND v.capacity_max IS NOT NULL AND v.capacity_max >= :guest_min';
        $params[':guest_min'] = (int)$bands[$f['guest_count']][1];
    }

    return [$sql, $params];
}

/* ===========================================================================
 * Listing query (published only) + count, with pagination
 * =========================================================================== */

/**
 * @return array{rows: array, total: int}
 */
function venue_list(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$where, $params] = venue_filter_sql($filters);

    // Total (for pagination + result count).
    $countSql = 'SELECT COUNT(*) FROM venues v WHERE v.status = :status' . $where;
    $stmt = $pdo->prepare($countSql);
    $stmt->execute([':status' => 'published'] + $params);
    $total = (int)$stmt->fetchColumn();

    $offset = ($page - 1) * $perPage;

    $sql = "SELECT
                v.id, v.slug, v.name, v.pricing_level, v.capacity_max, v.capacity_min,
                v.is_featured, v.indoor_outdoor, v.best_for, v.description,
                vt.name AS venue_type_name,
                e.name  AS emirate_name, v.area,
                p.org_name AS partner_name,
                (SELECT vi.file_path FROM venue_images vi
                   WHERE vi.venue_id = v.id AND vi.status = 'active'
                   ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC
                   LIMIT 1) AS primary_image
            FROM venues v
            LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
            LEFT JOIN emirates    e  ON e.id  = v.emirate_id
            LEFT JOIN partners    p  ON p.id  = v.partner_id
            WHERE v.status = :status" . $where . "
            ORDER BY v.is_featured DESC, v.name ASC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':status', 'published');
    foreach ($params as $k => $val) {
        $stmt->bindValue($k, $val);   // strings; slugs/enums
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/* ===========================================================================
 * Detail queries
 * =========================================================================== */

function venue_by_slug(PDO $pdo, string $slug): ?array
{
    $sql = "SELECT v.*,
                   vt.name AS venue_type_name, vt.slug AS venue_type_slug,
                   e.name  AS emirate_name,    e.slug  AS emirate_slug,
                   p.org_name AS partner_name, p.slug AS partner_slug
            FROM venues v
            LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
            LEFT JOIN emirates    e  ON e.id  = v.emirate_id
            LEFT JOIN partners    p  ON p.id  = v.partner_id
            WHERE v.slug = :slug AND v.status = 'published'
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function venue_images(PDO $pdo, int $venueId): array
{
    $stmt = $pdo->prepare(
        "SELECT file_path, alt_text, is_primary, sort_order
         FROM venue_images
         WHERE venue_id = :id AND status = 'active'
         ORDER BY is_primary DESC, sort_order ASC, id ASC"
    );
    $stmt->execute([':id' => $venueId]);
    return $stmt->fetchAll();
}

function venue_layout_capacity(PDO $pdo, int $venueId): array
{
    $stmt = $pdo->prepare(
        'SELECT layout_type, capacity
         FROM venue_layout_capacity
         WHERE venue_id = :id
         ORDER BY capacity DESC, layout_type ASC'
    );
    $stmt->execute([':id' => $venueId]);
    return $stmt->fetchAll();
}

/** Published venues sharing venue_type or emirate; excludes self. */
function venue_similar(PDO $pdo, array $venue, int $limit = 3): array
{
    $sql = "SELECT
                v.id, v.slug, v.name, v.pricing_level, v.capacity_max, v.capacity_min,
                v.is_featured, v.indoor_outdoor, v.best_for, v.description,
                vt.name AS venue_type_name,
                e.name  AS emirate_name, v.area,
                p.org_name AS partner_name,
                (SELECT vi.file_path FROM venue_images vi
                   WHERE vi.venue_id = v.id AND vi.status = 'active'
                   ORDER BY vi.is_primary DESC, vi.sort_order ASC, vi.id ASC
                   LIMIT 1) AS primary_image
            FROM venues v
            LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
            LEFT JOIN emirates    e  ON e.id  = v.emirate_id
            LEFT JOIN partners    p  ON p.id  = v.partner_id
            WHERE v.status = 'published'
              AND v.id <> :id
              AND (v.venue_type_id = :vt OR v.emirate_id = :em)
            ORDER BY (v.venue_type_id = :vt2) DESC, v.is_featured DESC, v.name ASC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', (int)$venue['id'], PDO::PARAM_INT);
    $stmt->bindValue(':vt', $venue['venue_type_id'] !== null ? (int)$venue['venue_type_id'] : 0, PDO::PARAM_INT);
    $stmt->bindValue(':vt2', $venue['venue_type_id'] !== null ? (int)$venue['venue_type_id'] : 0, PDO::PARAM_INT);
    $stmt->bindValue(':em', $venue['emirate_id'] !== null ? (int)$venue['emirate_id'] : 0, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Minimal published-venue lookup for the enquiry stub. */
function venue_name_by_id(PDO $pdo, int $id): ?string
{
    $stmt = $pdo->prepare(
        "SELECT name FROM venues WHERE id = :id AND status = 'published' LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $name = $stmt->fetchColumn();
    return $name === false ? null : (string)$name;
}

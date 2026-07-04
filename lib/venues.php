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
    // value => [label, band_min, band_max]  (band_max null = open-ended top band)
    return [
        'lt25'      => ['Up to 25 guests',   1,    25],
        '25-50'     => ['25–50 guests',      25,   50],
        '51-100'    => ['51–100 guests',     51,   100],
        '101-200'   => ['101–200 guests',    101,  200],
        '201-500'   => ['201–500 guests',    201,  500],
        '501-1000'  => ['501–1000 guests',   501,  1000],
        '1000plus'  => ['1000+ guests',      1000, null],
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

/**
 * Published-venue count per active event type: [slug => (int)n]. Only types
 * with at least one published venue appear (INNER JOINs), so the Event Types
 * page can gate on presence and show a count.
 */
function event_type_published_counts(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT et.slug AS slug, COUNT(DISTINCT vet.venue_id) AS n
         FROM event_types et
         JOIN venue_event_types vet ON vet.event_type_id = et.id
         JOIN venues v ON v.id = vet.venue_id AND v.status = 'published'
         WHERE et.active = 1
         GROUP BY et.slug"
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['slug']] = (int)$r['n']; }
    return $out;
}

/**
 * Published-venue count per emirate: [slug => (int)n]. Only emirates with at
 * least one published venue appear (INNER JOIN) — the Locations page gates on
 * presence and shows a count.
 */
function emirate_published_counts(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT e.slug, COUNT(*) AS n
         FROM venues v JOIN emirates e ON e.id = v.emirate_id
         WHERE v.status = 'published'
         GROUP BY e.slug"
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['slug']] = (int)$r['n']; }
    return $out;
}

/**
 * A representative published-venue image per emirate: [slug => file_path|null].
 * Used as the Locations tile cover fallback when no city image is provided.
 */
function emirate_cover_images(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT e.slug AS slug, (
            SELECT vi.file_path FROM venues v2
              JOIN venue_images vi ON vi.venue_id = v2.id AND vi.status = 'active'
             WHERE v2.emirate_id = e.id AND v2.status = 'published'
             ORDER BY v2.is_featured DESC, vi.is_primary DESC, v2.name ASC, vi.sort_order ASC, vi.id ASC
             LIMIT 1) AS cover
         FROM emirates e"
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['slug']] = $r['cover'] !== null ? (string)$r['cover'] : null; }
    return $out;
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

    // Free-text keyword (name / area / description).
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') { $out['q'] = mb_substr($q, 0, 100); }

    // Single-value slug filters.
    foreach (['event_type', 'venue_type'] as $k) {
        $v = trim((string)($in[$k] ?? ''));
        if ($v !== '' && preg_match('/^[a-z0-9-]{1,191}$/', $v)) {
            $out[$k] = $v;
        }
    }

    // Location: multi-select emirate (checkboxes). Accept array (emirate[])
    // or a comma-separated string (from chip/pagination links).
    $em = $in['emirate'] ?? [];
    $emList = is_array($em) ? $em : explode(',', (string)$em);
    $valid = [];
    foreach ($emList as $s) {
        $s = trim((string)$s);
        if ($s !== '' && preg_match('/^[a-z0-9-]{1,191}$/', $s) && !in_array($s, $valid, true)) {
            $valid[] = $s;
        }
    }
    if ($valid) {
        $out['emirate'] = $valid;
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

    $partner = (int)($in['partner'] ?? 0);
    if ($partner > 0) {
        $out['partner'] = $partner;
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

    if (isset($f['q'])) {
        // Match venue name, location (area + emirate name) and provider name —
        // NOT description. Self-contained EXISTS subqueries so COUNT + SELECT
        // need no extra JOINs. Distinct placeholders for the same value —
        // MySQL 5.7 + emulation off won't reuse a named placeholder (HY093 trap).
        $like = '%' . $f['q'] . '%';
        $sql .= ' AND (
            v.name LIKE :kw_name
            OR v.area LIKE :kw_area
            OR EXISTS (SELECT 1 FROM emirates e  WHERE e.id  = v.emirate_id AND e.name     LIKE :kw_em)
            OR EXISTS (SELECT 1 FROM partners pq WHERE pq.id = v.partner_id AND pq.org_name LIKE :kw_prov)
        )';
        $params[':kw_name'] = $like;
        $params[':kw_area'] = $like;
        $params[':kw_em']   = $like;
        $params[':kw_prov'] = $like;
    }
    if (isset($f['venue_type'])) {
        $sql .= ' AND v.venue_type_id = (SELECT id FROM venue_types WHERE slug = :venue_type)';
        $params[':venue_type'] = $f['venue_type'];
    }
    if (isset($f['emirate'])) {
        // Multi-select: v.emirate_id IN (subquery over the chosen slugs).
        $slugs = (array)$f['emirate'];
        $ph = [];
        foreach ($slugs as $i => $s) {
            $key = ':emirate' . $i;
            $ph[] = $key;
            $params[$key] = $s;
        }
        $sql .= ' AND v.emirate_id IN (SELECT id FROM emirates WHERE slug IN (' . implode(',', $ph) . '))';
    }
    if (isset($f['event_type'])) {
        $sql .= ' AND EXISTS (SELECT 1 FROM venue_event_types vet
                              JOIN event_types et ON et.id = vet.event_type_id
                              WHERE vet.venue_id = v.id AND et.slug = :event_type)';
        $params[':event_type'] = $f['event_type'];
    }
    if (isset($f['indoor_outdoor'])) {
        // 'both' venues are indoor AND outdoor, so include them when either
        // indoor or outdoor is selected (exact-match would drop them).
        if ($f['indoor_outdoor'] === 'both') {
            $sql .= ' AND v.indoor_outdoor = :indoor_outdoor';
            $params[':indoor_outdoor'] = 'both';
        } else {
            $sql .= ' AND v.indoor_outdoor IN (:indoor_outdoor, :indoor_outdoor_both)';
            $params[':indoor_outdoor']      = $f['indoor_outdoor'];
            $params[':indoor_outdoor_both'] = 'both';
        }
    }
    if (isset($f['budget'])) {
        $sql .= ' AND v.pricing_level = :budget';
        $params[':budget'] = $f['budget'];
    }
    if (isset($f['guest_count'])) {
        // Match on RANGE OVERLAP: a venue's [capacity_min, capacity_max] must
        // overlap the selected band [band_min, band_max] — not just clear the
        // lower bound (which returned everything for small bands). The top band
        // is open-ended (no band_max) so only the lower bound applies.
        $bands = venue_guest_bands();
        $band  = $bands[$f['guest_count']];
        $sql .= ' AND v.capacity_max IS NOT NULL AND v.capacity_max >= :guest_min';
        $params[':guest_min'] = (int)$band[1];
        if (($band[2] ?? null) !== null) {
            $sql .= ' AND (v.capacity_min IS NULL OR v.capacity_min <= :guest_max)';
            $params[':guest_max'] = (int)$band[2];
        }
    }
    if (isset($f['partner'])) {
        $sql .= ' AND v.partner_id = :partner';
        $params[':partner'] = (int)$f['partner'];
    }

    return [$sql, $params];
}

/* ===========================================================================
 * Listing query (published only) + count, with pagination
 * =========================================================================== */

/** Sort options for the listing (value => label). Whitelisted ORDER BY. */
function venue_sort_options(): array
{
    return [
        'recommended' => 'Recommended',
        'name'        => 'Name (A–Z)',
        'capacity'    => 'Capacity (high to low)',
    ];
}

/**
 * Build the GET param map for the current filters (+ optional sort/page),
 * for chip / pagination / sort links. http_build_query escapes it; callers
 * still e() the whole attribute. Emirate stays an array (emirate[]).
 */
function venue_filter_params(array $filters, string $sort = 'recommended', int $page = 1): array
{
    $p = [];
    foreach (['q', 'event_type', 'venue_type', 'guest_count', 'budget', 'indoor_outdoor', 'partner'] as $k) {
        if (isset($filters[$k])) {
            $p[$k] = $filters[$k];
        }
    }
    if (isset($filters['emirate'])) {
        $p['emirate'] = array_values((array)$filters['emirate']);
    }
    if ($sort !== '' && $sort !== 'recommended') {
        $p['sort'] = $sort;
    }
    if ($page > 1) {
        $p['page'] = $page;
    }
    return $p;
}

/** Map a sort key to a safe ORDER BY clause (never interpolates user input). */
function venue_sort_sql(string $sort): string
{
    switch ($sort) {
        case 'name':     return 'v.name ASC';
        case 'capacity': return 'v.capacity_max IS NULL, v.capacity_max DESC, v.name ASC';
        default:         return 'v.is_featured DESC, v.name ASC';   // recommended
    }
}

/**
 * @return array{rows: array, total: int}
 */
function venue_list(PDO $pdo, array $filters, int $page, int $perPage, string $sort = 'recommended'): array
{
    [$where, $params] = venue_filter_sql($filters);
    $orderBy = venue_sort_sql($sort);

    // Total (for pagination + result count).
    $countSql = 'SELECT COUNT(*) FROM venues v WHERE v.status = :status' . $where;
    $stmt = $pdo->prepare($countSql);
    $stmt->execute([':status' => 'published'] + $params);
    $total = (int)$stmt->fetchColumn();

    $offset = ($page - 1) * $perPage;

    $sql = "SELECT
                v.id, v.slug, v.name, v.pricing_level, v.minimum_spend,
                v.capacity_max, v.capacity_min, v.is_featured, v.is_verified,
                v.indoor_outdoor, v.best_for, v.description, v.partner_id,
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
            ORDER BY " . $orderBy . "
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

/**
 * Featured published venues for the homepage. Falls back to newest published
 * when fewer than $limit are flagged featured. Prepared + bound.
 */
function venue_featured(PDO $pdo, int $limit = 3): array
{
    $sql = "SELECT
                v.id, v.slug, v.name, v.pricing_level, v.minimum_spend,
                v.capacity_max, v.is_featured, v.is_verified, v.indoor_outdoor,
                v.best_for, v.description, v.partner_id,
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
            ORDER BY v.is_featured DESC, v.published_at DESC, v.id DESC
            LIMIT :limit";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
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
                v.id, v.slug, v.name, v.pricing_level, v.minimum_spend,
                v.capacity_max, v.capacity_min, v.is_featured, v.is_verified,
                v.indoor_outdoor, v.best_for, v.description, v.partner_id,
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

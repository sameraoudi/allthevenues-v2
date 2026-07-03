<?php
declare(strict_types=1);

/**
 * Public partner (provider) read layer. Approved-only is enforced in SQL on
 * every public query (mirrors the venue published-only gate). Prepared
 * statements throughout. NEVER selects email/phone for public output.
 *
 * Partner "type" is not a clean column in the migrated data (partner_group is
 * NULL; the legacy provider type was folded into `notes` as "Legacy org type:
 * X" by U1b). We extract it from notes and bucket it to Hotel / Resort /
 * Restaurant / Unique venue. Verified is a real column (partners.is_verified,
 * editorial trust) curated separately from is_featured (curation/featured); all
 * publicly-listed partners are `approved` (vetted).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venues.php';   // venue card row shape + helpers

/** SQL expression extracting the raw legacy org type from notes (or NULL). */
function partner_org_type_expr(string $alias = 'p'): string
{
    // Prefer the admin-set type (partner_group holds the bucket LABEL); else
    // fall back to the migration-derived value extracted from notes.
    return "CASE"
         . " WHEN $alias.partner_group IS NOT NULL AND TRIM($alias.partner_group) <> ''"
         . " THEN TRIM($alias.partner_group)"
         . " ELSE (CASE WHEN $alias.notes LIKE 'Legacy org type:%'"
         . " THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX($alias.notes, '\\n', 1), ':', -1))"
         . " ELSE NULL END)"
         . " END";
}

/** Public partner-type buckets: key => [label, [underlying legacy values]]. */
function partner_type_buckets(): array
{
    return [
        'hotel'      => ['Hotel',        ['Hotel']],
        'resort'     => ['Resort',       ['Resort']],
        'restaurant' => ['Restaurant',   ['Restaurant']],
        'unique'     => ['Unique venue', ['Art Space', 'Government', 'Warehouse Venue', 'Other']],
    ];
}

/** Normalise a raw legacy org type → display label. */
function partner_type_label(?string $raw): string
{
    $raw = trim((string)$raw);
    foreach (partner_type_buckets() as $b) {
        if (in_array($raw, $b[1], true)) {
            return $b[0];
        }
    }
    return $raw !== '' ? 'Unique venue' : 'Venue provider';
}

/**
 * Whether a provider is shown as "Verified" (public badge + footer). Reads the
 * real partners.is_verified column (editorial trust), independent of is_featured.
 */
function partner_is_verified(array $row): bool
{
    return !empty($row['is_verified']);
}

/** Whether the provider shows the "Featured" badge. */
function partner_is_featured(array $row): bool
{
    return !empty($row['is_featured']);
}

/**
 * Public badges for a provider (Featured / Verified), max two. Each entry:
 * ['label' => string, 'variant' => '' (sand) | 'verified' (blue)].
 */
function partner_badges(array $row): array
{
    $badges = [];
    if (partner_is_featured($row)) { $badges[] = ['label' => 'Featured',          'variant' => '']; }
    if (partner_is_verified($row)) { $badges[] = ['label' => 'Verified provider', 'variant' => 'verified']; }
    return array_slice($badges, 0, 2);
}

/**
 * Deterministic gradient index (0–5) for the rare no-image cover fallback.
 * Seeded by the provider name so a given provider always gets the same one.
 * The gradient itself is a CSS class (.cover-grad--N) — never an inline style,
 * so it stays within the strict CSP (style-src 'self', no unsafe-inline).
 */
function partner_cover_gradient_index(string $seed): int
{
    return abs(crc32($seed)) % 6;
}

/** Monogram (initials) for the logo fallback. */
function partner_monogram(string $orgName): string
{
    $words = preg_split('/\s+/', trim($orgName)) ?: [];
    $words = array_values(array_filter($words, static fn($w) => $w !== ''));
    if (!$words) return 'AV';
    $a = mb_substr($words[0], 0, 1);
    $b = isset($words[1]) ? mb_substr($words[1], 0, 1) : '';
    return mb_strtoupper($a . $b);
}

function partner_sort_options(): array
{
    return ['featured' => 'Featured', 'name' => 'Name (A–Z)'];
}
function partner_sort_sql(string $sort): string
{
    return $sort === 'name' ? 'p.org_name ASC' : 'p.is_featured DESC, p.org_name ASC';
}

/** Normalise listing filters from $_GET. */
function partner_normalise_filters(array $in): array
{
    $out = [];
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') $out['q'] = mb_substr($q, 0, 100);

    $em = $in['emirate'] ?? [];
    $emList = is_array($em) ? $em : explode(',', (string)$em);
    $valid = [];
    foreach ($emList as $s) {
        $s = trim((string)$s);
        if ($s !== '' && preg_match('/^[a-z0-9-]{1,191}$/', $s) && !in_array($s, $valid, true)) $valid[] = $s;
    }
    if ($valid) $out['emirate'] = $valid;

    $ty = $in['type'] ?? [];
    $tyList = is_array($ty) ? $ty : explode(',', (string)$ty);
    $vt = [];
    foreach ($tyList as $t) {
        $t = trim((string)$t);
        if (isset(partner_type_buckets()[$t]) && !in_array($t, $vt, true)) $vt[] = $t;
    }
    if ($vt) $out['type'] = $vt;

    if (!empty($in['featured'])) $out['featured'] = 1;

    return $out;
}

/** WHERE fragment (approved is added by the caller) + bound params. */
function partner_filter_sql(array $f): array
{
    $sql = '';
    $p = [];
    if (isset($f['q'])) {
        $sql .= ' AND p.org_name LIKE :q';
        $p[':q'] = '%' . $f['q'] . '%';
    }
    if (isset($f['emirate'])) {
        $ph = [];
        foreach ((array)$f['emirate'] as $i => $s) { $k = ":pem$i"; $ph[] = $k; $p[$k] = $s; }
        $sql .= ' AND p.emirate_id IN (SELECT id FROM emirates WHERE slug IN (' . implode(',', $ph) . '))';
    }
    if (isset($f['type'])) {
        $legacy = [];
        foreach ((array)$f['type'] as $t) {
            foreach (partner_type_buckets()[$t][1] as $v) { $legacy[] = $v; }
            $legacy[] = partner_type_buckets()[$t][0];   // admin-set label matches its bucket
        }
        if ($legacy) {
            $ph = [];
            foreach ($legacy as $i => $v) { $k = ":pty$i"; $ph[] = $k; $p[$k] = $v; }
            $sql .= ' AND (' . partner_org_type_expr() . ') IN (' . implode(',', $ph) . ')';
        }
    }
    if (!empty($f['featured'])) {
        $sql .= ' AND p.is_featured = 1';
    }
    return [$sql, $p];
}

/** Build the GET param map for chip/pagination/sort links. */
function partner_filter_params(array $filters, string $sort = 'featured', int $page = 1): array
{
    $p = [];
    if (isset($filters['q'])) $p['q'] = $filters['q'];
    if (isset($filters['emirate'])) $p['emirate'] = array_values((array)$filters['emirate']);
    if (isset($filters['type']))    $p['type'] = array_values((array)$filters['type']);
    if (!empty($filters['featured'])) $p['featured'] = 1;
    if ($sort !== '' && $sort !== 'featured') $p['sort'] = $sort;
    if ($page > 1) $p['page'] = $page;
    return $p;
}

/** @return array{rows: array, total: int} */
function partner_list(PDO $pdo, array $filters, int $page, int $perPage, string $sort = 'featured'): array
{
    [$where, $params] = partner_filter_sql($filters);

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM partners p WHERE p.status='approved'" . $where);
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $sql = "SELECT p.id, p.slug, p.org_name, p.about, p.website,
                   p.is_featured, p.is_verified, p.cover_image_alt, p.created_at, p.approved_at, p.city_text,
                   e.name AS emirate_name, e.slug AS emirate_slug,
                   (" . partner_org_type_expr() . ") AS raw_org_type,
                   (SELECT COUNT(*) FROM venues v WHERE v.partner_id = p.id AND v.status='published') AS venue_count,
                   COALESCE(p.cover_thumb_path, p.cover_image_path,
                     (SELECT vi.file_path
                      FROM venues v2
                      JOIN venue_images vi ON vi.venue_id = v2.id AND vi.status = 'active'
                      WHERE v2.partner_id = p.id AND v2.status = 'published'
                      ORDER BY v2.is_featured DESC, vi.is_primary DESC, v2.name ASC,
                               vi.sort_order ASC, vi.id ASC
                      LIMIT 1)) AS cover_image
            FROM partners p
            LEFT JOIN emirates e ON e.id = p.emirate_id
            WHERE p.status='approved'" . $where . "
            ORDER BY " . partner_sort_sql($sort) . "
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Approved partner by slug, or null. */
function partner_by_slug(PDO $pdo, string $slug): ?array
{
    $sql = "SELECT p.id, p.slug, p.org_name, p.about, p.website,
                   p.is_featured, p.is_verified, p.cover_image_alt, p.created_at, p.approved_at, p.city_text,
                   e.name AS emirate_name, e.slug AS emirate_slug,
                   (" . partner_org_type_expr() . ") AS raw_org_type,
                   (SELECT COUNT(*) FROM venues v WHERE v.partner_id = p.id AND v.status='published') AS venue_count,
                   COALESCE(p.cover_image_path, p.cover_thumb_path,
                     (SELECT vi.file_path
                      FROM venues v2
                      JOIN venue_images vi ON vi.venue_id = v2.id AND vi.status = 'active'
                      WHERE v2.partner_id = p.id AND v2.status = 'published'
                      ORDER BY v2.is_featured DESC, vi.is_primary DESC, v2.name ASC,
                               vi.sort_order ASC, vi.id ASC
                      LIMIT 1)) AS cover_image
            FROM partners p
            LEFT JOIN emirates e ON e.id = p.emirate_id
            WHERE p.slug = :slug AND p.status='approved' LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** The partner's PUBLISHED venues, shaped for the venue card. */
function partner_published_venues(PDO $pdo, int $partnerId, int $limit = 12): array
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
            WHERE v.partner_id = :pid AND v.status = 'published'
            ORDER BY v.is_featured DESC, v.name ASC
            LIMIT :lim";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':pid', $partnerId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** "Best for" tags derived from the partner's published venues' best_for. */
function partner_best_for(PDO $pdo, int $partnerId, int $limit = 3): array
{
    $stmt = $pdo->prepare(
        "SELECT best_for FROM venues WHERE partner_id = :pid AND status='published' AND best_for IS NOT NULL AND best_for <> ''"
    );
    $stmt->execute([':pid' => $partnerId]);
    $counts = [];
    foreach ($stmt->fetchAll() as $r) {
        foreach (tags_from($r['best_for']) as $t) {
            $key = mb_strtolower($t);
            if (!isset($counts[$key])) { $counts[$key] = ['label' => $t, 'n' => 0]; }
            $counts[$key]['n']++;
        }
    }
    usort($counts, static fn($a, $b) => $b['n'] <=> $a['n']);
    return array_map(static fn($c) => $c['label'], array_slice($counts, 0, $limit));
}

/** Approved-partner counts per emirate slug (for the sidebar). */
function partner_emirate_counts(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT e.slug, COUNT(*) AS n FROM partners p JOIN emirates e ON e.id = p.emirate_id
         WHERE p.status='approved' GROUP BY e.slug"
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) { $out[$r['slug']] = (int)$r['n']; }
    return $out;
}

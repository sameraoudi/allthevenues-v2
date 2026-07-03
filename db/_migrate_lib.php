<?php
declare(strict_types=1);

/**
 * Shared helpers for the follow-up backfill scripts
 *   - db/backfill_venue_website.php  (PART 1)
 *   - db/seed_venue_event_types.php  (PART 2)
 *
 * Include-only: defines functions, no side effects (safe to require). Direct
 * web access is denied by db/.htaccess; the entry scripts add a CLI-only guard.
 *
 * The legacy → new venue resolution replays EXACTLY what migrate_catalogue.php
 * did (legacy `venues` in id order → unique_slug(slugify(name))), so a standalone
 * script reproduces the same legacy_id → new_id mapping without that script's
 * in-memory state. slugify()/unique_slug() below are kept byte-identical to
 * migrate_catalogue.php — if one changes, change both.
 */

/* ---- DB connection (mirrors migrate_catalogue.php CONFIG) ------------------ */

function ml_legacy_config(): array
{
    return [
        'host' => getenv('ATV_LEGACY_DB_HOST') ?: 'localhost',
        'port' => (int)(getenv('ATV_LEGACY_DB_PORT') ?: 3306),
        'name' => getenv('ATV_LEGACY_DB_NAME') ?: 'sameraou_atv',
        'user' => getenv('ATV_LEGACY_DB_USER') ?: 'REPLACE_LEGACY_READ_USER',
        'pass' => getenv('ATV_LEGACY_DB_PASS') ?: 'REPLACE_LEGACY_READ_PASS',
    ];
}

/** New DB creds: env overrides (local) else config/config.php constants. */
function ml_new_config(): array
{
    if (getenv('ATV_NEW_DB_HOST')) {
        return [
            'host' => getenv('ATV_NEW_DB_HOST'),
            'port' => (int)(getenv('ATV_NEW_DB_PORT') ?: 3306),
            'name' => getenv('ATV_NEW_DB_NAME') ?: 'sameraou_atv2',
            'user' => getenv('ATV_NEW_DB_USER') ?: 'root',
            'pass' => getenv('ATV_NEW_DB_PASS') ?: '',
        ];
    }
    $cfg = __DIR__ . '/../config/config.php';
    if (!is_file($cfg)) {
        fwrite(STDERR, "config/config.php not found; set ATV_NEW_DB_* env vars or create it.\n");
        exit(1);
    }
    require_once $cfg;
    return [
        'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
        'port' => (defined('DB_PORT') && DB_PORT) ? (int)DB_PORT : 3306,
        'name' => defined('DB_NAME') ? DB_NAME : 'sameraou_atv2',
        'user' => defined('DB_USER') ? DB_USER : '',
        'pass' => defined('DB_PASS') ? DB_PASS : '',
    ];
}

function ml_pdo_connect(array $c): PDO
{
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/* ---- Slug logic (identical to migrate_catalogue.php) ---------------------- */

/** URL slug: lowercase, non-alnum → '-', collapse, trim. */
function ml_slugify(string $s): string
{
    $s = strtolower(trim($s));
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($t !== false) {
        $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'item';
}

/** Return a slug unique within $seen (adds -2, -3, …); records it. */
function ml_unique_slug(string $base, array &$seen): string
{
    $slug = $base;
    $n = 2;
    while (isset($seen[$slug])) {
        $slug = $base . '-' . $n;
        $n++;
    }
    $seen[$slug] = true;
    return $slug;
}

/**
 * Build legacy_venue_id → new_venue_id by replaying the migration's slug
 * derivation and resolving each slug against the new `venues` table.
 * @return array<int,int>
 */
function ml_build_venue_id_map(PDO $src, PDO $dst): array
{
    $newIdBySlug = [];
    foreach ($dst->query('SELECT id, slug FROM venues') as $r) {
        $newIdBySlug[$r['slug']] = (int)$r['id'];
    }
    $seen = [];
    $map  = [];
    foreach ($src->query('SELECT id, name FROM venues ORDER BY id') as $v) {
        $slug = ml_unique_slug(ml_slugify((string)$v['name']), $seen);
        if (isset($newIdBySlug[$slug])) {
            $map[(int)$v['id']] = $newIdBySlug[$slug];
        }
    }
    return $map;
}

/* ---- PART 1: website URL validation / normalization (pure) ---------------- */

/**
 * Normalize a legacy website value to a safe http(s) URL, or null if it is
 * empty / junk / not a URL. Adds https:// when a bare domain is given.
 * Never returns a value over 255 chars (the column width) — over-long → null.
 */
function ml_normalize_website_url(?string $raw): ?string
{
    $s = trim((string)$raw);
    if ($s === '') {
        return null;
    }
    // Common placeholder junk → treat as empty.
    $low = strtolower($s);
    if (in_array($low, [
        'n/a', 'na', 'none', 'nil', 'null', 'tbd', 'tba', '-', '–', '.', '..',
        'http://', 'https://', 'www.', 'website', 'no website',
    ], true)) {
        return null;
    }
    // If there is embedded whitespace, take the first token (legacy notes like
    // "www.x.com (main)").
    if (preg_match('/\s/', $s)) {
        $s = (string)preg_split('/\s+/', $s)[0];
    }
    // An email address is not a website.
    if (str_contains($s, '@')) {
        return null;
    }
    // Prepend a scheme for bare domains.
    if (!preg_match('#^https?://#i', $s)) {
        $s = 'https://' . ltrim($s, '/');
    }
    $parts = parse_url($s);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return null;
    }
    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
        return null;
    }
    // Host must be a dotted domain with a plausible TLD.
    $host = $parts['host'];
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/i', $host)) {
        return null;
    }
    if (strlen($s) > 255) {
        return null;
    }
    return $s;
}

/* ---- PART 2: best_for / ideal-for → event_types keyword map (documented) --- */

/**
 * Documented keyword → event_types.slug map. Matching is case-insensitive
 * substring over the venue's best_for text; every matching keyword adds its
 * event type (de-duplicated). Conservative first pass — Samer refines in admin.
 * Niche legacy categories with no clean event_type (concerts, music videos,
 * photoshoots, fashion shows, car reviews, retail pop-ups, brand activations,
 * lifestyle events) are intentionally left unmapped rather than dumped into
 * "Other".
 *
 * @return array<string,string> keyword => event_type slug
 */
function ml_event_type_keyword_map(): array
{
    return [
        'wedding'          => 'wedding',
        'engagement'       => 'engagement',
        'corporate'        => 'corporate-event',
        'business'         => 'corporate-event',   // "… business functions"
        'team building'    => 'corporate-event',   // "team building activities"
        'conference'       => 'conference',
        'meeting'          => 'meeting',
        'training'         => 'training',
        'launch'           => 'product-launch',    // "product launch(es)"
        'gala'             => 'gala-dinner',
        'birthday'         => 'birthday',
        'baby shower'      => 'private-party',
        'party'            => 'private-party',      // "private parties"
        'parties'          => 'private-party',
        'private event'    => 'private-party',
        'social gathering' => 'private-party',
        'exhibition'       => 'exhibition',
        'outdoor'          => 'outdoor-event',
        'yacht'            => 'yacht-event',
        'networking'       => 'networking-event',
    ];
}

/**
 * Map a best_for / ideal-for text to a de-duplicated list of event_type slugs
 * using the keyword map. Pure — no DB. Order follows the map for stability.
 * @return string[] event_type slugs
 */
function ml_map_best_for_to_event_slugs(?string $text, ?array $map = null): array
{
    $map = $map ?? ml_event_type_keyword_map();
    $hay = strtolower(trim(strip_tags((string)$text)));
    if ($hay === '') {
        return [];
    }
    $slugs = [];
    foreach ($map as $keyword => $slug) {
        if (str_contains($hay, $keyword) && !in_array($slug, $slugs, true)) {
            $slugs[] = $slug;
        }
    }
    return $slugs;
}

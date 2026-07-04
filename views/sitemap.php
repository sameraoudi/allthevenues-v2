<?php
declare(strict_types=1);

/**
 * Dynamic /sitemap.xml — every INDEXABLE public URL, domain-agnostic via
 * base_url() (BASE_URL). Standalone handler (no HTML layout). Excludes admin,
 * unpublished venues, and thin/redirecting landing combos (uses the same
 * LANDING_MIN_VENUES threshold as views/landing.php, so sitemap ⇔ what renders).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

$pdo = db_pdo();

header('Content-Type: application/xml; charset=utf-8');

/** One <url> entry; loc XML-escaped, optional lastmod. */
$url = static function (string $loc, ?string $lastmod = null): string {
    $x = '  <url><loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>';
    if ($lastmod !== null && $lastmod !== '') {
        $x .= '<lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . '</lastmod>';
    }
    return $x . '</url>';
};

$lines = [];

// Static hubs.
foreach (['/', 'venues', 'providers', 'event-types', 'locations'] as $hub) {
    $lines[] = $url(base_url($hub));
}

// Published venues (with lastmod from updated_at).
foreach ($pdo->query("SELECT slug, updated_at FROM venues WHERE status = 'published' ORDER BY slug") as $v) {
    $ts = $v['updated_at'] ? strtotime((string)$v['updated_at']) : false;
    $lines[] = $url(base_url('venues/' . $v['slug']), $ts ? date('Y-m-d', $ts) : null);
}

// Approved providers (no updated_at column → no lastmod).
foreach ($pdo->query("SELECT slug FROM partners WHERE status = 'approved' ORDER BY slug") as $p) {
    $lines[] = $url(base_url('providers/' . $p['slug']));
}

// Qualifying event×city landing combos (>= LANDING_MIN_VENUES — same gate as render).
foreach (qualifying_landing_combos($pdo) as $c) {
    $lines[] = $url(base_url('venues/' . $c['ev'] . '-in-' . $c['em']));
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo implode("\n", $lines) . "\n";
echo '</urlset>' . "\n";
exit;

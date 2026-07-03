<?php
declare(strict_types=1);

// CLI only — never over HTTP. (Also denied by db/.htaccess + excluded from
// deploy via .cpanel.yml; this is defence in depth.)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * PART 1 — Backfill venues.website from the legacy catalogue.
 *
 * The U1b migration (db/migrate_catalogue.php) did not carry over the legacy
 * `venues.website` column. This reads it from the legacy DB, resolves each
 * legacy venue to its new row the same way the migration did, validates /
 * normalizes the URL (http/https only), and writes venues.website.
 *
 * Runs ON THE SERVER (both DBs are localhost-only). Local dev: supply legacy
 * creds via ATV_LEGACY_DB_* env vars against a loaded dump.
 *
 *   php db/backfill_venue_website.php
 *
 * PREREQUISITE: db/007_venue_website.sql applied (adds venues.website).
 * IDEMPOTENT: an UPDATE keyed on venue id; re-running yields the same result.
 * Target MySQL 5.7. Prepared statements throughout.
 */

require_once __DIR__ . '/_migrate_lib.php';

$t0 = microtime(true);
echo "== Backfill venues.website (PART 1) ==\n";

try {
    $src = ml_pdo_connect(ml_legacy_config());
    $dst = ml_pdo_connect(ml_new_config());
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

// Guard: the target column must exist (007 applied).
try {
    $dst->query('SELECT website FROM venues LIMIT 0');
} catch (Throwable $e) {
    fwrite(STDERR, "venues.website is missing — apply db/007_venue_website.sql first.\n");
    exit(1);
}

// Legacy → new venue id map (replays the migration's slug derivation).
$venueIdMap = ml_build_venue_id_map($src, $dst);
echo "Resolved legacy→new venues: " . count($venueIdMap) . "\n";

$upd = $dst->prepare('UPDATE venues SET website = :website WHERE id = :id');

$stats = [
    'legacy_total'   => 0,   // legacy venues seen
    'legacy_hasurl'  => 0,   // legacy rows with a non-empty website
    'backfilled'     => 0,   // rows written (valid URL, venue resolved)
    'skip_invalid'   => 0,   // had a value but failed validation
    'skip_empty'     => 0,   // no website value
    'skip_unresolved'=> 0,   // valid URL but legacy venue not resolvable to new
];
$skippedInvalid = [];   // [legacy id, name, raw]

foreach ($src->query('SELECT id, name, website FROM venues ORDER BY id') as $v) {
    $stats['legacy_total']++;
    $raw = trim((string)($v['website'] ?? ''));
    if ($raw === '') {
        $stats['skip_empty']++;
        continue;
    }
    $stats['legacy_hasurl']++;

    $url = ml_normalize_website_url($raw);
    if ($url === null) {
        $stats['skip_invalid']++;
        $skippedInvalid[] = [(int)$v['id'], (string)$v['name'], $raw];
        continue;
    }

    $newId = $venueIdMap[(int)$v['id']] ?? null;
    if ($newId === null) {
        $stats['skip_unresolved']++;
        continue;
    }

    $upd->execute([':website' => $url, ':id' => $newId]);
    $stats['backfilled']++;
}

/* ---- Report -------------------------------------------------------------- */
echo "\n-- Report --\n";
printf("  legacy venues scanned : %d\n", $stats['legacy_total']);
printf("  had a website value   : %d\n", $stats['legacy_hasurl']);
printf("  backfilled (valid)    : %d\n", $stats['backfilled']);
printf("  skipped — empty       : %d\n", $stats['skip_empty']);
printf("  skipped — invalid URL : %d\n", $stats['skip_invalid']);
printf("  skipped — unresolved  : %d\n", $stats['skip_unresolved']);

if ($skippedInvalid) {
    echo "\n-- Skipped as invalid (first 25) --\n";
    foreach (array_slice($skippedInvalid, 0, 25) as [$lid, $name, $raw]) {
        echo "  legacy #$lid  \"$name\"  raw=\"$raw\"\n";
    }
}

// Final state in the new DB (idempotent check).
$now = (int)$dst->query("SELECT COUNT(*) FROM venues WHERE website IS NOT NULL AND website <> ''")->fetchColumn();
printf("\n  venues.website now populated: %d\n", $now);

printf("Done in %.2fs.\n", microtime(true) - $t0);

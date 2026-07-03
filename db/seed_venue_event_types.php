<?php
declare(strict_types=1);

// CLI only — never over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * PART 2 — First-pass auto-seed of venue_event_types (fixes the Event Type
 * filter, whose table migrated empty).
 *
 * Source is the venue's `best_for` text in the NEW DB — this IS the migrated
 * legacy `ideal-for` (see migrate_catalogue.php: best_for ← ideal-for), so no
 * legacy connection is needed and the result is fully reproducible locally.
 * Each venue's best_for is mapped to event_types via the documented keyword
 * map in _migrate_lib.php (conservative — Samer refines later in admin).
 *
 *   php db/seed_venue_event_types.php
 *
 * IDEMPOTENT: INSERT IGNORE against PRIMARY KEY (venue_id, event_type_id) —
 * re-running never duplicates and never removes admin-added tags. It only adds
 * the auto-derived rows. Target MySQL 5.7. Prepared statements throughout.
 *
 * Niche legacy categories with no clean event type are intentionally left
 * unmapped (not dumped into "Other"); such venues are reported as untagged.
 */

require_once __DIR__ . '/_migrate_lib.php';

$t0 = microtime(true);
echo "== Seed venue_event_types from best_for (PART 2) ==\n";

try {
    $dst = ml_pdo_connect(ml_new_config());
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

// event_types.slug → id (active types only).
$eventTypeIdBySlug = [];
foreach ($dst->query('SELECT id, slug FROM event_types WHERE active = 1') as $r) {
    $eventTypeIdBySlug[$r['slug']] = (int)$r['id'];
}

$ins = $dst->prepare(
    'INSERT IGNORE INTO venue_event_types (venue_id, event_type_id)
     VALUES (:venue_id, :event_type_id)'
);

$totalVenues   = 0;
$venuesTagged  = 0;   // got >= 1 tag from this pass
$venuesZero    = 0;   // no best_for OR no keyword matched
$rowsInserted  = 0;   // new rows this run (0 on a clean re-run)
$distribution  = [];  // event slug => venue count (auto-derived)
$untagged      = [];  // [venue id, name, best_for] with content but no match

foreach ($dst->query('SELECT id, name, best_for FROM venues ORDER BY id') as $v) {
    $totalVenues++;
    $vid   = (int)$v['id'];
    $slugs = ml_map_best_for_to_event_slugs($v['best_for'] ?? null);

    if (!$slugs) {
        $venuesZero++;
        if (trim(strip_tags((string)($v['best_for'] ?? ''))) !== '') {
            $untagged[] = [$vid, (string)$v['name'], trim(preg_replace('/\s+/', ' ', strip_tags((string)$v['best_for'])))];
        }
        continue;
    }

    $addedForVenue = false;
    foreach ($slugs as $slug) {
        $etid = $eventTypeIdBySlug[$slug] ?? null;
        if ($etid === null) {
            continue;   // slug not in active event_types (shouldn't happen)
        }
        $ins->execute([':venue_id' => $vid, ':event_type_id' => $etid]);
        $rowsInserted += $ins->rowCount();   // 1 if newly inserted, 0 if already present
        $distribution[$slug] = ($distribution[$slug] ?? 0) + 1;
        $addedForVenue = true;
    }
    if ($addedForVenue) {
        $venuesTagged++;
    }
}

/* ---- Report -------------------------------------------------------------- */
echo "\n-- Coverage report --\n";
printf("  venues total            : %d\n", $totalVenues);
printf("  venues tagged (>=1)     : %d\n", $venuesTagged);
printf("  venues with zero tags   : %d\n", $venuesZero);
printf("  rows inserted this run  : %d  (0 on a clean re-run)\n", $rowsInserted);

echo "\n-- Per-event-type distribution (auto-derived venues) --\n";
// Order by the seed order of event_types for a stable, readable table.
$labelBySlug = [];
foreach ($dst->query('SELECT slug, name, sort_order FROM event_types ORDER BY sort_order') as $r) {
    $labelBySlug[$r['slug']] = $r['name'];
}
foreach ($labelBySlug as $slug => $label) {
    printf("  %-20s %d\n", $label, $distribution[$slug] ?? 0);
}

// Live totals straight from the table (authoritative, includes any prior rows).
echo "\n-- venue_event_types table totals (live) --\n";
$rowsNow  = (int)$dst->query('SELECT COUNT(*) FROM venue_event_types')->fetchColumn();
$vNow     = (int)$dst->query('SELECT COUNT(DISTINCT venue_id) FROM venue_event_types')->fetchColumn();
printf("  rows: %d   distinct venues: %d\n", $rowsNow, $vNow);

if ($untagged) {
    echo "\n-- Untagged venues with best_for content (need admin review) --\n";
    foreach (array_slice($untagged, 0, 25) as [$vid, $name, $bf]) {
        echo "  #$vid  \"$name\"  best_for=\"$bf\"\n";
    }
    if (count($untagged) > 25) {
        printf("  … and %d more\n", count($untagged) - 25);
    }
}

printf("\nDone in %.2fs.\n", microtime(true) - $t0);

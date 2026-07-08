<?php
declare(strict_types=1);

// CLI only — never over HTTP. (Also denied by db/.htaccess + excluded from deploy
// via .cpanel.yml; this is defence in depth.)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * Contacts-A #A3 — one-time contact gap-fill backfill.
 *
 * Fill-if-empty ONLY (never overwrites a side that already has a contact),
 * idempotent, and re-runnable (once converged, a re-run reports zeros / is a
 * no-op). Dry-run by DEFAULT — pass --commit to actually write.
 *
 *   php db/backfill_contacts.php            # dry-run (report only, no writes)
 *   php db/backfill_contacts.php --commit   # apply the fills
 *
 * Order — chosen so the whole graph converges in a SINGLE pass (so a re-run is a
 * clean no-op): (1) venue→provider: fill each contactless provider from its
 * venues when UNAMBIGUOUS (single venue-with-contact, or all identical name+email);
 * (2) provider→venue: fill each contactless venue from its (now possibly filled)
 * provider. AMBIGUOUS providers (multiple differing venue contacts) are skipped
 * and listed for manual review.
 *
 * Uses lib/contact_sync.php (the same helpers that run on save). Dry-run wraps the
 * work in a transaction and ROLLS BACK, so counts are exact and nothing (incl.
 * audit rows) persists. FOR SAMER: db/ is deploy-excluded — upload + run on prod
 * manually; dry-run first, then --commit.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/contact_sync.php';

$commit = in_array('--commit', $argv, true);
$t0 = microtime(true);

try {
    $pdo = db_pdo();
} catch (Throwable $e) {
    fwrite(STDERR, 'DB connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

echo '== Contacts-A backfill (' . ($commit ? 'COMMIT' : 'DRY-RUN') . ") ==\n";

$pdo->beginTransaction();

$filledProviders = 0;
$filledVenues    = 0;
$ambiguousIds    = [];

// (1) venue → provider (unambiguous only).
foreach ($pdo->query('SELECT id FROM partners ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $pid) {
    $res = contact_sync_for_provider($pdo, (int)$pid, null);
    if (!empty($res['filled']))        { $filledProviders++; }
    elseif (!empty($res['ambiguous'])) { $ambiguousIds[] = (int)$pid; }
}

// (2) provider → venue (fill contactless venues from their provider).
foreach ($pdo->query('SELECT id FROM venues ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $vid) {
    if (contact_sync_for_venue($pdo, (int)$vid, null)) { $filledVenues++; }
}

/* ---- Report -------------------------------------------------------------- */
echo "\n-- Report --\n";
printf("  providers filled from a venue : %d\n", $filledProviders);
printf("  venues filled from a provider : %d\n", $filledVenues);
printf("  ambiguous providers skipped   : %d\n", count($ambiguousIds));

if ($ambiguousIds) {
    echo "\n-- Ambiguous providers (differing venue contacts — MANUAL REVIEW) --\n";
    $in  = implode(',', array_fill(0, count($ambiguousIds), '?'));
    $nq  = $pdo->prepare("SELECT id, org_name FROM partners WHERE id IN ($in) ORDER BY org_name");
    $nq->execute($ambiguousIds);
    foreach ($nq->fetchAll() as $p) {
        echo '  #' . (int)$p['id'] . '  "' . (string)$p['org_name'] . "\"\n";
    }
}

if ($commit) {
    $pdo->commit();
    echo "\nCommitted.\n";
} else {
    $pdo->rollBack();
    echo "\nDry-run — nothing written. Re-run with --commit to apply.\n";
}

printf("Done in %.2fs.\n", microtime(true) - $t0);

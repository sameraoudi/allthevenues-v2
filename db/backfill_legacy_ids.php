<?php
declare(strict_types=1);

// CLI only — never over HTTP. (Also denied by db/.htaccess + excluded from
// deploy via .cpanel.yml; this is defence in depth.)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * U6 Phase-4 — Backfill venues.legacy_id + partners.legacy_id from the legacy DB
 * so the old indexed URLs can 301 to the new slugs by id.
 *
 * CRITICAL EQUALITY (the whole point): the legacy primary keys ARE the ids used
 * in the indexed URLs —
 *   venue.php?venueid=<N>   → legacy `venues.id`   → stored as new venues.legacy_id
 *   provider.php?pid=<N>    → legacy `providers.pid`→ stored as new partners.legacy_id
 * (per db/migrate_catalogue.php, which reads `SELECT * FROM venues ORDER BY id`
 *  and `SELECT * FROM providers ORDER BY pid`; a venue's owning provider is the
 *  legacy TEXT column `venues.provider`).
 *
 * MATCHING legacy → new (to set new.legacy_id):
 *   * partners: normalized legacy provider name  → new partners.org_name.
 *   * venues:   normalized (venue name + owning-provider name) → new venue joined
 *               to its partner; falls back to name-only ONLY when unambiguous
 *               (handles same-named venues across different providers).
 *   Normalize = trim + lower + collapse whitespace.
 *
 * Reports three buckets — matched / ambiguous / unmatched — so stragglers can be
 * hand-set:  UPDATE {table} SET legacy_id = <legacyId> WHERE id = <newId>;
 *
 * SAFE BY DEFAULT: dry-run unless --commit (or --run). Idempotent / re-runnable —
 * only sets/updates legacy_id. Legacy creds come from ATV_LEGACY_DB_* env at
 * runtime (see _migrate_lib.php — the repo holds only a placeholder, never creds).
 *
 *   php db/backfill_legacy_ids.php            # dry-run (no writes)
 *   php db/backfill_legacy_ids.php --commit   # writes
 *
 * PREREQUISITE: db/016_venue_partner_legacy_id.sql applied. MySQL 5.7. Prepared
 * statements throughout.
 */

require_once __DIR__ . '/_migrate_lib.php';
require_once __DIR__ . '/../lib/helpers.php';   // slugify()

$commit = in_array('--commit', $argv, true) || in_array('--run', $argv, true);
$dryRun = !$commit;

echo "== Backfill venues.legacy_id + partners.legacy_id (U6 Phase-4) ==\n";
echo $dryRun ? "MODE: DRY-RUN (no writes; pass --commit to write)\n\n" : "MODE: COMMIT (writing)\n\n";

/** Normalize a name for matching: trim + lower + collapse internal whitespace. */
function bl_norm(?string $s): string
{
    $s = strtolower(trim((string)$s));
    return (string)preg_replace('/\s+/', ' ', $s);
}

try {
    $src = ml_pdo_connect(ml_legacy_config());
    $dst = ml_pdo_connect(ml_new_config());
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

// Guard: target columns must exist (016 applied).
try {
    $dst->query('SELECT legacy_id FROM venues LIMIT 0');
    $dst->query('SELECT legacy_id FROM partners LIMIT 0');
} catch (Throwable $e) {
    fwrite(STDERR, "legacy_id column missing — apply db/016_venue_partner_legacy_id.sql first.\n");
    exit(1);
}

/* =====================================================================
 * PARTNERS: normalized legacy providers.name → new partners.org_name
 * ===================================================================== */
$newPartnersByName = [];   // norm(org_name) => [new id, …]
foreach ($dst->query('SELECT id, org_name FROM partners') as $r) {
    $newPartnersByName[bl_norm($r['org_name'])][] = (int)$r['id'];
}

$pMatched = $pAmbig = $pUnmatched = [];
foreach ($src->query('SELECT pid, name FROM providers ORDER BY pid') as $p) {
    $pid  = (int)$p['pid'];
    $norm = bl_norm($p['name']);
    $cand = $newPartnersByName[$norm] ?? [];
    if (count($cand) === 1)      { $pMatched[]   = [$pid, (string)$p['name'], $cand[0]]; }
    elseif (count($cand) > 1)    { $pAmbig[]     = [$pid, (string)$p['name'], $cand]; }
    else                         { $pUnmatched[] = [$pid, (string)$p['name']]; }
}

/* =====================================================================
 * VENUES: (venue name + owning-provider name) → new venue; name-only fallback
 * ===================================================================== */
$newVenuesByNameProv = [];  // norm(name)||norm(provider) => [new id, …]
$newVenuesByName     = [];  // norm(name) => [new id, …]
$sqlV = 'SELECT v.id, v.name, p.org_name AS provider_name
         FROM venues v LEFT JOIN partners p ON p.id = v.partner_id';
foreach ($dst->query($sqlV) as $r) {
    $nName = bl_norm($r['name']);
    $nProv = bl_norm($r['provider_name']);
    $newVenuesByNameProv[$nName . '||' . $nProv][] = (int)$r['id'];
    $newVenuesByName[$nName][] = (int)$r['id'];
}

$vMatched = $vAmbig = $vUnmatched = [];
foreach ($src->query('SELECT id, name, provider FROM venues ORDER BY id') as $v) {
    $vid   = (int)$v['id'];
    $nName = bl_norm($v['name']);
    $nProv = bl_norm($v['provider']);

    $cand = $newVenuesByNameProv[$nName . '||' . $nProv] ?? [];
    if (count($cand) === 1)   { $vMatched[] = [$vid, (string)$v['name'], $cand[0]]; continue; }
    if (count($cand) > 1)     { $vAmbig[]   = [$vid, (string)$v['name'] . ' [by ' . trim((string)$v['provider']) . ']', $cand]; continue; }

    // Fall back to name-only ONLY when unambiguous.
    $byName = $newVenuesByName[$nName] ?? [];
    if (count($byName) === 1) { $vMatched[]   = [$vid, (string)$v['name'], $byName[0]]; }
    elseif (count($byName) > 1) { $vAmbig[]   = [$vid, (string)$v['name'] . ' [name-only]', $byName]; }
    else                        { $vUnmatched[] = [$vid, (string)$v['name']]; }
}

/* =====================================================================
 * WRITE (idempotent) — only the matched set.
 * ===================================================================== */
$errors = [];
$apply = static function (PDO $dst, string $table, array $matched, bool $commit, array &$errors): int {
    if (!$commit) { return count($matched); }
    $upd = $dst->prepare("UPDATE $table SET legacy_id = :lid WHERE id = :id");
    $n = 0;
    foreach ($matched as [$legacyId, , $newId]) {
        try { $upd->execute([':lid' => $legacyId, ':id' => $newId]); $n++; }
        catch (Throwable $e) { $errors[] = "$table id=$newId legacy_id=$legacyId: " . $e->getMessage(); }
    }
    return $n;
};
$pWrote = $apply($dst, 'partners', $pMatched, $commit, $errors);
$vWrote = $apply($dst, 'venues',   $vMatched, $commit, $errors);

/* =====================================================================
 * REPORT
 * ===================================================================== */
$verb = $dryRun ? 'would set' : 'set';
$dump = static function (string $title, array $rows, bool $withCand = false): void {
    echo "  -- $title (" . count($rows) . ") --\n";
    foreach ($rows as $r) {
        if ($withCand) {
            echo "     legacy #{$r[0]}  \"{$r[1]}\"  → new candidates: " . implode(',', $r[2]) . "\n";
        } elseif (isset($r[2])) {
            echo "     legacy #{$r[0]}  \"{$r[1]}\"  → new #{$r[2]}\n";
        } else {
            echo "     legacy #{$r[0]}  \"{$r[1]}\"\n";
        }
    }
};

echo "PROVIDERS (provider.php?pid=… → partners.legacy_id)\n";
echo "  matched: " . count($pMatched) . " ($verb) · ambiguous: " . count($pAmbig) . " · unmatched: " . count($pUnmatched) . "\n";
if ($pAmbig)     { $dump('AMBIGUOUS providers', $pAmbig, true); }
if ($pUnmatched) { $dump('UNMATCHED providers', $pUnmatched); }

echo "\nVENUES (venue.php?venueid=… → venues.legacy_id)\n";
echo "  matched: " . count($vMatched) . " ($verb) · ambiguous: " . count($vAmbig) . " · unmatched: " . count($vUnmatched) . "\n";
if ($vAmbig)     { $dump('AMBIGUOUS venues', $vAmbig, true); }
if ($vUnmatched) { $dump('UNMATCHED venues', $vUnmatched); }

echo "\n== Summary ==\n";
echo "  partners $verb: " . ($dryRun ? count($pMatched) : $pWrote) . "\n";
echo "  venues   $verb: " . ($dryRun ? count($vMatched) : $vWrote) . "\n";
if ($errors) {
    echo "  ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $e) { echo "    $e\n"; }
}
echo "  Hand-set stragglers:  UPDATE <table> SET legacy_id = <legacyId> WHERE id = <newId>;\n";
if ($dryRun) { echo "\nDRY-RUN — nothing was written. Re-run with --commit to write.\n"; }

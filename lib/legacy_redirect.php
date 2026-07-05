<?php
declare(strict_types=1);

/**
 * U6 Phase-4 — ID-based legacy 301s for the two per-record legacy URLs:
 *   /venue.php?venueid=<N>   → /venues/{slug}     (published)   else /venues
 *   /provider.php?pid=<N>    → /providers/{slug}  (approved)    else /providers
 *
 * Resolves by legacy_id (backfilled by db/backfill_legacy_ids.php). Unresolved /
 * unpublished ids 301 to the hub (never 404) to preserve link equity. Emits a
 * REAL 301 (permanent), single hop, then exits. DB is only touched when a legacy
 * path actually matches (lazy db_pdo). Prepared statements.
 *
 * Fixed legacy paths (venues.php, providers.php, about.php, …) are handled in
 * .htaccess; only these two id-based paths need a DB lookup.
 */

require_once __DIR__ . '/helpers.php';       // base_url()
require_once __DIR__ . '/../config/db.php';  // db_pdo()

/** Send a permanent redirect and stop. Absolute Location; single hop. */
function legacy_redirect_301(string $url): never
{
    header('Location: ' . $url, true, 301);
    exit;
}

/**
 * Handle ONLY '/venue.php' and '/provider.php'. Any other path returns (the
 * caller falls through to normal routing). Matching paths always 301 + exit.
 */
function legacy_redirect_dispatch(string $path, array $query): void
{
    if ($path === '/venue.php') {
        $id = (int)($query['venueid'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = db_pdo()->prepare(
                    "SELECT slug FROM venues WHERE legacy_id = :id AND status = 'published' LIMIT 1"
                );
                $stmt->execute([':id' => $id]);
                $slug = $stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('legacy_redirect venue lookup failed: ' . $e->getMessage());
                $slug = false;
            }
            if ($slug !== false && $slug !== null && $slug !== '') {
                legacy_redirect_301(base_url('venues/' . rawurlencode((string)$slug)));
            }
        }
        legacy_redirect_301(base_url('venues'));   // hub fallback
    }

    if ($path === '/provider.php') {
        $id = (int)($query['pid'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = db_pdo()->prepare(
                    "SELECT slug FROM partners WHERE legacy_id = :id AND status = 'approved' LIMIT 1"
                );
                $stmt->execute([':id' => $id]);
                $slug = $stmt->fetchColumn();
            } catch (Throwable $e) {
                error_log('legacy_redirect provider lookup failed: ' . $e->getMessage());
                $slug = false;
            }
            if ($slug !== false && $slug !== null && $slug !== '') {
                legacy_redirect_301(base_url('providers/' . rawurlencode((string)$slug)));
            }
        }
        legacy_redirect_301(base_url('providers'));   // hub fallback
    }

    // Not a handled legacy path — fall through to normal routing.
}

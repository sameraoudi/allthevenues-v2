<?php
declare(strict_types=1);

/**
 * #3 U-P0 — Provider portal skeleton dispatcher. Reached only when
 * PORTAL_ENABLED is true (index.php gates it). No auth yet (U-P2), no DB, no real
 * UI — just renders a noindex placeholder through the public layout.
 * Expects: string $portalSub (path after /portal) — ignored for now.
 */

require_once __DIR__ . '/../../lib/helpers.php';

$robots           = 'noindex, nofollow';   // the portal is never indexed
$page_title       = 'Provider Portal — All The Venues';
$meta_description = null;
$content_view     = __DIR__ . '/placeholder.php';
require __DIR__ . '/../layout.php';

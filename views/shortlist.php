<?php
declare(strict_types=1);

/**
 * Shortlist handler: /shortlist
 * Renders the saved (published) venues carried in ?ids=1,2,3 via the standard
 * venue card, and hands off to the existing multi-venue enquiry
 * (/enquire?venues=…). The shortlist itself lives in localStorage (app.js);
 * this page only validates + renders the ids it is given.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

$pdo = db_pdo();

/* Parse ?ids → distinct positive ints, cap 12 (matches the app.js CAP). */
$requested = [];
foreach (explode(',', (string)($_GET['ids'] ?? '')) as $part) {
    $n = (int)trim($part);
    if ($n > 0 && !in_array($n, $requested, true)) { $requested[] = $n; }
    if (count($requested) >= 12) { break; }
}

$rows     = $requested ? venue_cards_by_ids($pdo, $requested) : [];
$resolved = array_map(static fn($r) => (int)$r['id'], $rows);   // published, in order

$enquireHref = base_url('enquire') . ($resolved ? '?venues=' . implode(',', $resolved) : '');

$page_title       = 'Your shortlist — All The Venues';
$meta_description = 'Your saved UAE venues — compare your shortlist and send one enquiry for all of them.';
$robots           = 'noindex, follow';   // personal / transient — not for the index

$content_view = __DIR__ . '/content/shortlist.php';
require __DIR__ . '/layout.php';

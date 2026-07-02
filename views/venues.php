<?php
declare(strict_types=1);

/**
 * Listing handler: /venues
 * Fetches published venues with bound filters + pagination, then renders
 * the layout with the venues-list content view.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

$pdo = db_pdo();

$filters = venue_normalise_filters($_GET);

$sort = (string)($_GET['sort'] ?? 'recommended');
if (!isset(venue_sort_options()[$sort])) {
    $sort = 'recommended';
}

$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

$result = venue_list($pdo, $filters, $page, $perPage, $sort);
$venues = $result['rows'];
$total  = $result['total'];

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;   // clamp; re-query if we overshot a filtered set
    $result = venue_list($pdo, $filters, $page, $perPage, $sort);
    $venues = $result['rows'];
}

// Taxonomy for the filter controls.
$eventTypes = venue_event_types($pdo);
$venueTypes = venue_types_all($pdo);
$emirates   = venue_emirates($pdo);

// Contextual title: partner filter → "Venues by {partner}"; else single
// selected emirate → "Venues in {Name}".
$selectedEmirateSlugs = $filters['emirate'] ?? [];
$pageHeading  = 'All venues';
$partnerName  = null;
if (isset($filters['partner'])) {
    $ps = $pdo->prepare("SELECT org_name FROM partners WHERE id = :id AND status='approved' LIMIT 1");
    $ps->execute([':id' => (int)$filters['partner']]);
    $partnerName = $ps->fetchColumn() ?: null;
    if ($partnerName) {
        $pageHeading = 'Venues by ' . $partnerName;
    }
}
if ($partnerName === null && count($selectedEmirateSlugs) === 1) {
    foreach ($emirates as $em) {
        if ($em['slug'] === $selectedEmirateSlugs[0]) {
            $pageHeading = 'Venues in ' . $em['name'];
            break;
        }
    }
}

$page_title   = 'Browse Venues — All The Venues';
$content_view = __DIR__ . '/content/venues-list.php';
require __DIR__ . '/layout.php';

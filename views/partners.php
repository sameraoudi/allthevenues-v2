<?php
declare(strict_types=1);

/**
 * Partner listing handler: /partners
 * Approved partners only, with bound filters + pagination.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/partners.php';

$pdo = db_pdo();

$filters = partner_normalise_filters($_GET);

$sort = (string)($_GET['sort'] ?? 'featured');
if (!isset(partner_sort_options()[$sort])) {
    $sort = 'featured';
}

$perPage = 12;
$page    = max(1, (int)($_GET['page'] ?? 1));

$result = partner_list($pdo, $filters, $page, $perPage, $sort);
$partners = $result['rows'];
$total    = $result['total'];

$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $result = partner_list($pdo, $filters, $page, $perPage, $sort);
    $partners = $result['rows'];
}

$emirates      = venue_emirates($pdo);
$emirateCounts = partner_emirate_counts($pdo);

$page_title   = 'Venue Providers — All The Venues';
$meta_description = 'Explore vetted UAE venue providers — hotels, resorts, restaurants and unique spaces — and enquire through All The Venues.';
$content_view = __DIR__ . '/content/partners-list.php';
require __DIR__ . '/layout.php';

<?php
declare(strict_types=1);

/**
 * Detail handler: /venues/{slug}
 * Looks up a PUBLISHED venue by slug (404 otherwise), loads its images,
 * layout capacity, and similar venues, then renders the layout.
 *
 * Expects $slug in scope (set by the router).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

/** @var string $slug */
$slug = isset($slug) ? (string)$slug : '';

$pdo   = db_pdo();
$venue = venue_by_slug($pdo, $slug);

if ($venue === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$images  = venue_images($pdo, (int)$venue['id']);
$layouts = venue_layout_capacity($pdo, (int)$venue['id']);
$similar = venue_similar($pdo, $venue, 3);

$page_title   = ($venue['name'] ?? 'Venue') . ' — All The Venues';
$content_view = __DIR__ . '/content/venue-detail.php';
require __DIR__ . '/layout.php';

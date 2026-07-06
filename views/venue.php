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
require_once __DIR__ . '/../lib/slug_redirect.php';

/** @var string $slug */
$slug = isset($slug) ? (string)$slug : '';

$pdo   = db_pdo();
$venue = venue_by_slug($pdo, $slug);

if ($venue === null) {
    // #10 — a known OLD slug for a still-published venue wins over the landing
    // fallback: 301 to its current URL (single hop) and exit.
    slug_redirect_maybe_301($pdo, 'venue', $slug);
    // No real venue by this slug. An "{event}-in-{emirate}" slug is an SEO
    // landing page (real venues always win; no event/emirate slug contains
    // '-in-', so a first-split is safe). landing.php renders / redirects / 404s.
    if (strpos($slug, '-in-') !== false) {
        [$evSlug, $emSlug] = explode('-in-', $slug, 2);
        require __DIR__ . '/landing.php';
        exit;
    }
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$images  = venue_images($pdo, (int)$venue['id']);
$layouts = venue_layout_capacity($pdo, (int)$venue['id']);
$similar = venue_similar($pdo, $venue, 3);

$page_title   = ($venue['name'] ?? 'Venue') . ' — All The Venues';
$og_title     = ($venue['name'] ?? 'Venue') . ' — All The Venues';
$meta_description = 'Enquire about ' . ($venue['name'] ?? 'this venue') . ' on All The Venues — view capacity, highlights, photos and location, and send one simple enquiry.';
$content_view = __DIR__ . '/content/venue-detail.php';
require __DIR__ . '/layout.php';

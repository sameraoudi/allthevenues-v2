<?php
declare(strict_types=1);

/**
 * Homepage handler: /
 * Data-driven hero search options, popular event types, and featured venues.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';
require_once __DIR__ . '/../lib/icons.php';

$pdo = db_pdo();

$eventTypes   = venue_event_types($pdo);            // for hero select + popular grid
$popularTypes = array_slice($eventTypes, 0, 7);     // "Popular event types" strip
$emirates     = venue_emirates($pdo);               // hero "Location" select
$featured     = venue_featured($pdo, 3);            // featured (fallback newest)

$page_title   = 'All The Venues — Find the right UAE venue for your next event';
$meta_description = 'Discover and compare curated UAE venues for weddings, corporate events, conferences and parties — then send one simple enquiry through All The Venues.';
$content_view = __DIR__ . '/content/home_content.php';
require __DIR__ . '/layout.php';

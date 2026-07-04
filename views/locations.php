<?php
declare(strict_types=1);

/**
 * Locations handler: /locations
 * An editorial mosaic of emirates, each linking to the matching /venues?emirate
 * filter. An emirate renders only when it has >=1 published venue (gate), and a
 * numeric count shows only once it's reasonably strong. Mirrors /event-types.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

$pdo = db_pdo();

$counts = emirate_published_counts($pdo);   // [slug => published-venue count]
$covers = emirate_cover_images($pdo);       // [slug => top venue image | null]

$emirateNames = [];
foreach (venue_emirates($pdo) as $em) { $emirateNames[$em['slug']] = $em['name']; }

$LOC_COUNT_MIN = 5;   // below this, the pill reads "Explore venues" (no weak number)

// Ordered mosaic — slug => [size, descriptor]. Al Ain is intentionally excluded
// (it belongs to a future areas section). Each renders only with >=1 published venue.
$mosaic = [
    'dubai'          => ['hero', "From the Marina to Downtown and Palm Jumeirah, Dubai offers the UAE's widest choice of event venues."],
    'abu-dhabi'      => ['tall', 'Grand halls, island resorts, and waterfront venues across the capital.'],
    'sharjah'        => ['quad', 'Cultural venues, hotel ballrooms, and banquet halls close to Dubai.'],
    'ajman'          => ['quad', 'Beachfront hotels and boutique settings along the northern coast.'],
    'ras-al-khaimah' => ['quad', 'Mountain, desert, and beach resorts for destination-style events.'],
    'fujairah'       => ['quad', 'East-coast resorts and beachfront venues on the Gulf of Oman.'],
    'umm-al-quwain'  => ['quad', 'Relaxed waterfront and outdoor venues away from the crowds.'],
];

$page_title      = 'Event Venue Locations in the UAE — All The Venues';
$meta_description = 'Browse UAE event venues by location — Dubai, Abu Dhabi, Sharjah, and across the Emirates — and enquire through All The Venues.';
$content_view    = __DIR__ . '/content/locations.php';
require __DIR__ . '/layout.php';

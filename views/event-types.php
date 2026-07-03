<?php
declare(strict_types=1);

/**
 * Event Types handler: /event-types
 * An editorial mosaic of featured event types, each linking to the matching
 * /venues?event_type filter. A type only renders when it has >=1 published
 * venue (gate), and a numeric count only shows once it's reasonably strong.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

$pdo = db_pdo();

$counts = event_type_published_counts($pdo);   // [slug => published-venue count]

$typeNames = [];
foreach (venue_event_types($pdo) as $et) { $typeNames[$et['slug']] = $et['name']; }

$ET_COUNT_MIN = 5;   // below this, the pill reads "Explore venues" (no weak number)

// Featured mosaic — ordered; each: slug => [size, descriptor]. Gala/Yacht sit in
// the config but render only once they gain a published venue (same gate).
$mosaic = [
    'wedding'         => ['hero', 'Ballrooms, gardens and waterfront settings for the celebration of a lifetime.'],
    'corporate-event' => ['tall', 'Polished spaces for offsites, town halls and company milestones.'],
    'conference'      => ['quad', 'Large-format venues for talks, panels and delegate experiences.'],
    'product-launch'  => ['quad', 'High-impact spaces for reveals, showcases and brand moments.'],
    'private-party'   => ['quad', 'Intimate settings for birthdays, anniversaries and celebrations.'],
    'exhibition'      => ['quad', 'Flexible spaces for showcases, displays and visitor flow.'],
    'gala-dinner'     => ['quad', 'Grand, dressed-to-impress settings for award nights and fundraisers.'],
    'yacht-event'     => ['quad', 'Charter decks and marina views for events out on the water.'],
];

$page_title   = 'Event Types — All The Venues';
$content_view = __DIR__ . '/content/event-types.php';
require __DIR__ . '/layout.php';

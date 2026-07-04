<?php
declare(strict_types=1);

/**
 * SEO landing page: /venues/{event}-in-{emirate}
 * Reached from views/venue.php when a slug contains '-in-' and matches no real
 * venue. Strong combos render a full templated page; thin combos 301 to the
 * filtered search; invalid event/emirate 404. Expects $pdo, $evSlug, $emSlug,
 * $slug in scope. Prepared statements; canonical auto-resolves via U5-a.
 */

// LANDING_MIN_VENUES is defined in lib/venues.php (shared with the sitemap).

// --- validate event + emirate (active only) ---
$es = $pdo->prepare('SELECT name FROM event_types WHERE slug = :s AND active = 1');
$es->execute([':s' => $evSlug]);
$eventName = $es->fetchColumn();

$ms = $pdo->prepare('SELECT name FROM emirates WHERE slug = :s AND active = 1');
$ms->execute([':s' => $emSlug]);
$cityName = $ms->fetchColumn();

if ($eventName === false || $cityName === false) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
$eventName = (string)$eventName;
$cityName  = (string)$cityName;

// --- gate on inventory ---
$counts = event_emirate_counts($pdo);
$n = $counts["$evSlug|$emSlug"] ?? 0;

if ($n < LANDING_MIN_VENUES) {
    header('Location: ' . base_url('venues') . query_string(['event_type' => $evSlug, 'emirate' => $emSlug]), true, 301);
    exit;
}

// --- results grid ---
$result = venue_list($pdo, ['event_type' => $evSlug, 'emirate' => [$emSlug]], 1, 12, 'recommended');
$venues = $result['rows'];
$total  = $result['total'];

// --- internal links (only combos with >= MIN venues) ---
$sameEventLinks = [];   // this event, other emirates
foreach (venue_emirates($pdo) as $em) {
    if ($em['slug'] === $emSlug) { continue; }
    if (($counts["$evSlug|{$em['slug']}"] ?? 0) >= LANDING_MIN_VENUES) {
        $sameEventLinks[] = [$em['name'], base_url('venues/' . $evSlug . '-in-' . $em['slug'])];
    }
}
$otherEventLinks = [];   // other events, this city
foreach (venue_event_types($pdo) as $et) {
    if ($et['slug'] === $evSlug) { continue; }
    if (($counts["{$et['slug']}|$emSlug"] ?? 0) >= LANDING_MIN_VENUES) {
        $otherEventLinks[] = [$et['name'], base_url('venues/' . $et['slug'] . '-in-' . $emSlug)];
    }
}

$eventLower = mb_strtolower($eventName);

$page_title      = $eventName . ' venues in ' . $cityName . ' — All The Venues';
$meta_description = 'Browse ' . $total . ' ' . $eventLower . ' venues in ' . $cityName
    . '. Compare capacity, style and location, then enquire in one step through All The Venues.';
$content_view    = __DIR__ . '/content/landing.php';
require __DIR__ . '/layout.php';

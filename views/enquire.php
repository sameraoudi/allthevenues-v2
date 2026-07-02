<?php
declare(strict_types=1);

/**
 * Enquiry stub handler: /enquire?venue={id}
 * U3 builds the real form. For now this is a real (non-dead) page that
 * carries the venue name if a valid published venue id is supplied.
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';

$venueId   = (int)($_GET['venue'] ?? 0);
$venueName = null;
if ($venueId > 0) {
    $venueName = venue_name_by_id(db_pdo(), $venueId);   // null if not found/unpublished
}

$page_title   = 'Enquire — All The Venues';
$content_view = __DIR__ . '/content/enquire.php';
require __DIR__ . '/layout.php';

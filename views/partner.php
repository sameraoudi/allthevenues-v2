<?php
declare(strict_types=1);

/**
 * Partner detail handler: /partners/{slug}
 * Approved partner by slug (404 otherwise — mirrors venue published-only).
 * Expects $slug in scope (set by the router).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/partners.php';

/** @var string $slug */
$slug = isset($slug) ? (string)$slug : '';

$pdo     = db_pdo();
$partner = partner_by_slug($pdo, $slug);

if ($partner === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$partnerVenues = partner_published_venues($pdo, (int)$partner['id']);
$bestFor       = partner_best_for($pdo, (int)$partner['id']);

$page_title   = ($partner['org_name'] ?? 'Provider') . ' — All The Venues';
$meta_description = 'Enquire about ' . ($partner['org_name'] ?? 'this provider') . ', a venue provider on All The Venues — view their venues and send one managed enquiry.';
$content_view = __DIR__ . '/content/partner-detail.php';
require __DIR__ . '/layout.php';

<?php
declare(strict_types=1);

/**
 * Event×city SEO landing content. Expects $eventName, $cityName, $eventLower,
 * $evSlug, $emSlug, $total, $venues, $sameEventLinks, $otherEventLinks.
 * Templated + safe; all dynamic text e()-escaped. FAQ uses native
 * <details>/<summary> (no JS) + a non-executable FAQPage JSON-LD block.
 */
/** @var string $eventName @var string $cityName @var string $eventLower @var int $total */
/** @var array $venues @var array $sameEventLinks @var array $otherEventLinks */
$filterUrl  = base_url('venues') . query_string(['event_type' => $evSlug, 'emirate' => $emSlug]);
$enquireUrl = base_url('enquire') . query_string(['mode' => 'assisted']);

// FAQ copy (approved, templated). Drives both the accordion and the JSON-LD.
$faqs = [
    ["How much do {$eventLower} venues in {$cityName} cost?",
     "{$eventName} venue costs in {$cityName} vary by guest count, date, package, and venue style. Some venues work with a minimum spend, while others offer packages or custom quotes. Share your event details and we'll help match you with suitable options."],
    ["What size {$eventLower}s can these venues host?",
     "The venues here range from intimate gatherings to large-scale events. Use the guest-count filter on the venues page to narrow to the right capacity."],
    ["How many {$eventLower} venues are in {$cityName}?",
     "We currently list {$total} {$eventLower} venues in {$cityName}, across a range of styles and settings. Use the filters to refine by budget, guest count and setting."],
    ["How do I book through All The Venues?",
     "Send one enquiry through the site. We route it to the right venue provider on your behalf — share your details once and we help you reach the venues that fit."],
];
$jsonld = [
    '@context'   => 'https://schema.org',
    '@type'      => 'FAQPage',
    'mainEntity' => array_map(static fn($f) => [
        '@type'          => 'Question',
        'name'           => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faqs),
];
?>
<div class="atv-wrap">
  <div class="venues-crumb">
    <a href="<?= e(base_url('/')) ?>">Home</a> &rsaquo;
    <a href="<?= e(base_url('venues')) ?>">Venues</a> &rsaquo;
    <b><?= e($eventName . ' venues in ' . $cityName) ?></b>
  </div>

  <section class="atv-lp-intro">
    <div class="atv-lp-eyebrow"><?= e($eventName . ' venues · ' . $cityName) ?></div>
    <h1><?= e($eventName . ' venues in ' . $cityName) ?></h1>
    <p>Explore <?= e($eventLower) ?> venues in <?= e($cityName) ?> — compare capacity, setting and key details, then send one simple enquiry through All The Venues. We'll connect you to the right venue and provider.</p>
    <div class="atv-lp-count"><?= e($total . ' ' . $eventLower . ' venues in ' . $cityName) ?></div>
    <div class="atv-lp-actions">
      <a class="atv-btn" href="<?= e($enquireUrl) ?>">Make an enquiry</a>
      <a class="atv-btn atv-btn--ghost" href="<?= e($filterUrl) ?>">Browse all venues</a>
    </div>
  </section>

  <section class="atv-lp-results">
    <div class="atv-lp-results__head">
      <h2><?= e('Top ' . $eventLower . ' venues in ' . $cityName) ?></h2>
      <a class="atv-lp-viewall" href="<?= e($filterUrl) ?>">View all <?= e((string)$total) ?> <?= icon('arrow-right', '', null) ?></a>
    </div>
    <div class="atv-cards">
      <?php foreach ($venues as $venue): require __DIR__ . '/../partials/venue-card.php'; endforeach; ?>
    </div>
  </section>

  <section class="atv-lp-links">
    <h2>Explore more</h2>
    <p class="atv-lp-links__sub">Explore related venue searches across the UAE.</p>

    <?php if ($sameEventLinks): ?>
      <div class="atv-lp-links__group">
        <h3><?= e($eventName . ' venues in other emirates') ?></h3>
        <div class="atv-lp-pills">
          <?php foreach ($sameEventLinks as [$label, $href]): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($otherEventLinks): ?>
      <div class="atv-lp-links__group">
        <h3><?= e('Other events in ' . $cityName) ?></h3>
        <div class="atv-lp-pills">
          <?php foreach ($otherEventLinks as [$label, $href]): ?><a href="<?= e($href) ?>"><?= e($label) ?></a><?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="atv-lp-links__group">
      <h3>Browse by</h3>
      <div class="atv-lp-pills">
        <a href="<?= e(base_url('event-types')) ?>">All event types</a>
        <a href="<?= e(base_url('locations')) ?>">All locations</a>
      </div>
    </div>
  </section>

  <section class="atv-lp-faq">
    <h2>Frequently asked questions</h2>
    <?php foreach ($faqs as [$q, $a]): ?>
      <details class="atv-lp-faq__item">
        <summary><?= e($q) ?></summary>
        <p><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
  </section>

  <section class="atv-lp-cta">
    <div>
      <h2><?= e('Planning a ' . $eventLower . ' in ' . $cityName . '?') ?></h2>
      <p><?= e('Tell us your date, guest count, and style, and we\'ll help connect you with suitable ' . $cityName . ' ' . $eventLower . ' venues.') ?></p>
    </div>
    <a class="atv-btn atv-btn--sand" href="<?= e($enquireUrl) ?>">Make an enquiry</a>
  </section>
</div>

<script type="application/ld+json"><?= json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>

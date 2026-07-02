<?php
declare(strict_types=1);

/**
 * Horizontal venue card for the listing (image left, details right).
 * Expects $venue (a venue_list() row). Outputs the card only; caller lays out.
 * All output escaped with e(); description shown as a plain-text snippet.
 */
/** @var array $venue */
require_once __DIR__ . '/../../lib/icons.php';

$slug       = (string)($venue['slug'] ?? '');
$detailUrl  = base_url('venues/' . rawurlencode($slug));
$enquireUrl = base_url('enquire') . query_string(['venue' => (int)($venue['id'] ?? 0)]);
$imgSrc     = venue_img_src($venue['primary_image'] ?? null);
$capacity   = ($venue['capacity_max'] ?? null) !== null ? (int)$venue['capacity_max'] : null;
$ioLabels   = venue_indoor_outdoor_options();
$ioLabel    = !empty($venue['indoor_outdoor']) ? ($ioLabels[$venue['indoor_outdoor']] ?? $venue['indoor_outdoor']) : '';
$address    = trim(implode(', ', array_filter([
    trim((string)($venue['area'] ?? '')),
    trim((string)($venue['emirate_name'] ?? '')),
])));
if ($address === '' && !empty($venue['partner_name'])) {
    $address = (string)$venue['partner_name'];
}

$min = $venue['minimum_spend'] ?? null;
if ($min !== null && (float)$min > 0) {
    $spend = 'Minimum spend from AED ' . number_format((float)$min);
} elseif (!empty($venue['pricing_level']) && $venue['pricing_level'] !== 'Price on request') {
    $spend = 'Minimum spend available';
} else {
    $spend = 'Price on request';
}
$snip = snippet($venue['description'] ?? '', 150);
?>
<article class="venue-row">
  <a class="venue-row__img" href="<?= e($detailUrl) ?>" aria-label="<?= e($venue['name'] ?? 'Venue') ?>">
    <img src="<?= e($imgSrc) ?>" alt="<?= e($venue['name'] ?? 'Venue') ?>" loading="lazy" width="460" height="360">
    <?php if (!empty($venue['is_featured'])): ?><span class="atv-badge venue-row__badge">Featured</span><?php endif; ?>
  </a>
  <div class="venue-row__body">
    <button type="button" class="venue-row__heart" aria-label="Add to shortlist"><?= icon('heart') ?></button>
    <h3 class="venue-row__title"><a href="<?= e($detailUrl) ?>"><?= e($venue['name'] ?? 'Venue') ?></a></h3>
    <?php if ($address !== ''): ?><div class="venue-row__addr"><?= e($address) ?></div><?php endif; ?>

    <div class="venue-row__chips">
      <?php if ($capacity !== null): ?><span class="atv-chip"><?= icon('users') ?> <?= e(number_format($capacity)) ?></span><?php endif; ?>
      <?php if ($ioLabel !== ''): ?><span class="atv-chip"><?= e($ioLabel) ?></span><?php endif; ?>
      <?php if (!empty($venue['venue_type_name'])): ?><span class="atv-chip"><?= e($venue['venue_type_name']) ?></span><?php endif; ?>
    </div>

    <?php if ($snip !== ''): ?><p class="venue-row__desc"><?= e($snip) ?></p><?php endif; ?>

    <div class="venue-row__foot">
      <span class="venue-row__spend"><?= e($spend) ?></span>
      <span class="venue-row__actions">
        <a class="venue-row__link" href="<?= e($detailUrl) ?>">View details</a>
        <a class="venue-row__enquire" href="<?= e($enquireUrl) ?>">Enquire</a>
      </span>
    </div>
  </div>
</article>

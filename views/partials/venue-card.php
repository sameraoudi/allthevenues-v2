<?php
declare(strict_types=1);

/**
 * Reusable BRANDED venue card (matches the design preview). Expects $venue
 * (a row from venue_list()/venue_featured()/venue_similar()) in scope.
 * Outputs the card only — the caller provides the grid cell.
 *
 * All dynamic output escaped with e(); best_for rendered as escaped chips;
 * description shown as a plain-text snippet.
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

// Price-safe spend label (never invents a price).
$minSpend = $venue['minimum_spend'] ?? null;
if ($minSpend !== null && (float)$minSpend > 0) {
    $spend = 'From AED ' . number_format((float)$minSpend);
} elseif (!empty($venue['pricing_level']) && $venue['pricing_level'] !== 'Price on request') {
    $spend = (string)$venue['pricing_level'];
} else {
    $spend = 'Price on request';
}
$snip = snippet($venue['description'] ?? '', 110);
?>
<article class="atv-card">
  <a class="atv-card__img" href="<?= e($detailUrl) ?>" aria-label="<?= e($venue['name'] ?? 'Venue') ?>">
    <img src="<?= e($imgSrc) ?>" alt="<?= e($venue['name'] ?? 'Venue') ?>" loading="lazy" width="800" height="600">
    <span class="atv-card__badges">
      <?php if (!empty($venue['is_featured'])): ?><span class="atv-badge">Featured</span><?php endif; ?>
      <?php if (!empty($venue['partner_id'])): ?><span class="atv-badge atv-badge--partner">Provider managed</span><?php endif; ?>
    </span>
    <span class="atv-card__heart" aria-hidden="true"><?= icon('heart') ?></span>
  </a>
  <div class="atv-card__body">
    <h3><a href="<?= e($detailUrl) ?>"><?= e($venue['name'] ?? 'Venue') ?></a></h3>
    <?php if ($address !== ''): ?><div class="atv-card__addr"><?= e($address) ?></div><?php endif; ?>

    <div class="atv-card__chips">
      <?php if ($capacity !== null): ?>
        <span class="atv-chip"><?= icon('users') ?> <?= e(number_format($capacity)) ?></span>
      <?php endif; ?>
      <?php if ($ioLabel !== ''): ?><span class="atv-chip"><?= e($ioLabel) ?></span><?php endif; ?>
      <?php if (!empty($venue['venue_type_name'])): ?><span class="atv-chip"><?= e($venue['venue_type_name']) ?></span><?php endif; ?>
    </div>

    <?php if ($snip !== ''): ?><p class="atv-card__desc"><?= e($snip) ?></p><?php endif; ?>

    <div class="atv-card__foot">
      <span class="spend"><?= e($spend) ?></span>
      <a class="link" href="<?= e($detailUrl) ?>">View details <?= icon('arrow-right', '', null) ?></a>
    </div>
  </div>
</article>

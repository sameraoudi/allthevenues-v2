<?php
declare(strict_types=1);

/**
 * Reusable venue card. Expects $venue (an array row from venue_list()/
 * venue_similar()) in scope. All output escaped with e(); best_for rendered
 * as escaped tags; description shown as a plain-text snippet.
 */

/** @var array $venue */
$slug        = (string)($venue['slug'] ?? '');
$detailUrl   = base_url('venues/' . rawurlencode($slug));
$enquireUrl  = base_url('enquire') . query_string(['venue' => (int)($venue['id'] ?? 0)]);
$imgSrc      = venue_img_src($venue['primary_image'] ?? null);
$tags        = tags_from($venue['best_for'] ?? null, 3);
$capacity    = ($venue['capacity_max'] ?? null) !== null ? (int)$venue['capacity_max'] : null;
$location    = trim(implode(', ', array_filter([
    trim((string)($venue['area'] ?? '')),
    trim((string)($venue['emirate_name'] ?? '')),
])));
?>
<div class="col-12 col-sm-6 col-lg-4 mb-4">
  <div class="card h-100 shadow-sm venue-card">
    <a href="<?= e($detailUrl) ?>" class="text-decoration-none">
      <div class="venue-card__img ratio ratio-4x3">
        <img src="<?= e($imgSrc) ?>" alt="<?= e($venue['name'] ?? 'Venue') ?>"
             class="card-img-top object-fit-cover" loading="lazy">
      </div>
    </a>
    <?php if (!empty($venue['is_featured'])): ?>
      <span class="badge text-bg-warning position-absolute top-0 start-0 m-2">Featured</span>
    <?php endif; ?>

    <div class="card-body d-flex flex-column">
      <div class="d-flex flex-wrap gap-2 mb-1">
        <?php if (!empty($venue['venue_type_name'])): ?>
          <span class="badge rounded-pill text-bg-light border"><?= e($venue['venue_type_name']) ?></span>
        <?php endif; ?>
        <?php if (!empty($venue['pricing_level'])): ?>
          <span class="badge rounded-pill text-bg-light border"><?= e($venue['pricing_level']) ?></span>
        <?php endif; ?>
      </div>

      <h3 class="h5 card-title mb-1">
        <a href="<?= e($detailUrl) ?>" class="stretched-link-none text-reset text-decoration-none">
          <?= e($venue['name'] ?? 'Venue') ?>
        </a>
      </h3>

      <?php if (!empty($venue['partner_name'])): ?>
        <div class="text-muted small mb-1"><?= e($venue['partner_name']) ?></div>
      <?php endif; ?>

      <div class="text-muted small mb-2">
        <?php if ($location !== ''): ?>
          <span class="me-2">📍 <?= e($location) ?></span>
        <?php endif; ?>
        <?php if ($capacity !== null): ?>
          <span>👥 up to <?= e((string)$capacity) ?></span>
        <?php endif; ?>
      </div>

      <?php if ($tags): ?>
        <div class="d-flex flex-wrap gap-1 mb-2">
          <?php foreach ($tags as $t): ?>
            <span class="badge text-bg-secondary fw-normal"><?= e($t) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php $snip = snippet($venue['description'] ?? '', 120); ?>
      <?php if ($snip !== ''): ?>
        <p class="card-text small text-body-secondary flex-grow-1"><?= e($snip) ?></p>
      <?php endif; ?>

      <div class="d-flex gap-2 mt-auto pt-2">
        <a href="<?= e($detailUrl) ?>" class="btn btn-primary btn-sm flex-fill">View details</a>
        <a href="<?= e($enquireUrl) ?>" class="btn btn-outline-secondary btn-sm flex-fill">Enquire</a>
      </div>
    </div>
  </div>
</div>

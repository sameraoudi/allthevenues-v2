<?php
declare(strict_types=1);

/**
 * Venue detail content view. Expects in scope:
 *   array $venue, array $images, array $layouts, array $similar.
 *
 * IMPORTANT — output rules:
 *   - Rich-text fields (description, packages, special_offer, av_support,
 *     food_beverage, restrictions, atv_review) were sanitized at import
 *     (U1b) to a safe tag allowlist. They are rendered AS HTML (no e()).
 *   - Everything else (name, location, numbers, tags, dates) is escaped
 *     with e().
 *   - map_embed is intentionally NOT rendered (raw iframe; deferred to the
 *     maps + CSP unit).
 */

/** @var array $venue @var array $images @var array $layouts @var array $similar */

$name       = (string)($venue['name'] ?? 'Venue');
$location   = trim(implode(', ', array_filter([
    trim((string)($venue['area'] ?? '')),
    trim((string)($venue['emirate_name'] ?? '')),
])));
$tags       = tags_from($venue['best_for'] ?? null);
$enquireUrl = base_url('enquire') . query_string(['venue' => (int)$venue['id']]);
$ioLabels   = venue_indoor_outdoor_options();

// Rich-text (already-sanitized) sections to render as HTML.
$richSections = [
    'Description'          => $venue['description']   ?? '',
    'Facilities'           => $venue['facilities']    ?? '',
    'Food & beverage'      => $venue['food_beverage'] ?? '',
    'AV & technical'       => $venue['av_support']    ?? '',
    'Packages'             => $venue['packages']      ?? '',
    'Special offer'        => $venue['special_offer'] ?? '',
    'Notes & restrictions' => $venue['restrictions']  ?? '',
];

$capMax = ($venue['capacity_max'] ?? null) !== null ? (int)$venue['capacity_max'] : null;
$capMin = ($venue['capacity_min'] ?? null) !== null ? (int)$venue['capacity_min'] : null;
$minSpend = ($venue['minimum_spend'] ?? null);
$updated = !empty($venue['updated_at']) ? date('j M Y', strtotime((string)$venue['updated_at'])) : null;
?>
<nav aria-label="breadcrumb" class="bg-light border-bottom">
  <div class="container py-2">
    <ol class="breadcrumb mb-0 small">
      <li class="breadcrumb-item"><a href="<?= e(base_url('/')) ?>">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= e(base_url('venues')) ?>">Venues</a></li>
      <li class="breadcrumb-item active" aria-current="page"><?= e($name) ?></li>
    </ol>
  </div>
</nav>

<div class="container py-4">
  <div class="row g-4">
    <div class="col-lg-8">

      <!-- Hero gallery -->
      <?php if (count($images) > 1): ?>
        <div id="venueGallery" class="carousel slide mb-4 shadow-sm rounded overflow-hidden" data-bs-ride="false">
          <div class="carousel-indicators">
            <?php foreach ($images as $i => $img): ?>
              <button type="button" data-bs-target="#venueGallery" data-bs-slide-to="<?= e((string)$i) ?>"
                      class="<?= $i === 0 ? 'active' : '' ?>" aria-label="Slide <?= e((string)($i + 1)) ?>"></button>
            <?php endforeach; ?>
          </div>
          <div class="carousel-inner ratio ratio-16x9 bg-light">
            <?php foreach ($images as $i => $img): ?>
              <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                <img src="<?= e(venue_img_src($img['file_path'] ?? null)) ?>"
                     alt="<?= e($img['alt_text'] ?? $name) ?>" class="d-block w-100 object-fit-cover">
              </div>
            <?php endforeach; ?>
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#venueGallery" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#venueGallery" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span>
          </button>
        </div>
      <?php else: ?>
        <div class="ratio ratio-16x9 bg-light mb-4 shadow-sm rounded overflow-hidden">
          <img src="<?= e(venue_img_src($images[0]['file_path'] ?? null)) ?>"
               alt="<?= e($images[0]['alt_text'] ?? $name) ?>" class="object-fit-cover">
        </div>
      <?php endif; ?>

      <!-- Title block -->
      <div class="mb-3">
        <div class="d-flex flex-wrap gap-2 mb-2">
          <?php if (!empty($venue['venue_type_name'])): ?>
            <span class="badge rounded-pill text-bg-primary"><?= e($venue['venue_type_name']) ?></span>
          <?php endif; ?>
          <?php if (!empty($venue['indoor_outdoor'])): ?>
            <span class="badge rounded-pill text-bg-light border"><?= e($ioLabels[$venue['indoor_outdoor']] ?? $venue['indoor_outdoor']) ?></span>
          <?php endif; ?>
          <?php if (!empty($venue['is_featured'])): ?>
            <span class="badge rounded-pill text-bg-warning">Featured</span>
          <?php endif; ?>
          <?php if (!empty($venue['is_verified'])): ?>
            <span class="badge rounded-pill text-bg-success">Verified</span>
          <?php endif; ?>
        </div>
        <h1 class="h2 mb-1"><?= e($name) ?></h1>
        <?php if ($location !== ''): ?>
          <p class="text-muted mb-1">📍 <?= e($location) ?></p>
        <?php endif; ?>
        <?php if (!empty($venue['partner_name'])): ?>
          <p class="text-muted small mb-0">By <?= e($venue['partner_name']) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($tags): ?>
        <div class="d-flex flex-wrap gap-1 mb-4">
          <span class="small text-muted me-1">Best for:</span>
          <?php foreach ($tags as $t): ?>
            <span class="badge text-bg-secondary fw-normal"><?= e($t) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Rich-text sections (sanitized HTML, rendered raw) -->
      <?php foreach ($richSections as $title => $html): ?>
        <?php if (trim((string)$html) !== ''): ?>
          <section class="mb-4">
            <h2 class="h5 border-bottom pb-2 mb-3"><?= e($title) ?></h2>
            <div class="venue-richtext"><?= $html /* pre-sanitized at import — do NOT escape */ ?></div>
          </section>
        <?php endif; ?>
      <?php endforeach; ?>

      <!-- Layout capacity table -->
      <?php if ($layouts): ?>
        <section class="mb-4">
          <h2 class="h5 border-bottom pb-2 mb-3">Layout capacity</h2>
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead>
                <tr><th scope="col">Layout</th><th scope="col" class="text-end">Capacity</th></tr>
              </thead>
              <tbody>
                <?php foreach ($layouts as $l): ?>
                  <tr>
                    <td><?= e($l['layout_type']) ?></td>
                    <td class="text-end"><?= e(number_format((int)$l['capacity'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </div>

    <!-- Sidebar: quick facts + enquiry CTA -->
    <div class="col-lg-4">
      <div class="card shadow-sm mb-4 sticky-lg-top" style="top:1rem;">
        <div class="card-body">
          <h2 class="h5 card-title">Quick facts</h2>
          <ul class="list-unstyled small mb-3">
            <?php if ($capMax !== null): ?>
              <li class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Max capacity</span><span class="fw-semibold"><?= e(number_format($capMax)) ?> guests</span>
              </li>
            <?php endif; ?>
            <?php if ($capMin !== null): ?>
              <li class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Min guests</span><span class="fw-semibold"><?= e(number_format($capMin)) ?></span>
              </li>
            <?php endif; ?>
            <?php if (!empty($venue['pricing_level'])): ?>
              <li class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Pricing</span><span class="fw-semibold"><?= e($venue['pricing_level']) ?></span>
              </li>
            <?php endif; ?>
            <?php if ($minSpend !== null && (float)$minSpend > 0): ?>
              <li class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Minimum spend</span><span class="fw-semibold">AED <?= e(number_format((float)$minSpend)) ?></span>
              </li>
            <?php endif; ?>
            <?php if (!empty($venue['venue_type_name'])): ?>
              <li class="d-flex justify-content-between border-bottom py-2">
                <span class="text-muted">Type</span><span class="fw-semibold"><?= e($venue['venue_type_name']) ?></span>
              </li>
            <?php endif; ?>
            <?php if ($location !== ''): ?>
              <li class="d-flex justify-content-between py-2">
                <span class="text-muted">Location</span><span class="fw-semibold text-end"><?= e($location) ?></span>
              </li>
            <?php endif; ?>
          </ul>
          <div class="d-grid">
            <a href="<?= e($enquireUrl) ?>" class="btn btn-primary btn-lg">Enquire about this venue</a>
          </div>
          <?php if ($updated !== null): ?>
            <p class="text-muted small text-center mt-3 mb-0">Last updated <?= e($updated) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Similar venues -->
  <?php if ($similar): ?>
    <section class="mt-4 pt-2">
      <h2 class="h4 mb-3">Similar venues</h2>
      <div class="row">
        <?php foreach ($similar as $venue): /* reuse card; $venue reassigned intentionally */ ?>
          <?php require __DIR__ . '/../partials/venue-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

<?php
declare(strict_types=1);

/**
 * Listing content view. Expects in scope:
 *   array $venues, int $total, int $page, int $totalPages, array $filters,
 *   array $eventTypes, $venueTypes, $emirates.
 */

/** @var array $venues @var int $total @var int $page @var int $totalPages */
/** @var array $filters @var array $eventTypes @var array $venueTypes @var array $emirates */

$f = $filters;
// Base params carried across pagination links (filters only).
$carry = [
    'event_type'     => $f['event_type']     ?? '',
    'venue_type'     => $f['venue_type']     ?? '',
    'emirate'        => $f['emirate']        ?? '',
    'guest_count'    => $f['guest_count']    ?? '',
    'budget'         => $f['budget']         ?? '',
    'indoor_outdoor' => $f['indoor_outdoor'] ?? '',
];
$hasFilters = (bool)array_filter($carry, static fn($v) => $v !== '');
?>
<section class="bg-light border-bottom">
  <div class="container py-4">
    <h1 class="h3 mb-1">Browse Venues</h1>
    <p class="text-muted mb-0">Find the perfect venue for your event across the UAE.</p>
  </div>
</section>

<div class="container py-4">
  <div class="row">
    <!-- Filters -->
    <aside class="col-lg-3 mb-4">
      <form method="get" action="<?= e(base_url('venues')) ?>" class="card card-body shadow-sm">
        <h2 class="h6 text-uppercase text-muted mb-3">Filter</h2>

        <div class="mb-3">
          <label for="f-event" class="form-label small fw-semibold">Event type</label>
          <select id="f-event" name="event_type" class="form-select form-select-sm">
            <option value="">Any event</option>
            <?php foreach ($eventTypes as $et): ?>
              <option value="<?= e($et['slug']) ?>" <?= (($f['event_type'] ?? '') === $et['slug']) ? 'selected' : '' ?>>
                <?= e($et['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="f-type" class="form-label small fw-semibold">Venue type</label>
          <select id="f-type" name="venue_type" class="form-select form-select-sm">
            <option value="">Any type</option>
            <?php foreach ($venueTypes as $vt): ?>
              <option value="<?= e($vt['slug']) ?>" <?= (($f['venue_type'] ?? '') === $vt['slug']) ? 'selected' : '' ?>>
                <?= e($vt['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="f-emirate" class="form-label small fw-semibold">Emirate</label>
          <select id="f-emirate" name="emirate" class="form-select form-select-sm">
            <option value="">Any emirate</option>
            <?php foreach ($emirates as $em): ?>
              <option value="<?= e($em['slug']) ?>" <?= (($f['emirate'] ?? '') === $em['slug']) ? 'selected' : '' ?>>
                <?= e($em['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="f-guests" class="form-label small fw-semibold">Guest count</label>
          <select id="f-guests" name="guest_count" class="form-select form-select-sm">
            <option value="">Any size</option>
            <?php foreach (venue_guest_bands() as $val => [$label, $min]): ?>
              <option value="<?= e($val) ?>" <?= (($f['guest_count'] ?? '') === $val) ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="f-budget" class="form-label small fw-semibold">Budget</label>
          <select id="f-budget" name="budget" class="form-select form-select-sm">
            <option value="">Any budget</option>
            <?php foreach (venue_pricing_levels() as $pl): ?>
              <option value="<?= e($pl) ?>" <?= (($f['budget'] ?? '') === $pl) ? 'selected' : '' ?>>
                <?= e($pl) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label for="f-io" class="form-label small fw-semibold">Indoor / outdoor</label>
          <select id="f-io" name="indoor_outdoor" class="form-select form-select-sm">
            <option value="">Any setting</option>
            <?php foreach (venue_indoor_outdoor_options() as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= (($f['indoor_outdoor'] ?? '') === $val) ? 'selected' : '' ?>>
                <?= e($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-sm">Apply filters</button>
          <?php if ($hasFilters): ?>
            <a href="<?= e(base_url('venues')) ?>" class="btn btn-outline-secondary btn-sm">Clear all</a>
          <?php endif; ?>
        </div>
      </form>
    </aside>

    <!-- Results -->
    <div class="col-lg-9">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="mb-0 text-muted">
          <strong><?= e((string)$total) ?></strong> venue<?= $total === 1 ? '' : 's' ?> found
        </p>
      </div>

      <?php if (!$venues): ?>
        <div class="text-center py-5 my-4 bg-light rounded">
          <p class="h5 mb-2">No venues match your filters.</p>
          <p class="text-muted mb-3">Try widening your search.</p>
          <?php if ($hasFilters): ?>
            <a href="<?= e(base_url('venues')) ?>" class="btn btn-outline-primary">Clear filters</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="row">
          <?php foreach ($venues as $venue): ?>
            <?php require __DIR__ . '/../partials/venue-card.php'; ?>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <nav aria-label="Venue pages" class="mt-3">
            <ul class="pagination justify-content-center">
              <?php
                $prevQs = query_string($carry + ['page' => max(1, $page - 1)]);
                $nextQs = query_string($carry + ['page' => min($totalPages, $page + 1)]);
              ?>
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(base_url('venues') . $prevQs) ?>">Previous</a>
              </li>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php $qs = query_string($carry + ['page' => $i]); ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= e(base_url('venues') . $qs) ?>"><?= e((string)$i) ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= e(base_url('venues') . $nextQs) ?>">Next</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

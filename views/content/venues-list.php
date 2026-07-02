<?php
declare(strict_types=1);

/**
 * Listing content view (Coastal UAE visual pass). Expects in scope:
 *   array $venues, int $total, int $page, int $totalPages, array $filters,
 *   array $eventTypes, $venueTypes, $emirates, string $sort, $pageHeading.
 */
/** @var array $venues @var int $total @var int $page @var int $totalPages */
/** @var array $filters @var array $eventTypes @var array $venueTypes @var array $emirates */
/** @var string $sort @var string $pageHeading */
require_once __DIR__ . '/../../lib/icons.php';

$f          = $filters;
$listUrl    = base_url('venues');
$selEmirate = $f['emirate'] ?? [];
$isSel      = static fn(string $k, string $v): string => ((string)($f[$k] ?? '') === $v) ? ' selected' : '';

// slug → name maps for the active-filter chips.
$emNames = $vtNames = $etNames = [];
foreach ($emirates as $x)  { $emNames[$x['slug']] = $x['name']; }
foreach ($venueTypes as $x){ $vtNames[$x['slug']] = $x['name']; }
foreach ($eventTypes as $x){ $etNames[$x['slug']] = $x['name']; }

// Link to the listing with a (possibly modified) filter set.
$chipLink = static function (array $mod) use ($listUrl, $sort): string {
    return $listUrl . query_string(venue_filter_params($mod, $sort));
};

// Build the active-filter chips: [label, remove-link].
$chips = [];
foreach ((array)$selEmirate as $slug) {
    $mod = $f; $mod['emirate'] = array_values(array_diff((array)$selEmirate, [$slug]));
    if (!$mod['emirate']) unset($mod['emirate']);
    $chips[] = [$emNames[$slug] ?? $slug, $chipLink($mod)];
}
if (isset($f['venue_type']))     { $m = $f; unset($m['venue_type']);     $chips[] = [$vtNames[$f['venue_type']] ?? $f['venue_type'], $chipLink($m)]; }
if (isset($f['event_type']))     { $m = $f; unset($m['event_type']);     $chips[] = [$etNames[$f['event_type']] ?? $f['event_type'], $chipLink($m)]; }
if (isset($f['guest_count']))    { $m = $f; unset($m['guest_count']);    $chips[] = [venue_guest_bands()[$f['guest_count']][0] ?? $f['guest_count'], $chipLink($m)]; }
if (isset($f['budget']))         { $m = $f; unset($m['budget']);         $chips[] = [$f['budget'], $chipLink($m)]; }
if (isset($f['indoor_outdoor'])) { $m = $f; unset($m['indoor_outdoor']); $chips[] = [venue_indoor_outdoor_options()[$f['indoor_outdoor']] ?? $f['indoor_outdoor'], $chipLink($m)]; }
if (isset($f['partner']) && ($partnerName ?? null)) { $m = $f; unset($m['partner']); $chips[] = ['Partner: ' . $partnerName, $chipLink($m)]; }
$hasFilters = (bool)$chips;
?>
<div class="atv-wrap">
  <div class="venues-crumb"><a href="<?= e(base_url('/')) ?>">Home</a> &rsaquo; <b>Venues</b></div>

  <div class="venues-head">
    <div>
      <h1><?= e($pageHeading) ?></h1>
      <div class="venues-head__cnt"><?= e(number_format($total)) ?> venue<?= $total === 1 ? '' : 's' ?> match your search</div>
    </div>
    <div class="venues-head__right">
      <button type="button" class="atv-btn atv-btn--sm atv-btn--ghost venues-filter-toggle" data-filters-toggle aria-expanded="false">Filters</button>
      <form class="venues-sort" method="get" action="<?= e($listUrl) ?>">
        <?php foreach (venue_filter_params($f, 'recommended') as $k => $v): ?>
          <?php if (is_array($v)): foreach ($v as $vv): ?>
            <input type="hidden" name="<?= e($k) ?>[]" value="<?= e((string)$vv) ?>">
          <?php endforeach; else: ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
          <?php endif; endforeach; ?>
        <label class="venues-sort__lbl" for="sortsel">Sort by</label>
        <select id="sortsel" name="sort" data-autosubmit>
          <?php foreach (venue_sort_options() as $k => $label): ?>
            <option value="<?= e($k) ?>"<?= $sort === $k ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="venues-sort__go">Sort</button>
      </form>
    </div>
  </div>

  <?php if ($chips): ?>
    <div class="active-chips">
      <span class="active-chips__lbl">Active filters:</span>
      <?php foreach ($chips as [$label, $href]): ?>
        <a class="active-chip" href="<?= e($href) ?>"><?= e($label) ?> <span aria-hidden="true">&times;</span></a>
      <?php endforeach; ?>
      <a class="active-chips__clear" href="<?= e($listUrl) ?>">Clear all</a>
    </div>
  <?php endif; ?>

  <div class="venues-layout">
    <!-- Filter sidebar -->
    <aside class="venues-filters" id="venuesFilters">
      <form method="get" action="<?= e($listUrl) ?>">
        <?php if ($sort !== 'recommended'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
        <div class="venues-filters__head">
          <b>Refine your search</b>
          <a href="<?= e($listUrl) ?>">Reset all</a>
        </div>

        <div class="fgroup">
          <div class="fgroup__t">Location</div>
          <?php foreach ($emirates as $em): ?>
            <label class="fopt">
              <input type="checkbox" name="emirate[]" value="<?= e($em['slug']) ?>"<?= in_array($em['slug'], (array)$selEmirate, true) ? ' checked' : '' ?>>
              <?= e($em['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="fgroup">
          <label class="fgroup__t" for="fl-vt">Venue Type</label>
          <select id="fl-vt" name="venue_type" class="fsel">
            <option value="">All types</option>
            <?php foreach ($venueTypes as $vt): ?><option value="<?= e($vt['slug']) ?>"<?= $isSel('venue_type', $vt['slug']) ?>><?= e($vt['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fgroup">
          <label class="fgroup__t" for="fl-et">Event Type</label>
          <select id="fl-et" name="event_type" class="fsel">
            <option value="">All event types</option>
            <?php foreach ($eventTypes as $et): ?><option value="<?= e($et['slug']) ?>"<?= $isSel('event_type', $et['slug']) ?>><?= e($et['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fgroup">
          <label class="fgroup__t" for="fl-g">Guest Count</label>
          <select id="fl-g" name="guest_count" class="fsel">
            <option value="">Any number</option>
            <?php foreach (venue_guest_bands() as $k => [$label, $min]): ?><option value="<?= e($k) ?>"<?= $isSel('guest_count', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fgroup">
          <label class="fgroup__t" for="fl-b">Budget</label>
          <select id="fl-b" name="budget" class="fsel">
            <option value="">Any budget</option>
            <?php foreach (venue_pricing_levels() as $pl): ?><option value="<?= e($pl) ?>"<?= $isSel('budget', $pl) ?>><?= e($pl) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fgroup">
          <label class="fgroup__t" for="fl-io">Indoor / Outdoor</label>
          <select id="fl-io" name="indoor_outdoor" class="fsel">
            <option value="">Any</option>
            <?php foreach (venue_indoor_outdoor_options() as $k => $label): ?><option value="<?= e($k) ?>"<?= $isSel('indoor_outdoor', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="atv-btn venues-filters__apply">Apply Filters</button>
      </form>
    </aside>

    <!-- Results -->
    <div class="venues-results">
      <?php if (!$venues): ?>
        <div class="venues-empty">
          <p class="h5 mb-2">No venues match your filters.</p>
          <p class="text-muted mb-3">Try widening your search.</p>
          <?php if ($hasFilters): ?><a class="atv-btn" href="<?= e($listUrl) ?>">Clear filters</a><?php endif; ?>
        </div>
      <?php else: ?>
        <?php foreach ($venues as $venue): ?>
          <?php require __DIR__ . '/../partials/venue-row.php'; ?>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
          <nav class="venue-pager" aria-label="Venue pages">
            <?php if ($page > 1): ?>
              <a class="venue-pager__i" href="<?= e($listUrl . query_string(venue_filter_params($f, $sort, $page - 1))) ?>" aria-label="Previous">&lsaquo;</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a class="venue-pager__i<?= $i === $page ? ' is-active' : '' ?>" href="<?= e($listUrl . query_string(venue_filter_params($f, $sort, $i))) ?>"><?= e((string)$i) ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
              <a class="venue-pager__i" href="<?= e($listUrl . query_string(venue_filter_params($f, $sort, $page + 1))) ?>" aria-label="Next">&rsaquo;</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

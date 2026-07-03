<?php
declare(strict_types=1);

/**
 * Partner listing content. Reuses the venues-list layout/sidebar/chip styles.
 * Expects: array $partners, int $total, $page, $totalPages, array $filters,
 * string $sort, array $emirates, $emirateCounts.
 */
/** @var array $partners @var int $total @var int $page @var int $totalPages */
/** @var array $filters @var string $sort @var array $emirates @var array $emirateCounts */
require_once __DIR__ . '/../../lib/partners.php';

$f       = $filters;
$listUrl = base_url('providers');
$selEm   = (array)($f['emirate'] ?? []);
$selTy   = (array)($f['type'] ?? []);

$emNames = [];
foreach ($emirates as $x) { $emNames[$x['slug']] = $x['name']; }

$chipLink = static fn(array $mod): string => $listUrl . query_string(partner_filter_params($mod, $sort));

$chips = [];
if (isset($f['q']))       { $m = $f; unset($m['q']);       $chips[] = ['“' . $f['q'] . '”', $chipLink($m)]; }
foreach ($selEm as $slug)  { $m = $f; $m['emirate'] = array_values(array_diff($selEm, [$slug])); if (!$m['emirate']) unset($m['emirate']); $chips[] = [$emNames[$slug] ?? $slug, $chipLink($m)]; }
foreach ($selTy as $t)     { $m = $f; $m['type'] = array_values(array_diff($selTy, [$t])); if (!$m['type']) unset($m['type']); $chips[] = [partner_type_buckets()[$t][0] ?? $t, $chipLink($m)]; }
if (!empty($f['featured'])) { $m = $f; unset($m['featured']); $chips[] = ['Featured only', $chipLink($m)]; }
?>
<div class="atv-wrap">
  <div class="venues-crumb"><a href="<?= e(base_url('/')) ?>">Home</a> &rsaquo; <b>Venue Providers</b></div>

  <div class="venues-head">
    <div>
      <h1>Venue Providers</h1>
      <div class="partners-sub">Explore hotels, resorts, restaurants, and unique-venue operators across the UAE, with selected providers verified by All The Venues.</div>
      <div class="venues-head__cnt"><strong><?= e(number_format($total)) ?></strong> provider<?= $total === 1 ? '' : 's' ?></div>
    </div>
    <div class="venues-head__right">
      <button type="button" class="atv-btn atv-btn--sm atv-btn--ghost venues-filter-toggle" data-filters-toggle aria-expanded="false">Filters</button>
      <form class="venues-sort" method="get" action="<?= e($listUrl) ?>">
        <?php foreach (partner_filter_params($f, 'featured') as $k => $v): ?>
          <?php if (is_array($v)): foreach ($v as $vv): ?>
            <input type="hidden" name="<?= e($k) ?>[]" value="<?= e((string)$vv) ?>">
          <?php endforeach; else: ?>
            <input type="hidden" name="<?= e($k) ?>" value="<?= e((string)$v) ?>">
          <?php endif; endforeach; ?>
        <label class="venues-sort__lbl" for="psort">Sort by</label>
        <select id="psort" name="sort" data-autosubmit>
          <?php foreach (partner_sort_options() as $k => $label): ?>
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
    <aside class="venues-filters" id="venuesFilters">
      <form method="get" action="<?= e($listUrl) ?>">
        <?php if ($sort !== 'featured'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
        <div class="venues-filters__head"><b>Filter</b><a href="<?= e($listUrl) ?>">Reset all</a></div>

        <div class="fgroup">
          <input type="search" class="partner-search" name="q" value="<?= e((string)($f['q'] ?? '')) ?>" placeholder="Search providers…" aria-label="Search providers">
        </div>
        <div class="fgroup">
          <div class="fgroup__t">Location</div>
          <?php foreach ($emirates as $em): $n = $emirateCounts[$em['slug']] ?? 0; ?>
            <label class="fopt">
              <input type="checkbox" name="emirate[]" value="<?= e($em['slug']) ?>"<?= in_array($em['slug'], $selEm, true) ? ' checked' : '' ?>>
              <?= e($em['name']) ?><span class="fopt__n"><?= e((string)$n) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="fgroup">
          <div class="fgroup__t">Provider type</div>
          <?php foreach (partner_type_buckets() as $k => $b): ?>
            <label class="fopt">
              <input type="checkbox" name="type[]" value="<?= e($k) ?>"<?= in_array($k, $selTy, true) ? ' checked' : '' ?>>
              <?= e($b[0]) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="fgroup">
          <label class="fopt">
            <input type="checkbox" name="featured" value="1"<?= !empty($f['featured']) ? ' checked' : '' ?>>
            Featured providers only
          </label>
        </div>

        <button type="submit" class="atv-btn venues-filters__apply">Apply filters</button>
      </form>
    </aside>

    <div class="venues-results">
      <?php if (!$partners): ?>
        <div class="venues-empty">
          <p class="h5 mb-2">No providers match your filters.</p>
          <?php if ($chips): ?><a class="atv-btn" href="<?= e($listUrl) ?>">Clear filters</a><?php endif; ?>
        </div>
      <?php else: ?>
        <div class="pgrid">
          <?php foreach ($partners as $partner): ?>
            <?php require __DIR__ . '/../partials/partner-card.php'; ?>
          <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
          <nav class="venue-pager" aria-label="Provider pages">
            <?php if ($page > 1): ?><a class="venue-pager__i" href="<?= e($listUrl . query_string(partner_filter_params($f, $sort, $page - 1))) ?>">&lsaquo;</a><?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a class="venue-pager__i<?= $i === $page ? ' is-active' : '' ?>" href="<?= e($listUrl . query_string(partner_filter_params($f, $sort, $i))) ?>"><?= e((string)$i) ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a class="venue-pager__i" href="<?= e($listUrl . query_string(partner_filter_params($f, $sort, $page + 1))) ?>">&rsaquo;</a><?php endif; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

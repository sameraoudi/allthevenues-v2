<?php
declare(strict_types=1);

/**
 * PU-A1 / #7b — My Venues list. The owned-venue table with a per-venue status chip
 * + request badge (Under review / Changes requested / Edit under review / Delist
 * requested / Delisted / Live). Expects $myVenues (partner-scoped safe rows) +
 * $pendingCrs (portal_owned_pending_crs map). All output escaped.
 */
/** @var array $myVenues @var array $pendingCrs */
$myVenues  = $myVenues ?? [];
$pendingCrs = $pendingCrs ?? [];
?>
<div class="pcontent__head">
  <div>
    <h1 class="pcontent__title">My Venues</h1>
    <p class="pcontent__sub"><?= e(number_format(count($myVenues))) ?> venue<?= count($myVenues) === 1 ? '' : 's' ?> on your account</p>
  </div>
  <div class="pcontent__actions">
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/claim')) ?>">Claim a venue</a>
    <a class="atv-btn" href="<?= e(base_url('portal/venues/new')) ?>"><?= icon('plus', 'atv-btn__ico') ?> Add a venue</a>
  </div>
</div>

<?php if (!$myVenues): ?>
  <div class="admin-panel admin-panel--center">
    <p class="text-muted mb-2">No venues on your account yet.</p>
    <a class="atv-btn" href="<?= e(base_url('portal/venues/new')) ?>">Add your first venue</a>
  </div>
<?php else: ?>
  <div class="admin-panel">
    <div class="lead-table-wrap">
      <table class="lead-table">
        <thead>
          <tr><th>Venue</th><th>Location</th><th>Status</th><th>Updated</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($myVenues as $v):
            $loc = trim(implode(', ', array_filter([
                trim((string)($v['area'] ?? '')),
                trim((string)($v['emirate_name'] ?? '')),
            ])));
            $st = portal_venue_state_badge($v, $pendingCrs);
          ?>
            <tr>
              <td data-label="Venue"><strong><?= e((string)$v['name']) ?></strong></td>
              <td data-label="Location"><?= $loc !== '' ? e($loc) : '<span class="text-muted">&mdash;</span>' ?></td>
              <td data-label="Status">
                <span class="pv-chip pv-chip--<?= e($st['chip_class']) ?>"><?= e($st['chip_label']) ?></span>
                <?php if ($st['badge_label'] !== ''): ?><span class="pv-badge pv-badge--<?= e($st['badge_class']) ?>"><?= e($st['badge_label']) ?></span><?php endif; ?>
              </td>
              <td data-label="Updated"><?= e(date('j M Y', strtotime((string)$v['updated_at']))) ?></td>
              <td data-label=""><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/venues/' . (int)$v['id'])) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

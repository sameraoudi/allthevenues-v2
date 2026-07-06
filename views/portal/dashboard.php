<?php
declare(strict_types=1);

/**
 * #3 U-P3 — "My venues" dashboard. Expects $myVenues (provider-scoped safe rows).
 * Read-only; no internal fields. All output escaped.
 */
/** @var array $myVenues */
$myVenues = $myVenues ?? [];
?>
<div class="portal-head">
  <h1>My venues</h1>
  <p class="portal-sub"><?= e(number_format(count($myVenues))) ?> venue<?= count($myVenues) === 1 ? '' : 's' ?> on your account</p>
</div>

<?php if (!$myVenues): ?>
  <div class="admin-panel admin-panel--center">
    <p class="text-muted mb-0">No venues assigned to your account yet.</p>
  </div>
<?php else: ?>
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
        ?>
          <tr>
            <td data-label="Venue"><strong><?= e((string)$v['name']) ?></strong></td>
            <td data-label="Location"><?= $loc !== '' ? e($loc) : '<span class="text-muted">—</span>' ?></td>
            <td data-label="Status"><span class="lead-status lead-status--<?= e((string)$v['status']) ?>"><?= e(venue_admin_status_label((string)$v['status'])) ?></span></td>
            <td data-label="Updated"><?= e(date('j M Y', strtotime((string)$v['updated_at']))) ?></td>
            <td data-label=""><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/venues/' . (int)$v['id'])) ?>">View</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

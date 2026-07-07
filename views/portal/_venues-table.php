<?php
declare(strict_types=1);

/**
 * Portal-updates — shared My-Venues table, used by BOTH /portal/venues and the
 * dashboard so the two stay identical. Each row: status chip + request badge, a
 * portal "View", and "View Live" to the public page (published venues only, new
 * tab). Renders inner content only — the caller wraps it in an .admin-panel.
 * Expects $myVenues (partner-scoped safe rows, incl. slug/status) + $pendingCrs.
 */
/** @var array $myVenues @var array $pendingCrs */
$myVenues   = $myVenues ?? [];
$pendingCrs = $pendingCrs ?? [];
?>
<?php if (!$myVenues): ?>
  <p class="text-muted mb-2">No venues on your account yet.</p>
  <a class="atv-btn" href="<?= e(base_url('portal/venues/new')) ?>">Add your first venue</a>
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
          $st     = portal_venue_state_badge($v, $pendingCrs);
          $slug   = trim((string)($v['slug'] ?? ''));
          $isLive = ((string)($v['status'] ?? '') === 'published') && $slug !== '';
        ?>
          <tr>
            <td data-label="Venue"><strong><?= e((string)$v['name']) ?></strong></td>
            <td data-label="Location"><?= $loc !== '' ? e($loc) : '<span class="text-muted">&mdash;</span>' ?></td>
            <td data-label="Status">
              <span class="pv-chip pv-chip--<?= e($st['chip_class']) ?>"><?= e($st['chip_label']) ?></span>
              <?php if ($st['badge_label'] !== ''): ?><span class="pv-badge pv-badge--<?= e($st['badge_class']) ?>"><?= e($st['badge_label']) ?></span><?php endif; ?>
            </td>
            <td data-label="Updated"><?= e(date('j M Y', strtotime((string)$v['updated_at']))) ?></td>
            <td data-label="" class="pv-row-actions">
              <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/venues/' . (int)$v['id'])) ?>">View</a>
              <?php if ($isLive): ?>
                <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('venues/' . $slug)) ?>" target="_blank" rel="noopener">View Live</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php
declare(strict_types=1);

/**
 * #3 U-P5b — Change-request review queue. Expects $rows, $filters. Reuses the
 * lead-table / lead-status / lead-mode admin patterns; risk + type badges only.
 */
/** @var array $rows @var array $filters */
$f = $filters;
$listUrl = base_url('admin/change-requests');
$sel = static fn(string $k, string $v): string => ((string)($f[$k] ?? '') === $v) ? ' selected' : '';
$statuses = ['pending' => 'Pending', 'needs_changes' => 'Changes requested', 'approved' => 'Approved',
             'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn', 'all' => 'All statuses'];
$types = ['edit' => 'Edit', 'new_venue' => 'New venue', 'image' => 'Image', 'claim' => 'Ownership claim'];
?>
<form class="lead-filters" method="get" action="<?= e($listUrl) ?>">
  <select name="status" aria-label="Status">
    <?php foreach ($statuses as $k => $label): ?>
      <option value="<?= e($k) ?>"<?= $sel('status', $k) ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="type" aria-label="Type">
    <option value="">Any type</option>
    <?php foreach ($types as $k => $label): ?>
      <option value="<?= e($k) ?>"<?= $sel('type', $k) ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="atv-btn atv-btn--sm">Filter</button>
</form>

<?php if (!$rows): ?>
  <div class="admin-panel admin-panel--center">
    <p class="text-muted mb-0">No change requests match your filters.</p>
  </div>
<?php else: ?>
  <div class="lead-table-wrap">
    <table class="lead-table">
      <thead>
        <tr><th>Venue</th><th>Provider</th><th>Type</th><th>Risk</th><th>Changes</th><th>Submitted</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r):
          [$stLabel, $stClass] = cr_status_meta((string)$r['status']);
          $isEdit = ($r['type'] === 'edit');
          $detail = base_url('admin/change-requests/' . (int)$r['id']);
        ?>
          <tr>
            <td data-label="Venue"><?= e((string)($r['venue_name'] ?? '—')) ?></td>
            <td data-label="Provider"><?= e((string)($r['provider_name'] ?? '—')) ?></td>
            <td data-label="Type"><span class="lead-mode lead-mode--<?= e((string)$r['type']) ?>"><?= e(cr_type_label((string)$r['type'])) ?></span></td>
            <td data-label="Risk"><?php if ($isEdit): ?><span class="cr-risk cr-risk--<?= e((string)$r['risk']) ?>"><?= e(cr_risk_label((string)$r['risk'])) ?></span><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
            <td data-label="Changes"><?= (int)$r['change_count'] ?></td>
            <td data-label="Submitted"><?= e(date('j M Y H:i', strtotime((string)$r['created_at']))) ?></td>
            <td data-label="Status"><span class="lead-status lead-status--<?= e($stClass) ?>"><?= e($stLabel) ?></span></td>
            <td data-label="">
              <?php if ($isEdit): ?>
                <a class="atv-btn atv-btn--sm" href="<?= e($detail) ?>">Review</a>
              <?php else: ?>
                <span class="text-muted" title="Reviewed in a later unit">Coming soon</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

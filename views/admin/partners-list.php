<?php
declare(strict_types=1);

/**
 * Admin provider (partner) list. Expects: $rows, $total, $page, $totalPages,
 * $filters, $emirates. All statuses (admin view). The Email column flags the
 * migration's NULL-email gaps so staff can fill them.
 */
/** @var array $rows @var int $total @var int $page @var int $totalPages */
/** @var array $filters @var array $emirates */
require_once __DIR__ . '/../../lib/partners.php';
require_once __DIR__ . '/../../lib/partner_admin.php';

$f = $filters;
$carry = array_filter([
    'q'       => $f['q']       ?? '',
    'status'  => $f['status']  ?? '',
    'emirate' => $f['emirate'] ?? '',
], static fn($v) => $v !== '' && $v !== null);
$sel = static fn(string $k, $v): string => ((string)($f[$k] ?? '') === (string)$v) ? ' selected' : '';
$listUrl = base_url('admin/partners');
?>
<div class="lead-toolbar">
  <div class="lead-toolbar__counts"><strong><?= e(number_format($total)) ?></strong> provider<?= $total === 1 ? '' : 's' ?></div>
</div>

<form class="lead-filters" method="get" action="<?= e($listUrl) ?>">
  <input type="search" name="q" value="<?= e((string)($f['q'] ?? '')) ?>" placeholder="Search by name" aria-label="Search providers">
  <select name="status" aria-label="Status">
    <option value="">Any status</option>
    <?php foreach (partner_admin_statuses() as $k => $s): ?>
      <option value="<?= e($k) ?>"<?= $sel('status', $k) ?>><?= e($s[0]) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="emirate" aria-label="Emirate">
    <option value="">Any emirate</option>
    <?php foreach ($emirates as $em): ?>
      <option value="<?= e((string)$em['id']) ?>"<?= $sel('emirate', $em['id']) ?>><?= e($em['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="atv-btn atv-btn--sm">Filter</button>
  <?php if ($carry): ?><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($listUrl) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="admin-panel admin-panel--center"><p class="text-muted mb-0">No providers match your filters.</p></div>
<?php else: ?>
  <div class="lead-table-wrap">
    <table class="lead-table">
      <thead>
        <tr><th>Name</th><th>Type</th><th>Emirate</th><th>Venues</th><th>Verified</th><th>Featured</th><th>Email</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $edit = base_url('admin/partners/edit') . query_string(['id' => (int)$r['id']]); ?>
          <tr>
            <td data-label="Name"><a class="lead-ref" href="<?= e($edit) ?>"><?= e($r['org_name']) ?></a></td>
            <td data-label="Type"><?= e(partner_type_label((string)($r['raw_org_type'] ?? ''))) ?></td>
            <td data-label="Emirate"><?= e($r['emirate_name'] ?: '—') ?></td>
            <td data-label="Venues"><?= e((string)(int)($r['venue_count'] ?? 0)) ?></td>
            <td data-label="Verified"><?= !empty($r['is_verified']) ? '✓' : '' ?></td>
            <td data-label="Featured"><?= !empty($r['is_featured']) ? '★' : '' ?></td>
            <td data-label="Email"><?php $em = trim((string)($r['email'] ?? '')); ?><?php if ($em !== ''): ?><?= e($em) ?><?php else: ?><span class="text-muted">— missing</span><?php endif; ?></td>
            <td data-label="Status"><span class="lead-status lead-status--<?= e($r['status']) ?>"><?= e(partner_admin_status_label((string)$r['status'])) ?></span></td>
            <td data-label=""><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($edit) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="lead-pagination" aria-label="Pages">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="lead-page<?= $i === $page ? ' is-active' : '' ?>" href="<?= e($listUrl . query_string($carry + ['page' => $i])) ?>"><?= e((string)$i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

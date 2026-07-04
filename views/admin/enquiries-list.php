<?php
declare(strict_types=1);

/**
 * Admin enquiries list. Expects: $rows, $total, $page, $totalPages, $filters,
 * $counts, $eventTypes, $emirates.
 */
/** @var array $rows @var int $total @var int $page @var int $totalPages */
/** @var array $filters @var array $counts @var array $eventTypes @var array $emirates */

$f = $filters;
$carry = array_filter([
    'status'     => $f['status']     ?? '',
    'event_type' => $f['event_type'] ?? '',
    'emirate'    => $f['emirate']    ?? '',
    'mode'       => $f['mode']       ?? '',
    'date_from'  => $f['date_from']  ?? '',
    'date_to'    => $f['date_to']    ?? '',
    'q'          => $f['q']          ?? '',
], static fn($v) => $v !== '' && $v !== null);
$hasFilters = (bool)$carry;
$sel = static fn(string $k, $v): string => ((string)($f[$k] ?? '') === (string)$v) ? ' selected' : '';
$listUrl = base_url('admin/enquiries');
$modes = ['venue' => 'Venue enquiry', 'assisted' => 'Assisted', 'partner' => 'Partner interest', 'partner_signup' => 'Partner signup', 'contact' => 'Contact', 'general' => 'General'];
?>
<div class="lead-toolbar">
  <div class="lead-toolbar__counts">
    <strong><?= e(number_format($total)) ?></strong> result<?= $total === 1 ? '' : 's' ?>
    · <span class="lead-status lead-status--new"><?= e(number_format($counts['new'])) ?> new</span>
    · <?= e(number_format($counts['total'])) ?> total
  </div>
  <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($listUrl . query_string($carry + ['export' => 'csv'])) ?>">Export CSV</a>
</div>

<form class="lead-filters" method="get" action="<?= e($listUrl) ?>">
  <input type="search" name="q" value="<?= e((string)($f['q'] ?? '')) ?>" placeholder="Search name, email, reference" aria-label="Search">
  <select name="status" aria-label="Status">
    <option value="">Any status</option>
    <?php foreach (enquiry_statuses() as $k => $s): ?>
      <option value="<?= e($k) ?>"<?= $sel('status', $k) ?>><?= e($s[0]) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="mode" aria-label="Mode">
    <option value="">Any mode</option>
    <?php foreach ($modes as $k => $label): ?>
      <option value="<?= e($k) ?>"<?= $sel('mode', $k) ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="event_type" aria-label="Event type">
    <option value="">Any event</option>
    <?php foreach ($eventTypes as $et): ?>
      <option value="<?= e((string)$et['id']) ?>"<?= $sel('event_type', $et['id']) ?>><?= e($et['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="emirate" aria-label="Emirate">
    <option value="">Any emirate</option>
    <?php foreach ($emirates as $em): ?>
      <option value="<?= e((string)$em['id']) ?>"<?= $sel('emirate', $em['id']) ?>><?= e($em['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="lead-filters__date">From <input type="date" name="date_from" value="<?= e((string)($f['date_from'] ?? '')) ?>"></label>
  <label class="lead-filters__date">To <input type="date" name="date_to" value="<?= e((string)($f['date_to'] ?? '')) ?>"></label>
  <button type="submit" class="atv-btn atv-btn--sm">Filter</button>
  <?php if ($hasFilters): ?><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($listUrl) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$rows): ?>
  <div class="admin-panel admin-panel--center">
    <p class="text-muted mb-0">No enquiries match your filters.</p>
  </div>
<?php else: ?>
  <div class="lead-table-wrap">
    <table class="lead-table">
      <thead>
        <tr>
          <th>Reference</th><th>Name</th><th>Contact</th><th>Event</th><th>Guests</th>
          <th>Date</th><th>Venue(s)</th><th>Mode</th><th>Status</th><th>Received</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            [$modeLabel, $modeClass] = enquiry_mode_badge((string)$r['mode'], (int)$r['venue_count']);
            $detail = base_url('admin/enquiries/' . (int)$r['id']);
            $guests = $r['guest_count'] ? (venue_guest_bands()[$r['guest_count']][0] ?? $r['guest_count']) : '—';
          ?>
          <tr>
            <td data-label="Reference"><a class="lead-ref" href="<?= e($detail) ?>"><?= e($r['reference']) ?></a></td>
            <td data-label="Name"><a href="<?= e($detail) ?>"><?= e($r['name'] ?: '—') ?></a></td>
            <td data-label="Contact">
              <div class="lead-contact"><?= e($r['email'] ?: '') ?></div>
              <?php if ($r['phone']): ?><div class="lead-contact lead-contact--sub"><?= e($r['phone']) ?></div><?php endif; ?>
            </td>
            <td data-label="Event"><?= e($r['event_type_name'] ?: '—') ?></td>
            <td data-label="Guests"><?= e($guests) ?></td>
            <td data-label="Date"><?= e($r['event_date'] ? date('j M Y', strtotime((string)$r['event_date'])) : '—') ?></td>
            <td data-label="Venue(s)"><?php
              if ($r['venue_names']) { echo e($r['venue_names']); }
              elseif (!empty($r['partner_name'])) { echo 'Provider: ' . e($r['partner_name']); }
              elseif (!empty($r['company'])) { echo e($r['company']); }
              else { echo '<span class="text-muted">—</span>'; }
            ?></td>
            <td data-label="Mode"><span class="lead-mode lead-mode--<?= e($modeClass) ?>"><?= e($modeLabel) ?></span></td>
            <td data-label="Status"><span class="lead-status lead-status--<?= e($r['status']) ?>"><?= e(enquiry_status_label((string)$r['status'])) ?></span></td>
            <td data-label="Received"><?= e(date('j M Y H:i', strtotime((string)$r['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
    <nav class="lead-pagination" aria-label="Pages">
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="lead-page<?= $i === $page ? ' is-active' : '' ?>"
           href="<?= e($listUrl . query_string($carry + ['page' => $i])) ?>"><?= e((string)$i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

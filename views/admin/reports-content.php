<?php
declare(strict_types=1);

/**
 * Admin reports view (#5) — ported from docs/atv-reports-preview.html.
 * Expects: $filters, $includeHistorical, $kpis, $liveHist, $overTime, $byStatus,
 * $byEventType, $byEmirate, $byVenue, $provTouch, $provFwd, $eventTypes,
 * $emirates, $providers. No inline styles/JS — bar sizes come from data-pct
 * (assets/js/app.js). All output escaped with e().
 */

$repUrl = base_url('admin/reports');

// Params to carry across the toggle + CSV links (only set filters).
$carry = array_filter([
    'date_from'  => $filters['date_from']  ?? '',
    'date_to'    => $filters['date_to']    ?? '',
    'status'     => $filters['status']     ?? '',
    'event_type' => $filters['event_type'] ?? '',
    'emirate'    => $filters['emirate']    ?? '',
    'provider'   => $filters['provider']   ?? '',
    'q'          => $filters['q']          ?? '',
], static fn($v) => $v !== '' && $v !== null && $v !== 0);
if ($includeHistorical) { $carry['historical'] = '1'; }

// A CSV/toggle link from $carry plus overrides.
$link = static function (array $over) use ($repUrl, $carry): string {
    $p = $carry;
    foreach ($over as $k => $v) {
        if ($v === null) { unset($p[$k]); } else { $p[$k] = $v; }
    }
    return e($repUrl . query_string($p));
};

// KPI derived values (NULL-safe).
$total     = (int)$kpis['total'];
$fwdPct    = $total > 0 ? round($kpis['forwarded'] / $total * 100, 1) : 0.0;
$wonConv   = $kpis['decided'] > 0 ? round($kpis['won'] / $kpis['decided'] * 100, 1) : 0.0;
$venuePct  = $total > 0 ? (int)round($kpis['with_venue'] / $total * 100) : 0;

// Bar helper: percentage of the section max (min 3 so a non-zero bar shows).
$pctOf = static fn(int $c, int $max): int => $max > 0 ? max(3, (int)round($c / $max * 100)) : 0;

$fmtRange = static function (array $f): string {
    $from = $f['date_from'] ?? '';
    $to   = $f['date_to']   ?? '';
    if ($from === '' && $to === '') { return 'all dates'; }
    $d = static fn(string $s): string => $s !== '' ? date('j M Y', strtotime($s)) : '…';
    return $d($from) . ' – ' . $d($to);
};

$statusLabels = enquiry_statuses();
?>
<!-- FILTER BAR — same filter set as the enquiry inbox -->
<form class="rep-filters" method="get" action="<?= e($repUrl) ?>">
  <?php if ($includeHistorical): ?><input type="hidden" name="historical" value="1"><?php endif; ?>
  <div class="rep-field"><label for="rf-from">From</label><input type="date" id="rf-from" name="date_from" value="<?= e((string)($filters['date_from'] ?? '')) ?>"></div>
  <div class="rep-field"><label for="rf-to">To</label><input type="date" id="rf-to" name="date_to" value="<?= e((string)($filters['date_to'] ?? '')) ?>"></div>
  <div class="rep-field">
    <label for="rf-status">Status</label>
    <select id="rf-status" name="status">
      <option value="">Any status</option>
      <?php foreach ($statusLabels as $k => $s): ?>
        <option value="<?= e($k) ?>"<?= (($filters['status'] ?? '') === $k) ? ' selected' : '' ?>><?= e($s[0]) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="rep-field">
    <label for="rf-event">Event type</label>
    <select id="rf-event" name="event_type">
      <option value="">Any event</option>
      <?php foreach ($eventTypes as $et): ?>
        <option value="<?= e((string)$et['id']) ?>"<?= ((int)($filters['event_type'] ?? 0) === (int)$et['id']) ? ' selected' : '' ?>><?= e($et['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="rep-field">
    <label for="rf-emirate">Emirate</label>
    <select id="rf-emirate" name="emirate">
      <option value="">Any emirate</option>
      <?php foreach ($emirates as $em): ?>
        <option value="<?= e((string)$em['id']) ?>"<?= ((int)($filters['emirate'] ?? 0) === (int)$em['id']) ? ' selected' : '' ?>><?= e($em['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="rep-field">
    <label for="rf-provider">Provider</label>
    <select id="rf-provider" name="provider">
      <option value="">Any provider</option>
      <?php foreach ($providers as $p): ?>
        <option value="<?= e((string)$p['id']) ?>"<?= ((int)($filters['provider'] ?? 0) === (int)$p['id']) ? ' selected' : '' ?>><?= e($p['org_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="rep-field rep-field--grow"><label for="rf-q">Keyword</label><input type="search" id="rf-q" name="q" value="<?= e((string)($filters['q'] ?? '')) ?>" placeholder="Name, email, reference"></div>
  <div class="rep-actions">
    <button type="submit" class="atv-btn atv-btn--sm">Apply</button>
    <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($repUrl) ?>">Clear</a>
  </div>
</form>

<!-- HISTORICAL TOGGLE — default live-only -->
<div class="rep-togglebar">
  <a class="rep-toggle" href="<?= $link(['historical' => $includeHistorical ? null : '1']) ?>">
    <span class="rep-switch<?= $includeHistorical ? ' is-on' : '' ?>"></span>
    <b><?= $includeHistorical ? 'Including historical' : 'Live enquiries only' ?></b>
    <span class="rep-note">
      <?php if ($includeHistorical): ?>
        — <?= e(number_format($liveHist['historical'])) ?> historical included
      <?php else: ?>
        — excludes <?= e(number_format($liveHist['historical'])) ?> historical
      <?php endif; ?>
    </span>
  </a>
  <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= $link(['export' => 'summary']) ?>"><?= icon('download') ?> Export all (CSV)</a>
</div>

<?php if (!$includeHistorical): ?>
  <div class="rep-banner">
    <?= icon('info-circle') ?>
    Showing <b>live enquiries</b> for <?= e($fmtRange($filters)) ?>. Toggle above to include historical records.
  </div>
<?php endif; ?>

<!-- KPI SUMMARY -->
<section class="rep-kpis">
  <div class="kpi"><span class="kpi__value"><?= e(number_format($kpis['total'])) ?></span><span class="kpi__label">Enquiries in range</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(number_format($kpis['new_count'])) ?></span><span class="kpi__label">New / unhandled</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(number_format($kpis['forwarded'])) ?></span><span class="kpi__label">Forwarded</span><span class="kpi__sub"><?= e((string)$fwdPct) ?>%</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(number_format($kpis['won'])) ?></span><span class="kpi__label">Won</span><span class="kpi__sub"><?= e((string)$wonConv) ?>% of decided</span></div>
  <div class="kpi"><span class="kpi__value"><?= e((string)$venuePct) ?>%</span><span class="kpi__label">With a venue linked</span></div>
  <div class="kpi"><span class="kpi__value"><?= e(number_format($liveHist['live'])) ?><span class="kpi__slash"> / <?= e(number_format($liveHist['historical'])) ?></span></span><span class="kpi__label">Live / historical</span></div>
</section>

<!-- ENQUIRIES OVER TIME -->
<?php $maxMonth = 0; foreach ($overTime as $r) { $maxMonth = max($maxMonth, (int)$r['c']); } ?>
<section class="panel">
  <div class="panel__head">
    <h2 class="panel__title">Enquiries over time</h2>
    <a class="panel__csv" href="<?= $link(['export' => 'over_time']) ?>"><?= icon('download') ?>CSV</a>
  </div>
  <?php if ($overTime): ?>
    <div class="spark">
      <?php foreach ($overTime as $r): ?>
        <div class="spark__col" data-pct="<?= e((string)$pctOf((int)$r['c'], $maxMonth)) ?>" title="<?= e(date('M Y', mktime(0, 0, 0, (int)$r['m'], 1, (int)$r['y']))) ?>: <?= e((string)$r['c']) ?>"></div>
      <?php endforeach; ?>
    </div>
    <div class="spark__labels">
      <?php foreach ($overTime as $r): ?>
        <span><?= e(date((int)$r['m'] === 1 ? 'M ’y' : 'M', mktime(0, 0, 0, (int)$r['m'], 1, (int)$r['y']))) ?></span>
      <?php endforeach; ?>
    </div>
    <p class="rep-note rep-note--block">Monthly counts (GROUP BY year/month, Gulf time). Include historical to run back to the earliest preserved legacy date.</p>
  <?php else: ?>
    <p class="rep-note rep-note--block">No enquiries in this range.</p>
  <?php endif; ?>
</section>

<div class="rep-grid">
  <!-- BY STATUS -->
  <?php $maxStatus = 0; foreach ($byStatus as $r) { $maxStatus = max($maxStatus, (int)$r['c']); } ?>
  <section class="panel">
    <div class="panel__head"><h2 class="panel__title">By status</h2><a class="panel__csv" href="<?= $link(['export' => 'status']) ?>"><?= icon('download') ?>CSV</a></div>
    <?php if ($byStatus): ?>
      <table class="rtable">
        <?php foreach ($byStatus as $r): $st = (string)$r['status']; $c = (int)$r['c']; ?>
          <tr>
            <td><span class="lead-status lead-status--<?= e($st) ?>"><?= e($statusLabels[$st][0] ?? ucfirst($st)) ?></span></td>
            <td class="barcell"><div class="bar-track"><div class="bar" data-pct="<?= e((string)$pctOf($c, $maxStatus)) ?>"></div></div></td>
            <td class="num"><?= e(number_format($c)) ?></td>
            <td class="pct"><?= e($total > 0 ? (string)round($c / $total * 100, 1) . '%' : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="rep-note">No data.</p><?php endif; ?>
  </section>

  <!-- BY EVENT TYPE -->
  <?php $maxEvent = 0; foreach ($byEventType as $r) { $maxEvent = max($maxEvent, (int)$r['c']); } ?>
  <section class="panel">
    <div class="panel__head"><h2 class="panel__title">By event type</h2><a class="panel__csv" href="<?= $link(['export' => 'event_type']) ?>"><?= icon('download') ?>CSV</a></div>
    <?php if ($byEventType): ?>
      <table class="rtable">
        <?php foreach ($byEventType as $r): $c = (int)$r['c']; ?>
          <tr>
            <td><?= e($r['name'] ?? '(unspecified)') ?></td>
            <td class="barcell"><div class="bar-track"><div class="bar" data-pct="<?= e((string)$pctOf($c, $maxEvent)) ?>"></div></div></td>
            <td class="num"><?= e(number_format($c)) ?></td>
            <td class="pct"><?= e($total > 0 ? (string)round($c / $total * 100, 1) . '%' : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="rep-note">No data.</p><?php endif; ?>
  </section>

  <!-- BY EMIRATE -->
  <?php $maxEm = 0; foreach ($byEmirate as $r) { $maxEm = max($maxEm, (int)$r['c']); } ?>
  <section class="panel">
    <div class="panel__head"><h2 class="panel__title">By emirate</h2><a class="panel__csv" href="<?= $link(['export' => 'emirate']) ?>"><?= icon('download') ?>CSV</a></div>
    <?php if ($byEmirate): ?>
      <table class="rtable">
        <?php foreach ($byEmirate as $r): $c = (int)$r['c']; ?>
          <tr>
            <td><?= e($r['name'] ?? '(unspecified)') ?></td>
            <td class="barcell"><div class="bar-track"><div class="bar" data-pct="<?= e((string)$pctOf($c, $maxEm)) ?>"></div></div></td>
            <td class="num"><?= e(number_format($c)) ?></td>
            <td class="pct"><?= e($total > 0 ? (string)round($c / $total * 100, 1) . '%' : '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="rep-note">No data.</p><?php endif; ?>
  </section>

  <!-- TOP VENUES -->
  <?php $maxVen = 0; foreach ($byVenue as $r) { $maxVen = max($maxVen, (int)$r['c']); } ?>
  <section class="panel">
    <div class="panel__head"><h2 class="panel__title">Top venues</h2><a class="panel__csv" href="<?= $link(['export' => 'venue']) ?>"><?= icon('download') ?>CSV</a></div>
    <p class="tabsub">By linked enquiries (via enquiry_venues).</p>
    <?php if ($byVenue): ?>
      <table class="rtable">
        <?php foreach ($byVenue as $r): $c = (int)$r['c']; ?>
          <tr>
            <td><?= e($r['name']) ?></td>
            <td class="barcell"><div class="bar-track"><div class="bar bar--blue" data-pct="<?= e((string)$pctOf($c, $maxVen)) ?>"></div></div></td>
            <td class="num"><?= e(number_format($c)) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="rep-note">No linked venues in this range.</p><?php endif; ?>
  </section>
</div>

<!-- BY PROVIDER — two lenses -->
<?php
$maxTouch = 0; foreach ($provTouch as $r) { $maxTouch = max($maxTouch, (int)$r['c']); }
$maxFwd   = 0; foreach ($provFwd   as $r) { $maxFwd   = max($maxFwd,   (int)$r['c']); }
?>
<section class="panel">
  <div class="panel__head"><h2 class="panel__title">By provider</h2><a class="panel__csv" href="<?= $link(['export' => 'provider']) ?>"><?= icon('download') ?>CSV</a></div>
  <div class="lenswrap">
    <div class="lens">
      <h3>Enquiries touching their venues</h3>
      <p>Based on the owning provider of each linked venue (venues.partner_id).</p>
      <?php if ($provTouch): ?>
        <table class="rtable">
          <?php foreach ($provTouch as $r): $c = (int)$r['c']; ?>
            <tr>
              <td><?= e($r['name']) ?></td>
              <td class="barcell"><div class="bar-track"><div class="bar" data-pct="<?= e((string)$pctOf($c, $maxTouch)) ?>"></div></div></td>
              <td class="num"><?= e(number_format($c)) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?><p class="rep-note">No data.</p><?php endif; ?>
    </div>
    <div class="lens">
      <h3>Leads actually forwarded</h3>
      <p>Based on lead_routing — what was routed to each provider.</p>
      <?php if ($provFwd): ?>
        <table class="rtable">
          <?php foreach ($provFwd as $r): $c = (int)$r['c']; ?>
            <tr>
              <td><?= e($r['name']) ?></td>
              <td class="barcell"><div class="bar-track"><div class="bar bar--sand" data-pct="<?= e((string)$pctOf($c, $maxFwd)) ?>"></div></div></td>
              <td class="num"><?= e(number_format($c)) ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php else: ?><p class="rep-note">No leads forwarded in this range.</p><?php endif; ?>
    </div>
  </div>
</section>

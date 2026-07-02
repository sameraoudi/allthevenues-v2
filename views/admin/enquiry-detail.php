<?php
declare(strict_types=1);

/** Enquiry detail. Expects $enq (with ['venues'],['routing']), $partners, ?$flash. */
/** @var array $enq @var array $partners */
$flash = $flash ?? null;
$id    = (int)$enq['id'];
[$modeLabel, $modeClass] = enquiry_mode_badge((string)$enq['mode'], count($enq['venues']));
$guests = $enq['guest_count'] ? (venue_guest_bands()[$enq['guest_count']][0] ?? $enq['guest_count']) : '—';
$io = $enq['indoor_outdoor'] ? (venue_indoor_outdoor_options()[$enq['indoor_outdoor']] ?? $enq['indoor_outdoor']) : '';
$flexOpts = enquiry_date_flexibility();

$row = static function (string $label, ?string $value): void {
    if (trim((string)$value) === '') return;
    echo '<div class="lead-detail__row"><span class="lead-detail__k">' . e($label)
        . '</span><span class="lead-detail__v">' . nl2br(e($value)) . '</span></div>';
};
?>
<p><a class="lead-back" href="<?= e(base_url('admin/enquiries')) ?>">&larr; Back to enquiries</a></p>

<?php if ($flash): ?>
  <div class="lead-flash lead-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>

<div class="lead-detail">
  <!-- Main -->
  <div class="lead-detail__main">
    <div class="admin-panel">
      <div class="lead-detail__head">
        <h2 class="admin-panel__title"><?= e($enq['reference']) ?></h2>
        <div>
          <span class="lead-mode lead-mode--<?= e($modeClass) ?>"><?= e($modeLabel) ?></span>
          <span class="lead-status lead-status--<?= e($enq['status']) ?>"><?= e(enquiry_status_label((string)$enq['status'])) ?></span>
        </div>
      </div>

      <h3 class="lead-detail__section">Contact</h3>
      <?php $row('Name', $enq['name']); $row('Email', $enq['email']); $row('Phone', $enq['phone']); $row('Company', $enq['company']); ?>

      <h3 class="lead-detail__section">Event</h3>
      <?php
        $row('Event type', $enq['event_type_name']);
        $row('Event date', $enq['event_date'] ? date('j M Y', strtotime((string)$enq['event_date'])) : '');
        $row('Date flexibility', $enq['date_flexibility'] ? ($flexOpts[$enq['date_flexibility']] ?? $enq['date_flexibility']) : '');
        $row('Location', $enq['emirate_name']);
        $row('Guests', $enq['guest_count'] ? $guests : '');
        $row('Budget', $enq['budget_range']);
      ?>

      <h3 class="lead-detail__section">Requirements</h3>
      <?php
        $row('Venue preference', $enq['venue_preference']);
        $row('Indoor / outdoor', $io);
        $row('Food & beverage', $enq['fb_requirements']);
        $row('AV & technical', $enq['av_requirements']);
        $row('Notes', $enq['notes']);
      ?>

      <h3 class="lead-detail__section">Meta</h3>
      <?php
        $row('Consent to share', $enq['consent_to_share'] ? 'Yes' : 'No');
        $row('Mode', $modeLabel);
        $row('Source', $enq['source_page']);
        $row('Received', date('j M Y H:i', strtotime((string)$enq['created_at'])));
        $row('Updated', date('j M Y H:i', strtotime((string)$enq['updated_at'])));
      ?>
    </div>

    <?php if ($enq['venues']): ?>
      <div class="admin-panel">
        <h3 class="lead-detail__section mt-0">Linked venues</h3>
        <ul class="lead-venues">
          <?php foreach ($enq['venues'] as $v): ?>
            <li>
              <a href="<?= e(base_url('venues/' . rawurlencode((string)$v['slug']))) ?>" target="_blank" rel="noopener"><?= e($v['name']) ?></a>
              <?php if ($v['emirate_name']): ?><span class="text-muted">· <?= e($v['emirate_name']) ?></span><?php endif; ?>
              <span class="lead-status lead-status--<?= e($v['status'] === 'published' ? 'won' : 'closed') ?>"><?= e(ucfirst((string)$v['status'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($enq['routing']): ?>
      <div class="admin-panel">
        <h3 class="lead-detail__section mt-0">Routing history</h3>
        <ul class="lead-routing">
          <?php foreach ($enq['routing'] as $r): ?>
            <li>
              Forwarded to <strong><?= e($r['partner_name'] ?: ('Partner #' . (int)$r['partner_id'])) ?></strong>
              (<?= e((string)$r['routed_to_email']) ?>) · <?= e(ucfirst((string)$r['status'])) ?>
              <?php if ($r['routed_at']): ?> · <?= e(date('j M Y H:i', strtotime((string)$r['routed_at']))) ?><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <!-- Actions -->
  <div class="lead-detail__side">
    <div class="admin-panel">
      <h3 class="lead-detail__section mt-0">Status</h3>
      <form method="post" action="<?= e(base_url('admin/enquiries/' . $id . '/status')) ?>">
        <?php csrf_field(); ?>
        <div class="atv-field">
          <select name="status" aria-label="New status">
            <?php foreach (enquiry_statuses() as $k => $s): ?>
              <option value="<?= e($k) ?>"<?= $k === $enq['status'] ? ' selected' : '' ?>><?= e($s[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="atv-btn atv-btn--sm">Update status</button>
      </form>
    </div>

    <div class="admin-panel">
      <h3 class="lead-detail__section mt-0">Add internal note</h3>
      <form method="post" action="<?= e(base_url('admin/enquiries/' . $id . '/note')) ?>">
        <?php csrf_field(); ?>
        <div class="atv-field">
          <textarea name="note" rows="3" maxlength="2000" placeholder="Visible to admins only" aria-label="Internal note"></textarea>
        </div>
        <button type="submit" class="atv-btn atv-btn--sm">Add note</button>
      </form>
    </div>

    <div class="admin-panel">
      <h3 class="lead-detail__section mt-0">Forward to partner</h3>
      <?php if (!$partners): ?>
        <p class="text-muted mb-0">No approved partners available.</p>
      <?php else: ?>
        <?php
          // Context-aware default: prefer the linked venue(s)' partner, but
          // only when that partner is approved (i.e. selectable in the list).
          $approvedEmail = [];
          foreach ($partners as $p) { $approvedEmail[(int)$p['id']] = (string)$p['email']; }

          $venuePartners = [];   // partner_id => ['name'=>, 'email'=>, 'venues'=>[]] (first-venue order)
          foreach ($enq['venues'] as $v) {
              $pid = (int)($v['partner_id'] ?? 0);
              if ($pid > 0 && isset($approvedEmail[$pid])) {
                  if (!isset($venuePartners[$pid])) {
                      $venuePartners[$pid] = ['name' => (string)$v['partner_name'], 'email' => $approvedEmail[$pid], 'venues' => []];
                  }
                  $venuePartners[$pid]['venues'][] = (string)$v['name'];
              }
          }
          $defaultPid   = $venuePartners ? (int)array_key_first($venuePartners) : 0;
          $defaultEmail = $defaultPid ? $venuePartners[$defaultPid]['email'] : '';
        ?>
        <?php if (count($venuePartners) > 1): ?>
          <p class="lead-hint mb-2">
            Linked venue partners —
            <?php $bits = [];
              foreach ($venuePartners as $vp) { $bits[] = e(implode(', ', $vp['venues'])) . ' → ' . e($vp['name']); }
              echo implode('; ', $bits); /* pre-escaped */ ?>.
            Defaulted to the first; choose another to forward there.
          </p>
        <?php elseif ($defaultPid): ?>
          <p class="lead-hint mb-2">Defaulted to this venue's partner — override if needed.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('admin/enquiries/' . $id . '/assign')) ?>">
          <?php csrf_field(); ?>
          <div class="atv-field">
            <label for="p-sel">Partner</label>
            <select id="p-sel" name="partner_id" required>
              <option value="">Choose a partner…</option>
              <?php foreach ($partners as $p): ?>
                <option value="<?= e((string)$p['id']) ?>" data-email="<?= e((string)$p['email']) ?>"<?= ((int)$p['id'] === $defaultPid) ? ' selected' : '' ?>><?= e($p['org_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="atv-field">
            <label for="p-email">Recipient email</label>
            <input type="email" id="p-email" name="routed_to_email" value="<?= e($defaultEmail) ?>" placeholder="Defaults to partner email" maxlength="255">
          </div>
          <label class="atv-check">
            <input type="checkbox" name="send_email" value="1" checked>
            <span>Email the partner the lead summary</span>
          </label>
          <div class="mt-2"><button type="submit" class="atv-btn atv-btn--sm">Forward lead</button></div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

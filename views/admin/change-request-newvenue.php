<?php
declare(strict_types=1);

/**
 * #3 U-P6b — Structured review of a NEW-VENUE submission. Expects $req (from
 * cr_admin_get), $nv (from cr_load_new_venue: venue + type/emirate names +
 * event_types + image_count), $comp (cr_newvenue_completeness), ?$flash,
 * ?$noteError, ?$note. Read-only venue view + completeness gate + decisions.
 * "Approve & publish" is disabled unless $comp['can_publish'] (server re-checks).
 * Rich-text is rendered raw ONLY because it is sanitized on write; all else e().
 */
/** @var array $req @var array $nv @var array $comp @var ?array $flash @var ?string $noteError @var string $note */
$flash     = $flash ?? null;
$noteError = $noteError ?? null;
$note      = $note ?? '';
$v         = $nv['venue'] ?? null;
$id        = (int)$req['id'];
$venueId   = (int)($req['venue_id'] ?? 0);
[$stLabel, $stClass] = cr_status_meta((string)$req['status']);
$submitter = trim((string)($req['submitter_name'] ?? '')) !== ''
    ? (string)$req['submitter_name'] : (string)($req['submitter_email'] ?? '—');

$missing = $comp['missing'] ?? [];
$isMiss  = static fn(string $label): bool => in_array($label, $missing, true);

// Read-only key/value row; $reqLabel (if given & missing) shows a "Required" flag.
$row = static function (string $label, ?string $value, string $reqLabel = '') use ($missing): void {
    $val = trim((string)$value);
    $flag = ($reqLabel !== '' && in_array($reqLabel, $missing, true))
        ? ' <span class="cr-req-flag">Required</span>' : '';
    echo '<div class="lead-detail__row"><span class="lead-detail__k">' . e($label) . '</span>'
        . '<span class="lead-detail__v">' . ($val !== '' ? e($val) : '<span class="text-muted">—</span>') . $flag . '</span></div>';
};
?>
<p><a class="lead-back" href="<?= e(base_url('admin/change-requests')) ?>">&larr; Back to change requests</a></p>

<?php if ($flash): ?>
  <div class="lead-flash lead-flash--<?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['msg']) ?></div>
<?php endif; ?>

<?php if ($v === null): ?>
  <div class="admin-panel"><p class="text-muted mb-0">The pending venue for this submission no longer exists.</p></div>
  <?php return; ?>
<?php endif; ?>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title"><?= e((string)$v['name']) ?></h2>
    <div>
      <span class="lead-mode lead-mode--new_venue">New venue</span>
      <span class="cr-risk cr-risk--high">High risk</span>
      <span class="lead-status lead-status--<?= e($stClass) ?>"><?= e($stLabel) ?></span>
    </div>
  </div>
  <?php
    $row('Provider', (string)($req['provider_name'] ?? '—'));
    echo '<div class="lead-detail__row"><span class="lead-detail__k">Submitted by</span><span class="lead-detail__v">'
        . e($submitter) . ' · ' . e(date('j M Y H:i', strtotime((string)$req['created_at']))) . '</span></div>';
  ?>
  <p class="lead-hint mt-2">A new venue is high-risk: review every field before publishing. It stays hidden until you approve &amp; publish.</p>
  <p><a href="<?= e(base_url('admin/venues/' . $venueId . '/edit')) ?>">Preview / edit pending venue &#8599;</a></p>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Completeness</h3>
  <div class="cr-comp">
    <div class="cr-meter" role="img" aria-label="<?= (int)$comp['score'] ?>% complete">
      <div class="cr-bar cr-bar--<?= $comp['can_publish'] ? 'ok' : 'warn' ?>" style="width: <?= (int)$comp['score'] ?>%"></div>
    </div>
    <span class="cr-comp__pct"><?= (int)$comp['score'] ?>%</span>
  </div>
  <ul class="cr-check">
    <?php foreach (($comp['checks'] ?? []) as [$label, $ok]): ?>
      <li class="cr-tick cr-tick--<?= $ok ? 'ok' : 'miss' ?>"><?= $ok ? '&#10003;' : '&#10007;' ?> <?= e($label) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$comp['can_publish']): ?>
    <p class="lead-flash lead-flash--error mb-0" role="status">Publishing blocked — missing: <?= e(implode(', ', $missing)) ?>.</p>
  <?php endif; ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Basics</h3>
  <?php
    $row('Name', (string)$v['name'], 'Name');
    $row('Slug', '/venues/' . (string)$v['slug'], 'Slug');
    $row('Website', (string)($v['website'] ?? ''));
    $row('Video URL', (string)($v['video_url'] ?? ''));
  ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Location</h3>
  <?php
    $row('Primary emirate', (string)($nv['emirate_name'] ?? ''), 'Primary emirate');
    $row('Area', (string)($v['area'] ?? ''), 'Area or address');
    $row('Address', (string)($v['address'] ?? ''));
  ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Classification &amp; capacity</h3>
  <?php
    $row('Venue type', (string)($nv['type_name'] ?? ''), 'Venue type');
    $row('Event types', $nv['event_types'] ? implode(', ', $nv['event_types']) : '', 'At least one event type');
    $ioOpts = venue_indoor_outdoor_options();
    $row('Indoor / outdoor', (string)($ioOpts[$v['indoor_outdoor']] ?? $v['indoor_outdoor'] ?? ''));
    $capMin = (int)($v['capacity_min'] ?? 0); $capMax = (int)($v['capacity_max'] ?? 0);
    $cap = $capMin && $capMax ? number_format($capMin) . '–' . number_format($capMax)
         : ($capMax ? 'Up to ' . number_format($capMax) : ($capMin ? 'From ' . number_format($capMin) : ''));
    $row('Capacity', $cap, 'Capacity');
    $row('Pricing level', (string)($v['pricing_level'] ?? ''));
    $spend = ($v['minimum_spend'] ?? null) && (float)$v['minimum_spend'] > 0 ? 'AED ' . number_format((float)$v['minimum_spend']) : '';
    $row('Minimum spend', $spend);
    $floor = ($v['floor_area'] ?? null) && (float)$v['floor_area'] > 0
        ? number_format((float)$v['floor_area']) . ' ' . ((string)($v['floor_area_unit'] ?? '') === 'sqm' ? 'm²' : (($v['floor_area_unit'] ?? '') === 'sqft' ? 'ft²' : (string)($v['floor_area_unit'] ?? ''))) : '';
    $row('Floor area', $floor);
  ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Description</h3>
  <?php $desc = trim((string)($v['description'] ?? '')); ?>
  <?php if ($desc !== ''): ?>
    <div class="venue-richtext"><?= $v['description'] /* sanitized on write */ ?></div>
  <?php else: ?>
    <p class="lead-detail__v"><span class="text-muted">—</span> <span class="cr-req-flag">Required</span></p>
  <?php endif; ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Photos</h3>
  <?php if ((int)$nv['image_count'] > 0): ?>
    <p class="lead-detail__v"><?= (int)$nv['image_count'] ?> image<?= (int)$nv['image_count'] === 1 ? '' : 's' ?> uploaded.</p>
  <?php else: ?>
    <p class="lead-flash lead-flash--error mb-0" role="status">Publishing blocked: at least one approved venue image with rights confirmation is required.</p>
  <?php endif; ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Decision</h3>
  <?php if ($noteError): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($noteError) ?></div><?php endif; ?>
  <form class="admin-form" method="post" action="<?= e(base_url('admin/change-requests/' . $id)) ?>"
        data-confirm="Submit this review decision? The provider will be notified by email.">
    <?php csrf_field(); ?>
    <div class="atv-field atv-field--full">
      <label for="review_note">Review note <span class="text-muted">(required to reject or request changes)</span></label>
      <textarea id="review_note" name="review_note" rows="3"><?= e($note) ?></textarea>
    </div>
    <div class="admin-form__actions">
      <button type="submit" class="atv-btn" name="decision" value="approve_publish"<?= $comp['can_publish'] ? '' : ' disabled' ?>>Approve &amp; publish</button>
      <button type="submit" class="atv-btn atv-btn--ghost" name="decision" value="approve_draft">Approve as draft</button>
      <button type="submit" class="atv-btn atv-btn--ghost" name="decision" value="request_changes">Request changes</button>
      <button type="submit" class="atv-btn atv-btn--danger" name="decision" value="reject">Reject</button>
    </div>
    <?php if (!$comp['can_publish']): ?>
      <p class="lead-hint mt-2">Approve &amp; publish is disabled until every required field is complete.</p>
    <?php endif; ?>
  </form>
</div>

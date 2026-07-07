<?php
declare(strict_types=1);

/**
 * #3 U-P3 — Read-only owned-venue detail (portal). Expects $venue (provider-safe
 * row, ownership already verified), $images, $layouts, $eventTags. NO edit
 * controls, NO internal contact/commission. Rich-text fields are rendered raw
 * ONLY because they are sanitized on write (same rule as the public/admin detail);
 * everything else is escaped with e().
 */
/** @var array $venue @var array $images @var array $layouts @var array $eventTags */

$ioLabel = !empty($venue['indoor_outdoor'])
    ? (venue_indoor_outdoor_options()[$venue['indoor_outdoor']] ?? $venue['indoor_outdoor'])
    : '';
$loc = trim(implode(', ', array_filter([
    trim((string)($venue['area'] ?? '')),
    trim((string)($venue['emirate_name'] ?? '')),
])));
$cap = '';
$cmin = ($venue['capacity_min'] ?? null) !== null ? (int)$venue['capacity_min'] : null;
$cmax = ($venue['capacity_max'] ?? null) !== null ? (int)$venue['capacity_max'] : null;
if ($cmin !== null && $cmax !== null) { $cap = number_format($cmin) . '–' . number_format($cmax); }
elseif ($cmax !== null)               { $cap = 'Up to ' . number_format($cmax); }
elseif ($cmin !== null)               { $cap = 'From ' . number_format($cmin); }
$floor = '';
if (($venue['floor_area'] ?? null) !== null && (float)$venue['floor_area'] > 0) {
    $u = (string)($venue['floor_area_unit'] ?? '');
    $floor = number_format((float)$venue['floor_area']) . ' ' . ($u === 'sqm' ? 'm²' : ($u === 'sqft' ? 'ft²' : $u));
}
$spend = ($venue['minimum_spend'] ?? null) && (float)$venue['minimum_spend'] > 0
    ? 'AED ' . number_format((float)$venue['minimum_spend']) : '';

$mapEmbed = trim((string)($venue['map_embed'] ?? ''));
$isGoogleMapEmbed = $mapEmbed !== ''
    && (bool)preg_match('#^<iframe[^>]*\ssrc="https://www\.google\.com/maps/#i', $mapEmbed);

$krow = static function (string $k, string $v): void {
    if (trim($v) === '') return;
    echo '<div class="lead-detail__row"><span class="lead-detail__k">' . e($k)
        . '</span><span class="lead-detail__v">' . e($v) . '</span></div>';
};
$richBlocks = [
    'description'   => 'Description',
    'highlights'    => 'What makes it special',
    'facilities'    => 'Facilities',
    'food_beverage' => 'Food & beverage',
    'av_support'    => 'AV & technical',
    'restrictions'  => 'Notes & restrictions',
    'packages'      => 'Packages',
    'special_offer' => 'Special offer',
];
$ltypeIcons = venue_layout_types();
?>
<p><a class="lead-back" href="<?= e(base_url('portal')) ?>">&larr; Back to my venues</a> &nbsp;·&nbsp; <a href="<?= e(base_url('portal/claim')) ?>">Claim a venue</a></p>

<?php if (!empty($flash)): ?><div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div><?php endif; ?>

<?php
$vid       = (int)$venue['id'];
$vStatus   = (string)$venue['status'];
// #15 — total uploaded photos (any review_status) drives the submit gate.
$phc = $pdo->prepare('SELECT COUNT(*) FROM venue_images WHERE venue_id = :vid');
$phc->execute([':vid' => $vid]);
$photoCount = (int)$phc->fetchColumn();

// PU-D1-fix-3 A — per-status breakdown for a friendly "N photos (…)" label.
$pbStmt = $pdo->prepare('SELECT review_status, COUNT(*) AS c FROM venue_images WHERE venue_id = :vid GROUP BY review_status');
$pbStmt->execute([':vid' => $vid]);
$photoBy = [];
foreach ($pbStmt->fetchAll() as $r) { $photoBy[(string)$r['review_status']] = (int)$r['c']; }
$photoCountLabel = static function (int $total, array $by): string {
    if ($total === 0) { return 'No photos yet'; }
    $label = $total . ' photo' . ($total === 1 ? '' : 's');
    $parts = [];
    if (!empty($by['approved']))       { $parts[] = $by['approved'] . ' approved'; }
    if (!empty($by['pending_review'])) { $parts[] = $by['pending_review'] . ' pending review'; }
    if ($parts) { $label .= ' (' . implode(' · ', $parts) . ')'; }
    return $label;
};

/* PU-D1-fix-2 #1C — one source of truth for the submission state, shared with the
   server (portal_active_newvenue_cr). Distinguish: under review · changes requested
   (incl. the pre-fix stuck 'pending'+needs_changes-CR rows) · draft. */
$partnerIdCtx = (int)($partnerId ?? 0);
$nvCr         = portal_active_newvenue_cr($pdo, $vid, $partnerIdCtx);
$nvCrStatus   = $nvCr !== null ? (string)$nvCr['status'] : '';
$nvCrNote     = $nvCr !== null ? trim((string)($nvCr['review_note'] ?? '')) : '';

$underReview  = ($vStatus === 'pending' && $nvCrStatus === 'pending');
$needsChanges = ($vStatus === 'needs_changes')
             || ($vStatus === 'pending' && $nvCrStatus === 'needs_changes');
$isDraftState = ($vStatus === 'draft');
$showFlow     = $underReview || $needsChanges || $isDraftState;

// Delist-1 — reversible take-down state (published request / pending / delisted).
$pendingDelist = portal_pending_delist_request($pdo, $vid, $partnerIdCtx);
$isDelisted    = ($vStatus === 'delisted');

// Readiness (shared by draft + re-submit), mirrors the server gate exactly.
$etCount   = count(portal_venue_event_type_ids($pdo, $vid));
$missing   = portal_venue_missing_required($venue, $etCount);
$detailsOk = ($missing === []);
$photosOk  = ($photoCount > 0);
$stepReady = $detailsOk && $photosOk;   // #3 — Step 3 reachable
$canSubmit = $stepReady;
$blockers  = [];
if (!$detailsOk) { $blockers[] = 'complete required details (' . implode(', ', $missing) . ')'; }
if (!$photosOk)  { $blockers[] = 'add at least one photo'; }
?>
<div class="lead-detail__head">
  <h1><?= e((string)$venue['name']) ?></h1>
  <div>
    <?php if ($isDraftState): ?>
      <span class="status-chip">Draft — not submitted</span>
    <?php elseif ($underReview): ?>
      <span class="lead-status lead-status--pending">Awaiting review</span>
    <?php elseif ($needsChanges): ?>
      <span class="lead-status lead-status--needs_changes">Changes requested</span>
    <?php else: ?>
      <span class="lead-status lead-status--<?= e($vStatus) ?>"><?= e(venue_admin_status_label($vStatus)) ?></span>
    <?php endif; ?>
    <a class="atv-btn atv-btn--sm" href="<?= e(base_url('portal/venues/' . $vid . '/edit')) ?>">Edit venue</a>
  </div>
</div>

<?php if ($showFlow): ?>
  <?php if ($underReview): /* #1C — submitted, awaiting first review: never a dead-end */ ?>
    <div class="admin-panel">
      <h2 class="admin-panel__title">Submitted &mdash; awaiting All The Venues review</h2>
      <p class="lead-hint mb-2">Your venue is with All The Venues for review. It stays private until it&rsquo;s approved, and you&rsquo;ll see the outcome here.</p>
      <p class="lead-hint mb-2">Need to change something first? Withdraw it back to a private draft, edit, then re-submit.</p>
      <form method="post" action="<?= e(base_url('portal/venues/' . $vid . '/withdraw')) ?>">
        <?php csrf_field(); ?>
        <button type="submit" class="atv-btn atv-btn--ghost atv-btn--sm" data-confirm="Withdraw this venue back to a draft? It will be removed from the review queue.">Withdraw to draft</button>
      </form>
    </div>
  <?php else: /* draft OR changes-requested → readiness checklist + (re)submit */
    $isResubmit = $needsChanges;
    $submitLabel = $isResubmit ? 'Re-submit for review' : 'Submit for review';
  ?>
    <div class="admin-panel">
      <h2 class="admin-panel__title"><?= $isResubmit ? 'Changes requested' : 'Finish adding this venue' ?></h2>
      <?php if ($isResubmit): ?>
        <p class="lead-hint mb-2">All The Venues reviewed your submission and asked for some changes. Update the venue below, then re-submit for another review.</p>
        <?php if ($nvCrNote !== ''): ?><div class="lead-detail__row"><span class="lead-detail__k">Reviewer note</span><span class="lead-detail__v"><?= nl2br(e($nvCrNote)) ?></span></div><?php endif; ?>
      <?php endif; ?>
      <?php $stepActive = 'submit'; $stepDetailsDone = $detailsOk; $stepPhotosDone = $photosOk; require __DIR__ . '/_stepper.php'; ?>
      <ul class="pd-prog">
        <li class="pd-prog__i <?= $detailsOk ? 'pd-prog__i--ok' : 'pd-prog__i--todo' ?>">
          <span class="pd-tick"><?= $detailsOk ? '&#10003;' : '1' ?></span>
          <?php if ($detailsOk): ?>
            Required details complete
          <?php else: ?>
            Required details missing: <?= e(implode(', ', $missing)) ?>
            <a href="<?= e(base_url('portal/venues/' . $vid . '/edit')) ?>">Edit details</a>
          <?php endif; ?>
        </li>
        <li class="pd-prog__i <?= $photosOk ? 'pd-prog__i--ok' : 'pd-prog__i--todo' ?>">
          <span class="pd-tick"><?= $photosOk ? '&#10003;' : '2' ?></span>
          <?= $photosOk ? e((string)$photoCount) . ' photo' . ($photoCount === 1 ? '' : 's') . ' uploaded' : 'Add at least one photo' ?>
          <a href="<?= e(base_url('portal/venues/' . $vid . '/images')) ?>">Add photos (Step 2)</a>
        </li>
        <li class="pd-prog__i <?= $canSubmit ? 'pd-prog__i--ok' : 'pd-prog__i--todo' ?>"><span class="pd-tick">3</span> <?= e($submitLabel) ?></li>
      </ul>
      <p class="lead-hint mb-2"><?= $isResubmit
          ? 'When you&rsquo;re ready, re-submit and All The Venues will review it again.'
          : 'Draft venues are private and never public. When you submit, All The Venues reviews the venue and its photos.' ?></p>
      <form method="post" action="<?= e(base_url('portal/venues/' . $vid . '/submit')) ?>">
        <?php csrf_field(); ?>
        <button type="submit" class="atv-btn"<?= $canSubmit ? '' : ' disabled title="Before submitting: ' . e(implode('; ', $blockers)) . '"' ?>><?= e($submitLabel) ?></button>
      </form>
      <?php if (!$canSubmit): ?><p class="lead-hint mt-2">Before you can submit: <?= e(implode('; ', $blockers)) ?>.</p><?php endif; ?>
    </div>

    <?php /* PU-D1-fix Part F — delete is offered ONLY for a true draft (never
             pending/needs_changes/published/archived). */ ?>
    <?php if ($isDraftState): ?>
      <div class="admin-panel">
        <h2 class="admin-panel__title">Delete this draft</h2>
        <p class="lead-hint mb-2">This draft hasn&rsquo;t been submitted. Deleting it removes it and any photos you&rsquo;ve uploaded — this can&rsquo;t be undone.</p>
        <form method="post" action="<?= e(base_url('portal/venues/' . $vid . '/delete')) ?>" data-confirm="Delete this draft venue? This can't be undone.">
          <?php csrf_field(); ?>
          <button type="submit" class="atv-btn atv-btn--warn atv-btn--sm">Delete draft</button>
        </form>
      </div>
    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>

<?php /* Delist-1 — delist states: delisted (re-list) · pending request (withdraw) · published (request). */ ?>
<?php if ($isDelisted): ?>
  <div class="admin-panel">
    <div class="lead-detail__head">
      <h2 class="admin-panel__title">This venue is delisted</h2>
      <span class="lead-status lead-status--delisted">Delisted</span>
    </div>
    <?php
      $dOn     = trim((string)($venue['delisted_at'] ?? ''));
      $dReason = (string)($venue['delist_reason'] ?? '');
      $dLabel  = portal_delist_reasons()[$dReason] ?? '';
    ?>
    <?php if ($dOn !== '' && strtotime($dOn) !== false): ?><div class="lead-detail__row"><span class="lead-detail__k">Delisted on</span><span class="lead-detail__v"><?= e(date('j M Y', strtotime($dOn))) ?> &middot; by All The Venues</span></div><?php endif; ?>
    <?php if ($dLabel !== ''): ?><div class="lead-detail__row"><span class="lead-detail__k">Reason</span><span class="lead-detail__v"><?= e($dLabel) ?></span></div><?php endif; ?>
    <p class="lead-hint mb-2">This venue is hidden from the public site. Re-listing restores it immediately (it was already reviewed) &mdash; our team is notified.</p>
    <form method="post" action="<?= e(base_url('portal/venues/' . $vid . '/relist')) ?>">
      <?php csrf_field(); ?>
      <button type="submit" class="atv-btn atv-btn--ok" data-confirm="Re-list this venue? It goes live on the public site again straight away.">Re-list this venue</button>
    </form>
  </div>
<?php elseif ($pendingDelist !== null): ?>
  <div class="admin-panel">
    <div class="lead-detail__head">
      <h2 class="admin-panel__title">Delisting requested &mdash; pending review</h2>
      <span class="lead-status lead-status--pending">Pending</span>
    </div>
    <p class="lead-hint mb-2">Your delisting request is with All The Venues. The venue stays live until it&rsquo;s approved.</p>
    <form method="post" action="<?= e(base_url('portal/venues/' . $vid . '/delist/withdraw')) ?>">
      <?php csrf_field(); ?>
      <input type="hidden" name="request_id" value="<?= (int)$pendingDelist['id'] ?>">
      <button type="submit" class="atv-btn atv-btn--ghost atv-btn--sm">Withdraw request</button>
    </form>
  </div>
<?php elseif ($vStatus === 'published'): ?>
  <div class="admin-panel">
    <div class="lead-detail__head">
      <h2 class="admin-panel__title">Delist this venue</h2>
    </div>
    <p class="lead-hint mb-2">Temporarily or permanently hide this venue from the public site. It&rsquo;s reversible &mdash; you can re-list it yourself anytime.</p>
    <a class="atv-btn atv-btn--ghost atv-btn--sm" href="<?= e(base_url('portal/venues/' . $vid . '/delist')) ?>">Request delisting</a>
  </div>
<?php endif; ?>

<?php
/* #3 U-P5a — pending change request for managed (name/slug/type/emirate) fields. */
if (!empty($pending)):
    $vcChanges = json_decode((string)($pending['proposed_changes_json'] ?? ''), true);
    if (!is_array($vcChanges)) { $vcChanges = []; }
    // Resolve type/emirate ids to names for readable old → new lines.
    $vcTypes = $vcEmirates = [];
    foreach (venue_types_all($pdo) as $t) { $vcTypes[(int)$t['id']] = (string)$t['name']; }
    foreach (venue_emirates($pdo) as $em) { $vcEmirates[(int)$em['id']] = (string)$em['name']; }
    $vcLabels = ['name' => 'Name', 'slug' => 'Slug', 'venue_type_id' => 'Venue type', 'emirate_id' => 'Primary emirate'];
    $vcShow = static function (string $field, $val) use ($vcTypes, $vcEmirates): string {
        if ($val === null || $val === '') return '—';
        if ($field === 'venue_type_id') return $vcTypes[(int)$val] ?? ('#' . (int)$val);
        if ($field === 'emirate_id')    return $vcEmirates[(int)$val] ?? ('#' . (int)$val);
        if ($field === 'slug')          return '/venues/' . (string)$val;
        return (string)$val;
    };
?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Change request pending review</h2>
    <p class="lead-hint mb-2">These proposed changes are with All The Venues for review. Your venue stays unchanged until they are approved.</p>
    <?php foreach ($vcChanges as $field => $pair): if (!isset($vcLabels[$field])) continue; ?>
      <div class="lead-detail__row">
        <span class="lead-detail__k"><?= e($vcLabels[$field]) ?></span>
        <span class="lead-detail__v"><?= e($vcShow($field, $pair['old'] ?? null)) ?> &rarr; <?= e($vcShow($field, $pair['new'] ?? null)) ?></span>
      </div>
    <?php endforeach; ?>
    <?php /* PU-D2 (#17) — proposed event-type set (published-venue change). */
      if (isset($vcChanges['_event_type_ids']) && is_array($vcChanges['_event_type_ids'])):
        $etNames = [];
        foreach (venue_event_types($pdo) as $et) { $etNames[(int)$et['id']] = (string)$et['name']; }
        $propNames = [];
        foreach ($vcChanges['_event_type_ids'] as $eid) { $eid = (int)$eid; if ($eid > 0) { $propNames[] = $etNames[$eid] ?? ('#' . $eid); } }
    ?>
      <div class="lead-detail__row">
        <span class="lead-detail__k">Event types</span>
        <span class="lead-detail__v"><?= $propNames ? e(implode(', ', $propNames)) : 'None' ?> <span class="lead-hint">(proposed — current tags stay live until approved)</span></span>
      </div>
    <?php endif; ?>
    <form class="mt-2" method="post" action="<?= e(base_url('portal/venues/' . $vid . '/request/withdraw')) ?>">
      <?php csrf_field(); ?>
      <input type="hidden" name="request_id" value="<?= (int)$pending['id'] ?>">
      <button type="submit" class="atv-btn atv-btn--ghost atv-btn--sm">Withdraw request</button>
    </form>
  </div>
<?php else:
    // #3 U-P5b — reflect the latest decision when there is no pending request.
    // ($partnerId is in scope from the portal dispatch venues block.)
    $lr       = portal_latest_request($pdo, $vid, (int)($partnerId ?? 0));
    $lrStatus = $lr['status'] ?? '';
    $lrNote   = trim((string)($lr['review_note'] ?? ''));

    // #3 U-P6b / PU-D1-fix-2 — new_venue decision reuses the shared CR lookup
    // ($nvCrStatus/$nvCrNote from the top). 'needs_changes' (incl. the stuck
    // pending rows) is handled by the submission flow above, so only the
    // 'declined' outcome is surfaced here.
    $nvStatus = $nvCrStatus;
    $nvNote   = $nvCrNote;
?>
  <?php if ($nvStatus === 'rejected'): ?>
    <div class="admin-panel">
      <h2 class="admin-panel__title">Submission declined</h2>
      <p class="lead-hint mb-2">All The Venues reviewed your venue submission and were unable to accept it.</p>
      <?php if ($nvNote !== ''): ?><div class="lead-detail__row"><span class="lead-detail__k">Reviewer note</span><span class="lead-detail__v"><?= nl2br(e($nvNote)) ?></span></div><?php endif; ?>
    </div>
  <?php elseif ($lrStatus === 'needs_changes'): ?>
    <div class="admin-panel">
      <h2 class="admin-panel__title">Changes requested</h2>
      <p class="lead-hint mb-2">All The Venues reviewed your last request and asked for some changes before it can be applied.</p>
      <?php if ($lrNote !== ''): ?><div class="lead-detail__row"><span class="lead-detail__k">Reviewer note</span><span class="lead-detail__v"><?= nl2br(e($lrNote)) ?></span></div><?php endif; ?>
      <p class="mt-2"><a class="atv-btn atv-btn--sm" href="<?= e(base_url('portal/venues/' . $vid . '/request')) ?>">Revise &amp; resubmit</a></p>
    </div>
  <?php elseif ($lrStatus === 'rejected'): ?>
    <div class="admin-panel">
      <h2 class="admin-panel__title">Request declined</h2>
      <p class="lead-hint mb-2">All The Venues reviewed your last request and were unable to apply it.</p>
      <?php if ($lrNote !== ''): ?><div class="lead-detail__row"><span class="lead-detail__k">Reviewer note</span><span class="lead-detail__v"><?= nl2br(e($lrNote)) ?></span></div><?php endif; ?>
      <p class="mt-2"><a href="<?= e(base_url('portal/venues/' . $vid . '/request')) ?>">Request a change to managed fields (name, classification, location…)</a></p>
    </div>
  <?php else: ?>
    <p><a href="<?= e(base_url('portal/venues/' . $vid . '/request')) ?>">Request a change to managed fields (name, classification, location…)</a></p>
  <?php endif; ?>
<?php endif; ?>

<div class="admin-panel">
  <h2 class="admin-panel__title">Key information</h2>
  <?php
    $krow('Venue type', (string)($venue['venue_type_name'] ?? ''));
    $krow('Location', $loc);
    $krow('Address', (string)($venue['address'] ?? ''));
    $krow('Indoor / outdoor', (string)$ioLabel);
    $krow('Capacity', $cap);
    $krow('Floor area', $floor);
    $krow('Pricing level', (string)($venue['pricing_level'] ?? ''));
    $krow('Minimum spend', $spend);
  ?>
  <?php if (trim((string)($venue['website'] ?? '')) !== ''): ?>
    <div class="lead-detail__row"><span class="lead-detail__k">Website</span><span class="lead-detail__v"><a href="<?= e((string)$venue['website']) ?>" target="_blank" rel="noopener nofollow"><?= e((string)$venue['website']) ?></a></span></div>
  <?php endif; ?>
</div>

<?php if ($eventTags): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Event types</h2>
    <div class="atv-card__chips">
      <?php foreach ($eventTags as $t): ?><span class="atv-chip"><?= e($t) ?></span><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title">Images</h2>
    <a class="atv-btn atv-btn--sm" href="<?= e(base_url('portal/venues/' . (int)$venue['id'] . '/images')) ?>">Manage photos</a>
  </div>
  <p class="lead-hint mb-2"><?= e($photoCountLabel($photoCount, $photoBy)) ?></p>
  <?php if ($images): ?>
    <div class="portal-thumbs">
      <?php foreach ($images as $img): ?>
        <img class="portal-thumb" src="<?= e(venue_img_src($img['file_path'] ?? null)) ?>" alt="<?= e((string)($img['alt_text'] ?? '')) ?>" loading="lazy">
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php if ($layouts): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Layouts &amp; capacity</h2>
    <table class="vd-layouts">
      <thead><tr><th>Layout</th><th class="ta-r">Capacity</th></tr></thead>
      <tbody>
        <?php foreach ($layouts as $l): ?>
          <tr>
            <td class="vd-layout-name"><?php $lk = $ltypeIcons[$l['layout_type']] ?? ''; if ($lk !== '') echo icon($lk, 'vd-layout-ico'); ?> <?= e((string)$l['layout_type']) ?></td>
            <td class="ta-r"><?= e(number_format((int)$l['capacity'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php foreach ($richBlocks as $field => $label):
    $val = trim((string)($venue[$field] ?? ''));
    if ($val === '') continue; ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title"><?= e($label) ?></h2>
    <div class="venue-richtext"><?= $venue[$field] /* sanitized on write */ ?></div>
  </div>
<?php endforeach; ?>

<?php if ($isGoogleMapEmbed): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Map</h2>
    <div class="vd-map"><?= $mapEmbed /* validated Google Maps iframe */ ?></div>
  </div>
<?php endif; ?>

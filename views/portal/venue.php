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
<p><a class="lead-back" href="<?= e(base_url('portal')) ?>">&larr; Back to my venues</a></p>

<?php if (!empty($flash)): ?><div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div><?php endif; ?>

<div class="lead-detail__head">
  <h1><?= e((string)$venue['name']) ?></h1>
  <div>
    <span class="lead-status lead-status--<?= e((string)$venue['status']) ?>"><?= e(venue_admin_status_label((string)$venue['status'])) ?></span>
    <a class="atv-btn atv-btn--sm" href="<?= e(base_url('portal/venues/' . (int)$venue['id'] . '/edit')) ?>">Edit venue</a>
  </div>
</div>

<?php
/* #3 U-P5a — pending change request for managed (name/slug/type/emirate) fields. */
$vid = (int)$venue['id'];
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

    // #3 U-P6b — a new_venue submission carries its own decision (owner-scoped).
    // (lib/portal.php is out of this unit's scope, so the lookup is inline here.)
    $nvStmt = $pdo->prepare(
        "SELECT status, review_note FROM venue_change_requests
         WHERE venue_id = :vid AND partner_id = :pid AND type = 'new_venue'
         ORDER BY id DESC LIMIT 1"
    );
    $nvStmt->execute([':vid' => $vid, ':pid' => (int)($partnerId ?? 0)]);
    $nvReq    = $nvStmt->fetch() ?: null;
    $nvStatus = $nvReq['status'] ?? '';
    $nvNote   = trim((string)($nvReq['review_note'] ?? ''));
?>
  <?php if ($nvStatus === 'needs_changes'): ?>
    <div class="admin-panel">
      <h2 class="admin-panel__title">Changes requested</h2>
      <p class="lead-hint mb-2">All The Venues reviewed your venue submission and asked for some changes. Update your venue below and we will re-review it.</p>
      <?php if ($nvNote !== ''): ?><div class="lead-detail__row"><span class="lead-detail__k">Reviewer note</span><span class="lead-detail__v"><?= nl2br(e($nvNote)) ?></span></div><?php endif; ?>
    </div>
  <?php elseif ($nvStatus === 'rejected'): ?>
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

<?php if ($images): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Images</h2>
    <div class="portal-thumbs">
      <?php foreach ($images as $img): ?>
        <img class="portal-thumb" src="<?= e(venue_img_src($img['file_path'] ?? null)) ?>" alt="<?= e((string)($img['alt_text'] ?? '')) ?>" loading="lazy">
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

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

<?php
declare(strict_types=1);

/**
 * #3 U-P6a — Provider portal: new-venue submission form. Reuses the portal
 * edit-form field markup/classes. Identity fields (name/type/emirate) are
 * editable here because the whole venue is born pending admin review. Expects
 * $old, $errors, $pdo. No status/featured/verified/contact fields. Escapes output.
 */
/** @var array $old @var array $errors @var array $layoutErrors @var PDO $pdo */
$layoutErrors = $layoutErrors ?? [];
$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$ta  = static fn(string $k): string => e((string)($old[$k] ?? ''));
$lval = static fn(string $type): string => e((string)($old['layout'][$type] ?? ''));   // #18 layout prefill
// #13 — 'best_for' removed from partner forms (event types replace it).
$richFields = [
    'description'   => 'Description',
    'highlights'    => 'What makes it special (highlights)',
    'facilities'    => 'Facilities',
    'food_beverage' => 'Food & beverage',
    'av_support'    => 'AV & technical',
    'restrictions'  => 'Notes & restrictions',
    'packages'      => 'Packages',
    'special_offer' => 'Special offer',
];
?>
<p><a class="lead-back" href="<?= e(base_url('portal')) ?>">&larr; Back to my venues</a></p>

<div class="lead-detail__head">
  <h1>Add a venue</h1>
</div>

<?php $stepActive = 'details'; $stepDetailsDone = false; $stepPhotosDone = false; require __DIR__ . '/_stepper.php'; ?>

<p class="lead-hint mb-2">Step 1 of 3. Saving keeps this as a <strong>draft</strong> (private, not submitted) — you’ll add photos next, then submit for review.</p>

<?php if (!empty($errors)): /* #14 — top error banner on any validation failure */ ?>
  <div class="lead-flash lead-flash--error" role="alert"><strong>Submission could not be saved.</strong> Please fix the highlighted fields and try again.<?php if (!empty($errors['_form'])): ?> <?= e($errors['_form']) ?><?php endif; ?></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('portal/venues/new')) ?>" novalidate data-layout-form>
  <?php csrf_field(); ?>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Venue basics</h2>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-name">Name <span class="req">*</span></label>
        <input type="text" id="f-name" name="name" value="<?= $v('name') ?>" maxlength="255" class="<?= $has('name') ? 'is-invalid' : '' ?>">
        <?php $err('name'); ?>
      </div>
      <div class="atv-field">
        <label for="f-type">Venue type <span class="req">*</span></label>
        <select id="f-type" name="venue_type_id" class="<?= $has('venue_type_id') ? 'is-invalid' : '' ?>">
          <option value="">—</option>
          <?php foreach (venue_types_all($pdo) as $t): ?><option value="<?= (int)$t['id'] ?>"<?= $sel('venue_type_id', (int)$t['id']) ?>><?= e((string)$t['name']) ?></option><?php endforeach; ?>
        </select>
        <?php $err('venue_type_id'); ?>
      </div>
      <div class="atv-field">
        <label for="f-emirate">Primary emirate <span class="req">*</span></label>
        <select id="f-emirate" name="emirate_id" class="<?= $has('emirate_id') ? 'is-invalid' : '' ?>">
          <option value="">—</option>
          <?php foreach (venue_emirates($pdo) as $em): ?><option value="<?= (int)$em['id'] ?>"<?= $sel('emirate_id', (int)$em['id']) ?>><?= e((string)$em['name']) ?></option><?php endforeach; ?>
        </select>
        <?php $err('emirate_id'); ?>
      </div>
      <div class="atv-field"><label for="f-area">Area <span class="req">*</span> <span class="lead-hint">(area or address)</span></label><input type="text" id="f-area" name="area" value="<?= $v('area') ?>" maxlength="150" class="<?= $has('area') ? 'is-invalid' : '' ?>"><?php $err('area'); ?></div>
      <div class="atv-field atv-field--full"><label for="f-address">Address</label><input type="text" id="f-address" name="address" value="<?= $v('address') ?>" maxlength="255"></div>
      <div class="atv-field">
        <label for="f-io">Indoor / outdoor</label>
        <select id="f-io" name="indoor_outdoor">
          <?php foreach (venue_indoor_outdoor_options() as $k => $label): ?><option value="<?= e($k) ?>"<?= $sel('indoor_outdoor', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field"><label for="f-website">Website</label><input type="text" id="f-website" name="website" value="<?= $v('website') ?>" maxlength="255"></div>
      <div class="atv-field"><label for="f-video">Video URL</label><input type="text" id="f-video" name="video_url" value="<?= $v('video_url') ?>" maxlength="255"></div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Capacity, size &amp; pricing</h2>
    <div class="admin-form__grid">
      <div class="atv-field"><label for="f-cmin">Minimum guests</label><input type="number" id="f-cmin" name="capacity_min" value="<?= $v('capacity_min') ?>" min="0"></div>
      <div class="atv-field"><label for="f-cmax">Maximum capacity <span class="req">*</span></label><input type="number" id="f-cmax" name="capacity_max" value="<?= $v('capacity_max') ?>" min="0" data-layout-capmax class="<?= $has('capacity_max') ? 'is-invalid' : '' ?>"><?php $err('capacity_max'); ?></div>
      <div class="atv-field"><label for="f-spend">Minimum spend (AED)</label><input type="number" id="f-spend" name="minimum_spend" value="<?= $v('minimum_spend') ?>" min="0" step="0.01"></div>
      <div class="atv-field">
        <label for="f-price">Pricing level</label>
        <select id="f-price" name="pricing_level">
          <option value="">—</option>
          <?php foreach (venue_pricing_levels() as $pl): ?><option value="<?= e($pl) ?>"<?= $sel('pricing_level', $pl) ?>><?= e($pl) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field"><label for="f-floor">Floor area</label><input type="number" id="f-floor" name="floor_area" value="<?= $v('floor_area') ?>" min="0" step="0.01"></div>
      <div class="atv-field">
        <label for="f-floor-unit">Floor area unit</label>
        <select id="f-floor-unit" name="floor_area_unit">
          <option value="">—</option>
          <option value="sqm"<?= $sel('floor_area_unit', 'sqm') ?>>m² (sqm)</option>
          <option value="sqft"<?= $sel('floor_area_unit', 'sqft') ?>>ft² (sqft)</option>
        </select>
      </div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Layouts &amp; capacity</h2>
    <p class="lead-hint mb-2">Capacity for each layout you offer. None can exceed the venue maximum. Leave blank for layouts this venue doesn’t offer.</p>
    <div class="admin-form__grid layout-grid">
      <?php foreach (venue_layout_types() as $ltype => $liconKey): $lerr = isset($layoutErrors[$ltype]); ?>
        <div class="atv-field">
          <label class="layout-label" for="f-layout-<?= e($ltype) ?>"><?= icon($liconKey, 'layout-ico') ?> <?= e($ltype) ?></label>
          <input type="number" id="f-layout-<?= e($ltype) ?>" name="layout[<?= e($ltype) ?>]" value="<?= $lval($ltype) ?>" min="0" class="<?= $lerr ? 'is-invalid' : '' ?>" data-layout-cap>
          <?php if ($lerr): ?><p class="atv-enq-err" role="alert"><?= e($layoutErrors[$ltype]) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Map</h2>
    <div class="atv-field atv-field--full">
      <label for="f-map">Map embed</label>
      <textarea id="f-map" name="map_embed" rows="3" class="<?= $has('map_embed') ? 'is-invalid' : '' ?>"><?= e((string)($old['map_embed'] ?? '')) ?></textarea>
      <p class="lead-hint">Paste the Google Maps embed &lt;iframe&gt;. Only a valid Google Maps iframe is shown publicly.</p>
      <?php $err('map_embed'); ?>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Description &amp; details</h2>
    <p class="lead-hint mb-2">Rich text — only p, br, strong, em, lists and links are kept; everything else is stripped on save.</p>
    <?php foreach ($richFields as $field => $label): $reqd = ($field === 'description'); ?>
      <div class="atv-field">
        <label for="f-<?= e($field) ?>"><?= e($label) ?><?php if ($reqd): ?> <span class="req">*</span><?php endif; ?></label>
        <textarea id="f-<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= $field === 'description' ? 5 : 3 ?>" class="<?= $has($field) ? 'is-invalid' : '' ?>"><?= $ta($field) ?></textarea>
        <?php $err($field); ?>
      </div>
    <?php endforeach; ?>
  </div>

  <?php
    /* #3 U-P9b — event types (new venue is 'pending' → editable). */
    $etChecked   = array_map('intval', (array)($old['event_types'] ?? []));
    $etPublished = false;
    $etVid       = 0;
    include __DIR__ . '/event-types-field.php';
    $err('event_types');
  ?>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn" name="action" value="continue" data-layout-submit>Save &amp; continue to photos &rarr;</button>
    <button type="submit" class="atv-btn atv-btn--ghost" name="action" value="draft">Save as draft</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal')) ?>">Cancel</a>
  </div>
  <p class="lead-hint mt-2">Fields marked <span class="req">*</span> are required to continue to photos. <strong>Save as draft</strong> keeps whatever you’ve entered (only a name is needed) so you can finish later — it isn’t submitted for review yet.</p>
</form>

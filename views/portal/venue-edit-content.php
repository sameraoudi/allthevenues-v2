<?php
declare(strict_types=1);

/**
 * #3 U-P4 — Provider portal venue edit form. Editable inputs for LIVE fields only;
 * managed/sensitive fields (name, slug, type, emirate, status) shown read-only.
 * Reuses the admin form classes. Expects $venue, $old, $errors, $layoutValues.
 * NO internal contact / commission / featured/verified anywhere.
 */
/** @var array $venue @var array $old @var array $errors @var array $layoutValues */
$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$ta  = static fn(string $k): string => e((string)($old[$k] ?? ''));   // rich-text source (already sanitized)
$id  = (int)$venue['id'];
$richFields = [
    'description'   => 'Description',
    'best_for'      => 'Best for',
    'highlights'    => 'What makes it special (highlights)',
    'facilities'    => 'Facilities',
    'food_beverage' => 'Food & beverage',
    'av_support'    => 'AV & technical',
    'restrictions'  => 'Notes & restrictions',
    'packages'      => 'Packages',
    'special_offer' => 'Special offer',
];
$layoutValues = $layoutValues ?? [];
?>
<p><a class="lead-back" href="<?= e(base_url('portal/venues/' . $id)) ?>">&larr; Back to venue</a></p>

<div class="lead-detail__head">
  <h1>Edit <?= e((string)$venue['name']) ?></h1>
  <span class="lead-status lead-status--<?= e((string)$venue['status']) ?>"><?= e(venue_admin_status_label((string)$venue['status'])) ?></span>
</div>

<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('portal/venues/' . $id . '/edit')) ?>" novalidate>
  <?php csrf_field(); ?>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Managed by All The Venues</h2>
    <p class="lead-hint mb-2">These are managed by All The Venues. To change them, <a href="<?= e(base_url('portal/venues/' . $id . '/request')) ?>">request a change</a>.</p>
    <div class="admin-form__grid">
      <div class="atv-field"><label>Name</label><div class="portal-ro-val"><?= e((string)$venue['name']) ?></div></div>
      <div class="atv-field"><label>Slug</label><div class="portal-ro-val">/venues/<?= e((string)$venue['slug']) ?></div></div>
      <div class="atv-field"><label>Venue type</label><div class="portal-ro-val"><?= e((string)($venue['venue_type_name'] ?? '—')) ?></div></div>
      <div class="atv-field"><label>Primary emirate</label><div class="portal-ro-val"><?= e((string)($venue['emirate_name'] ?? '—')) ?></div></div>
      <div class="atv-field"><label>Status</label><div class="portal-ro-val"><?= e(venue_admin_status_label((string)$venue['status'])) ?></div></div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Location &amp; basics</h2>
    <div class="admin-form__grid">
      <div class="atv-field"><label for="f-area">Area</label><input type="text" id="f-area" name="area" value="<?= $v('area') ?>" maxlength="150"></div>
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
      <div class="atv-field"><label for="f-cmax">Maximum capacity</label><input type="number" id="f-cmax" name="capacity_max" value="<?= $v('capacity_max') ?>" min="0"></div>
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
    <p class="lead-hint mb-2">Seated/standing capacity per layout. Leave blank for layouts this venue doesn't offer.</p>
    <div class="admin-form__grid layout-grid">
      <?php foreach (venue_layout_types() as $ltype => $liconKey): ?>
        <div class="atv-field">
          <label class="layout-label" for="f-layout-<?= e($ltype) ?>"><?= icon($liconKey, 'layout-ico') ?> <?= e($ltype) ?></label>
          <input type="number" id="f-layout-<?= e($ltype) ?>" name="layout[<?= e($ltype) ?>]" value="<?= e((string)($layoutValues[$ltype] ?? '')) ?>" min="0">
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
    <?php foreach ($richFields as $field => $label): ?>
      <div class="atv-field"><label for="f-<?= e($field) ?>"><?= e($label) ?></label><textarea id="f-<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= $field === 'description' ? 5 : 3 ?>"><?= $ta($field) ?></textarea></div>
    <?php endforeach; ?>
  </div>

  <?php
    /* #3 U-P9b — event types. Published → read-only (governed); else editable,
       prefilled from the current tags (or the posted set on a re-render). */
    $etPublished = ((string)$venue['status'] === 'published');
    $etChecked   = $etPublished
        ? portal_venue_event_type_ids($pdo, (int)$venue['id'])
        : (isset($old['event_types']) ? array_map('intval', (array)$old['event_types']) : portal_venue_event_type_ids($pdo, (int)$venue['id']));
    $etVid       = (int)$venue['id'];
    include __DIR__ . '/event-types-field.php';
  ?>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn">Save changes</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/venues/' . $id)) ?>">Cancel</a>
  </div>
</form>

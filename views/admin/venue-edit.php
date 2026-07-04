<?php
declare(strict_types=1);

/**
 * Admin venue edit form. Expects $venue (original), $old (display values),
 * $errors, ?$flash, $venueTypes, $emirates, $id.
 * Rich-text fields show the stored (sanitized) HTML as editable source; they
 * are re-sanitized on save.
 */
/** @var array $venue @var array $old @var array $errors @var array $venueTypes @var array $emirates @var int $id */
$flash = $flash ?? null;
$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$ta  = static fn(string $k): string => e((string)($old[$k] ?? ''));   // textarea source (already sanitized)
$textFields = ['facilities' => 'Facilities', 'food_beverage' => 'Food & beverage', 'av_support' => 'AV & technical',
               'restrictions' => 'Notes & restrictions', 'packages' => 'Packages', 'special_offer' => 'Special offer'];
?>
<p><a class="lead-back" href="<?= e(base_url('admin/venues')) ?>">&larr; Back to venues</a></p>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('admin/venues/edit')) ?>" novalidate>
  <?php csrf_field(); ?>
  <input type="hidden" name="id" value="<?= e((string)$id) ?>">

  <div class="admin-panel">
    <div class="lead-detail__head">
      <h2 class="admin-panel__title">Basics</h2>
      <?php if (($venue['status'] ?? '') === 'published'): ?>
        <a class="lead-back" href="<?= e(base_url('venues/' . rawurlencode((string)$venue['slug']))) ?>" target="_blank" rel="noopener">View public page ↗</a>
      <?php endif; ?>
    </div>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-name">Name <span class="req">*</span></label>
        <input type="text" id="f-name" name="name" value="<?= $v('name') ?>" maxlength="255" class="<?= $has('name') ? 'is-invalid' : '' ?>">
        <?php $err('name'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label for="f-slug">Slug <span class="req">*</span></label>
        <input type="text" id="f-slug" name="slug" value="<?= $v('slug') ?>" maxlength="191" class="<?= $has('slug') ? 'is-invalid' : '' ?>">
        <p class="lead-hint">Changing the slug changes the public URL (/venues/&lt;slug&gt;). Old links will stop working.</p>
        <?php $err('slug'); ?>
      </div>
      <div class="atv-field">
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
          <?php foreach (venue_admin_statuses() as $k => $s): ?><option value="<?= e($k) ?>"<?= $sel('status', $k) ?>><?= e($s[0]) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field">
        <label for="f-type">Venue type</label>
        <select id="f-type" name="venue_type_id">
          <option value="">—</option>
          <?php foreach ($venueTypes as $vt): ?><option value="<?= e((string)$vt['id']) ?>"<?= $sel('venue_type_id', $vt['id']) ?>><?= e($vt['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field">
        <label for="f-emirate">Emirate</label>
        <select id="f-emirate" name="emirate_id">
          <option value="">—</option>
          <?php foreach ($emirates as $em): ?><option value="<?= e((string)$em['id']) ?>"<?= $sel('emirate_id', $em['id']) ?>><?= e($em['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field">
        <label for="f-partner">Provider</label>
        <select id="f-partner" name="partner_id">
          <option value="">— None —</option>
          <?php foreach ($partners as $pt): ?><option value="<?= e((string)$pt['id']) ?>"<?= $sel('partner_id', $pt['id']) ?>><?= e($pt['org_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field">
        <label for="f-io">Indoor / outdoor</label>
        <select id="f-io" name="indoor_outdoor">
          <?php foreach (venue_indoor_outdoor_options() as $k => $label): ?><option value="<?= e($k) ?>"<?= $sel('indoor_outdoor', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field"><label for="f-area">Area</label><input type="text" id="f-area" name="area" value="<?= $v('area') ?>" maxlength="150"></div>
      <div class="atv-field atv-field--full"><label for="f-address">Address</label><input type="text" id="f-address" name="address" value="<?= $v('address') ?>" maxlength="255"></div>
      <div class="atv-field atv-field--full">
        <label for="f-map">Map embed</label>
        <textarea id="f-map" name="map_embed" rows="3" class="<?= $has('map_embed') ? 'is-invalid' : '' ?>"><?= e((string)($old['map_embed'] ?? '')) ?></textarea>
        <p class="lead-hint">Paste the Google Maps embed &lt;iframe&gt;. Only a valid Google Maps iframe is shown publicly; anything else falls back to a Maps search link.</p>
        <?php $err('map_embed'); ?>
      </div>
      <div class="atv-field">
        <label class="atv-check"><input type="checkbox" name="is_featured" value="1"<?= !empty($old['is_featured']) ? ' checked' : '' ?>> <span>Featured</span></label>
        <label class="atv-check"><input type="checkbox" name="is_verified" value="1"<?= !empty($old['is_verified']) ? ' checked' : '' ?>> <span>Verified</span></label>
      </div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Capacity &amp; pricing</h2>
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
      <div class="atv-field"><label for="f-video">Video URL</label><input type="text" id="f-video" name="video_url" value="<?= $v('video_url') ?>" maxlength="255"></div>
      <div class="atv-field"><label for="f-website">Website</label><input type="text" id="f-website" name="website" value="<?= $v('website') ?>" maxlength="255"></div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Venue contact (internal)</h2>
    <p class="lead-hint mb-2">Admin-only — never shown publicly.</p>
    <div class="admin-form__grid">
      <div class="atv-field"><label for="f-cname">Contact name</label><input type="text" id="f-cname" name="contact_name" value="<?= $v('contact_name') ?>" maxlength="255"></div>
      <div class="atv-field">
        <label for="f-cemail">Contact email</label>
        <input type="email" id="f-cemail" name="contact_email" value="<?= $v('contact_email') ?>" maxlength="255" class="<?= $has('contact_email') ? 'is-invalid' : '' ?>">
        <?php $err('contact_email'); ?>
      </div>
      <div class="atv-field"><label for="f-cphone">Contact phone</label><input type="text" id="f-cphone" name="contact_phone" value="<?= $v('contact_phone') ?>" maxlength="50"></div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Description &amp; highlights</h2>
    <p class="lead-hint mb-2">Rich text — only p, br, strong, em, lists and links are kept; everything else is stripped on save.</p>
    <div class="atv-field"><label for="f-desc">Description</label><textarea id="f-desc" name="description" rows="5"><?= $ta('description') ?></textarea></div>
    <div class="atv-field"><label for="f-best">Best for</label><textarea id="f-best" name="best_for" rows="2"><?= $ta('best_for') ?></textarea></div>
    <div class="atv-field">
      <label for="f-high">What makes it special (highlights)</label>
      <textarea id="f-high" name="highlights" rows="4" placeholder="One differentiator per line (or short HTML list)"><?= $ta('highlights') ?></textarea>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Details</h2>
    <?php foreach ($textFields as $k => $label): ?>
      <div class="atv-field"><label for="f-<?= e($k) ?>"><?= e($label) ?></label><textarea id="f-<?= e($k) ?>" name="<?= e($k) ?>" rows="3"><?= $ta($k) ?></textarea></div>
    <?php endforeach; ?>
  </div>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn">Save venue</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/venues')) ?>">Cancel</a>
  </div>
</form>

<?php
/* ---- Image manager (U4b) — its own forms, OUTSIDE the scalar form above ---- */
/** @var array $images */
$images   = $images ?? [];
$venueId  = (int)($venue['id'] ?? 0);
$imgAct   = static fn(string $a): string => e(base_url('admin/venues/images/' . $a));
$hidden   = static function (int $vid, int $iid = 0) {
    echo '<input type="hidden" name="venue_id" value="' . e((string)$vid) . '">';
    if ($iid > 0) echo '<input type="hidden" name="image_id" value="' . e((string)$iid) . '">';
};
$lastIdx = count($images) - 1;
?>
<div class="admin-panel img-mgr">
  <h2 class="admin-panel__title">Images</h2>
  <p class="lead-hint mb-2">JPG, PNG or WebP. Uploads are converted to WebP and optimized automatically. The <strong>primary</strong> image is the venue’s hero; drag order via the arrows.</p>

  <form class="img-upload" method="post" action="<?= $imgAct('upload') ?>" enctype="multipart/form-data">
    <?php csrf_field(); $hidden($venueId); ?>
    <input type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required aria-label="Choose images">
    <button type="submit" class="atv-btn atv-btn--sm">Upload images</button>
  </form>

  <?php if (!$images): ?>
    <p class="text-muted mt-2">No images yet — upload the first one above.</p>
  <?php else: ?>
    <div class="img-grid">
      <?php foreach ($images as $i => $img):
        $iid       = (int)$img['id'];
        $isPrimary = (int)$img['is_primary'] === 1;
        $thumb     = venue_img_src(($img['thumb_path'] ?? '') !== '' ? $img['thumb_path'] : ($img['file_path'] ?? null));
      ?>
        <div class="img-card<?= $isPrimary ? ' is-primary' : '' ?>">
          <div class="img-card__thumb">
            <img src="<?= e($thumb) ?>" alt="" loading="lazy">
            <?php if ($isPrimary): ?><span class="img-card__badge">Primary</span><?php endif; ?>
          </div>

          <form method="post" action="<?= $imgAct('alt') ?>" class="img-card__alt">
            <?php csrf_field(); $hidden($venueId, $iid); ?>
            <label class="sr-only" for="alt-<?= e((string)$iid) ?>">Alt text</label>
            <input type="text" id="alt-<?= e((string)$iid) ?>" name="alt_text" value="<?= e((string)($img['alt_text'] ?? '')) ?>" maxlength="255" placeholder="Alt text (describe the image)">
            <button type="submit" class="atv-btn atv-btn--sm atv-btn--ghost">Save alt</button>
          </form>

          <div class="img-card__acts">
            <?php if (!$isPrimary): ?>
              <form method="post" action="<?= $imgAct('primary') ?>"><?php csrf_field(); $hidden($venueId, $iid); ?><button type="submit" class="atv-btn atv-btn--sm atv-btn--ghost">Set primary</button></form>
            <?php endif; ?>
            <form method="post" action="<?= $imgAct('reorder') ?>"><?php csrf_field(); $hidden($venueId, $iid); ?><input type="hidden" name="dir" value="up"><button type="submit" class="atv-btn atv-btn--sm atv-btn--ghost"<?= $i === 0 ? ' disabled' : '' ?> aria-label="Move up">&uarr;</button></form>
            <form method="post" action="<?= $imgAct('reorder') ?>"><?php csrf_field(); $hidden($venueId, $iid); ?><input type="hidden" name="dir" value="down"><button type="submit" class="atv-btn atv-btn--sm atv-btn--ghost"<?= $i === $lastIdx ? ' disabled' : '' ?> aria-label="Move down">&darr;</button></form>
            <form method="post" action="<?= $imgAct('delete') ?>" data-confirm="Delete this image? This can’t be undone."><?php csrf_field(); $hidden($venueId, $iid); ?><button type="submit" class="atv-btn atv-btn--sm atv-btn--danger">Delete</button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

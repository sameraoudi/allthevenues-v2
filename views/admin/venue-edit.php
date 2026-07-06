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
?>
<p><a class="lead-back" href="<?= e(base_url('admin/venues')) ?>">&larr; Back to venues</a></p>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('admin/venues/edit')) ?>" novalidate>
  <?php csrf_field(); ?>
  <input type="hidden" name="id" value="<?= e((string)$id) ?>">

  <?php require __DIR__ . '/_venue-fields.php'; ?>

  <?php
    /* Provider-ownership provenance (#6-2) — read-only; set automatically by the
       save logic when the Provider above changes. managed_by_provider is derived. */
    $mgManaged    = !empty($old['partner_id']);
    $mgSource     = venue_management_source_label((string)($old['management_source'] ?? 'unassigned'));
    $mgAssignedAt = $old['provider_assigned_at'] ?? null;
    $mgAssignedBy = trim((string)($old['assigned_by_name'] ?? ''));
  ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Management</h2>
    <p class="lead-hint mb-0">
      Provider-managed: <strong><?= $mgManaged ? 'Yes' : 'No' ?></strong>
      &middot; Source: <strong><?= e($mgSource) ?></strong><?php if (!empty($mgAssignedAt)): ?>
      &middot; Assigned <?= e(date('j M Y', strtotime((string)$mgAssignedAt))) ?><?php if ($mgAssignedBy !== ''): ?> by <?= e($mgAssignedBy) ?><?php endif; ?><?php endif; ?>
    </p>
    <p class="lead-hint mb-0">Set automatically when you change the Provider above.</p>
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
$permOpts = venue_images_permission_options();   // #9b — status ENUM → [label, cleared?]
// #9b — badge CSS variant for a permission status.
$permClass = static function (string $status) use ($permOpts): string {
    if ($status === 'remove_replace') { return 'img-perm--remove'; }
    return !empty($permOpts[$status][1]) ? 'img-perm--ok' : 'img-perm--review';
};
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
        <?php
          $permStatus = (string)($img['permission_status'] ?? 'legacy_needs_review');
          $permLabel  = $permOpts[$permStatus][0] ?? 'Needs review';
        ?>
        <div class="img-card<?= $isPrimary ? ' is-primary' : '' ?>">
          <div class="img-card__thumb">
            <img src="<?= e($thumb) ?>" alt="" loading="lazy">
            <?php if ($isPrimary): ?><span class="img-card__badge">Primary</span><?php endif; ?>
          </div>

          <span class="img-perm <?= e($permClass($permStatus)) ?>"><?= e($permLabel) ?></span>

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

          <details class="img-rights">
            <summary>Image rights</summary>
            <form method="post" action="<?= $imgAct('provenance') ?>" class="img-rights__form">
              <?php csrf_field(); $hidden($venueId, $iid); ?>
              <label for="perm-<?= e((string)$iid) ?>">Permission status</label>
              <select id="perm-<?= e((string)$iid) ?>" name="permission_status">
                <?php foreach ($permOpts as $k => $opt): ?>
                  <option value="<?= e($k) ?>"<?= $permStatus === $k ? ' selected' : '' ?>><?= e($opt[0]) ?></option>
                <?php endforeach; ?>
              </select>
              <label for="src-<?= e((string)$iid) ?>">Source</label>
              <input type="text" id="src-<?= e((string)$iid) ?>" name="image_source" value="<?= e((string)($img['image_source'] ?? '')) ?>" maxlength="100" placeholder="e.g. Provider, ATV shoot, Unsplash">
              <label for="url-<?= e((string)$iid) ?>">Source URL</label>
              <input type="text" id="url-<?= e((string)$iid) ?>" name="source_url" value="<?= e((string)($img['source_url'] ?? '')) ?>" maxlength="255">
              <label for="by-<?= e((string)$iid) ?>">Approved by</label>
              <input type="text" id="by-<?= e((string)$iid) ?>" name="provider_approved_by" value="<?= e((string)($img['provider_approved_by'] ?? '')) ?>" maxlength="255" placeholder="Who confirmed rights">
              <label for="ad-<?= e((string)$iid) ?>">Approval date</label>
              <input type="date" id="ad-<?= e((string)$iid) ?>" name="approval_date" value="<?= e((string)($img['approval_date'] ?? '')) ?>">
              <label for="exp-<?= e((string)$iid) ?>">Licence expiry</label>
              <input type="date" id="exp-<?= e((string)$iid) ?>" name="expires_at" value="<?= e((string)($img['expires_at'] ?? '')) ?>">
              <label for="notes-<?= e((string)$iid) ?>">Usage notes</label>
              <textarea id="notes-<?= e((string)$iid) ?>" name="usage_notes" rows="2"><?= e((string)($img['usage_notes'] ?? '')) ?></textarea>
              <button type="submit" class="atv-btn atv-btn--sm">Save image rights</button>
            </form>
          </details>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

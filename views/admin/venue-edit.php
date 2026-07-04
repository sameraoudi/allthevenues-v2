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

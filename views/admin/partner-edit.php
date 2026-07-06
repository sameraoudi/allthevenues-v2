<?php
declare(strict_types=1);

/**
 * Admin provider (partner) edit form. Expects $partner (original), $old (display
 * values), $errors, ?$flash, $emirates, $id. `about` is sanitized HTML shown as
 * editable source; re-sanitized on save. Provider "type" is read-only here
 * (editable type is U4d-3b).
 */
/** @var array $partner @var array $old @var array $errors @var array $emirates @var int $id */
require_once __DIR__ . '/../../lib/partners.php';
require_once __DIR__ . '/../../lib/partner_admin.php';

$flash = $flash ?? null;
$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$ta  = static fn(string $k): string => e((string)($old[$k] ?? ''));   // textarea source (already sanitized)
?>
<p><a class="lead-back" href="<?= e(base_url('admin/partners')) ?>">&larr; Back to providers</a></p>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('admin/partners/edit')) ?>" novalidate>
  <?php csrf_field(); ?>
  <input type="hidden" name="id" value="<?= e((string)$id) ?>">

  <div class="admin-panel">
    <div class="lead-detail__head">
      <h2 class="admin-panel__title">Basics</h2>
      <?php if (($partner['status'] ?? '') === 'approved'): ?>
        <a class="lead-back" href="<?= e(base_url('providers/' . rawurlencode((string)$partner['slug']))) ?>" target="_blank" rel="noopener">View public page ↗</a>
      <?php endif; ?>
    </div>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-name">Name <span class="req">*</span></label>
        <input type="text" id="f-name" name="org_name" value="<?= $v('org_name') ?>" maxlength="255" class="<?= $has('org_name') ? 'is-invalid' : '' ?>">
        <?php $err('org_name'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label for="f-slug">Slug <span class="req">*</span></label>
        <input type="text" id="f-slug" name="slug" value="<?= $v('slug') ?>" maxlength="191" class="<?= $has('slug') ? 'is-invalid' : '' ?>">
        <p class="lead-hint">Changing the slug changes the public URL (/providers/&lt;slug&gt;). Old links auto-redirect to the new URL.</p>
        <?php $err('slug'); ?>
      </div>
      <div class="atv-field">
        <label for="f-status">Status</label>
        <select id="f-status" name="status">
          <?php foreach (partner_admin_statuses() as $k => $s): ?><option value="<?= e($k) ?>"<?= $sel('status', $k) ?>><?= e($s[0]) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field">
        <label for="f-emirate">Emirate</label>
        <select id="f-emirate" name="emirate_id">
          <option value="">—</option>
          <?php foreach ($emirates as $em): ?><option value="<?= e((string)$em['id']) ?>"<?= $sel('emirate_id', $em['id']) ?>><?= e($em['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field"><label for="f-city">City / area</label><input type="text" id="f-city" name="city_text" value="<?= $v('city_text') ?>" maxlength="150"></div>
      <div class="atv-field">
        <label for="f-type">Provider type</label>
        <select id="f-type" name="partner_type">
          <option value="">Use type from migration</option>
          <?php foreach (partner_type_buckets() as $k => $b): ?>
            <option value="<?= e($k) ?>"<?= ((string)($old['partner_group'] ?? '') === $b[0]) ? ' selected' : '' ?>><?= e($b[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="lead-hint">Currently showing: <b><?= e(partner_type_label($old['raw_org_type'] ?? null)) ?></b>. Blank = keep the type derived from the migration.</p>
      </div>
      <div class="atv-field"><label for="f-contact">Contact name</label><input type="text" id="f-contact" name="contact_name" value="<?= $v('contact_name') ?>" maxlength="255"></div>
      <div class="atv-field">
        <label for="f-email">Email</label>
        <input type="email" id="f-email" name="email" value="<?= $v('email') ?>" maxlength="255" class="<?= $has('email') ? 'is-invalid' : '' ?>">
        <?php $err('email'); ?>
      </div>
      <div class="atv-field"><label for="f-phone">Phone</label><input type="text" id="f-phone" name="phone" value="<?= $v('phone') ?>" maxlength="50"></div>
      <div class="atv-field atv-field--full"><label for="f-website">Website</label><input type="text" id="f-website" name="website" value="<?= $v('website') ?>" maxlength="255"></div>
      <div class="atv-field">
        <label class="atv-check"><input type="checkbox" name="is_featured" value="1"<?= !empty($old['is_featured']) ? ' checked' : '' ?>> <span>Featured</span></label>
        <label class="atv-check"><input type="checkbox" name="is_verified" value="1"<?= !empty($old['is_verified']) ? ' checked' : '' ?>> <span>Verified</span></label>
      </div>
      <div class="atv-field">
        <label for="f-commission">Commission (%)</label>
        <input type="number" id="f-commission" name="commission_rate" value="<?= $v('commission_rate') ?>" min="0" max="100" step="0.01" class="<?= $has('commission_rate') ? 'is-invalid' : '' ?>">
        <p class="lead-hint">Admin-only, never public. Blank = unknown · 0 = no commission · or a rate, e.g. 10.</p>
        <?php $err('commission_rate'); ?>
      </div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">About</h2>
    <p class="lead-hint mb-2">Rich text — only p, br, strong, em, lists and links are kept; everything else is stripped on save.</p>
    <div class="atv-field"><label for="f-about">About</label><textarea id="f-about" name="about" rows="6"><?= $ta('about') ?></textarea></div>
  </div>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn">Save provider</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/partners')) ?>">Cancel</a>
  </div>
</form>

<?php
/* ---- Cover image (U4d-3c) — single image, OUTSIDE the scalar form above ---- */
$coverFull   = trim((string)($partner['cover_image_path'] ?? ''));
$coverThumb  = trim((string)($partner['cover_thumb_path'] ?? ''));
$coverAlt    = (string)($partner['cover_image_alt'] ?? '');
$hasCoverImg = $coverFull !== '';
$coverAction = static fn(string $a): string => e(base_url('admin/partners/cover/' . $a));
// #9c — cover permission badge/sub-form (mirrors the venue image card).
$coverPermOpts   = venue_images_permission_options();
$coverPermStatus = (string)($partner['cover_permission_status'] ?? 'legacy_needs_review');
$coverPermLabel  = $coverPermOpts[$coverPermStatus][0] ?? 'Needs review';
$coverPermClass  = ($coverPermStatus === 'remove_replace') ? 'img-perm--remove'
    : (!empty($coverPermOpts[$coverPermStatus][1]) ? 'img-perm--ok' : 'img-perm--review');
?>
<div class="admin-panel img-mgr">
  <h2 class="admin-panel__title">Cover image</h2>
  <p class="lead-hint mb-2">One landscape image (3:2 works best). JPG, PNG or WebP — converted to WebP automatically. Shown as this provider's cover on the public page, overriding the venue-derived image.</p>

  <?php if ($hasCoverImg): ?>
    <div class="img-grid">
      <div class="img-card is-primary">
        <div class="img-card__thumb"><img src="<?= e(base_url($coverThumb !== '' ? $coverThumb : $coverFull)) ?>" alt="" loading="lazy"></div>
        <form method="post" action="<?= $coverAction('alt') ?>" class="img-card__alt">
          <?php csrf_field(); ?><input type="hidden" name="partner_id" value="<?= e((string)$id) ?>">
          <label class="sr-only" for="cover-alt">Alt text</label>
          <input type="text" id="cover-alt" name="alt_text" value="<?= e($coverAlt) ?>" maxlength="255" placeholder="Alt text (describe the image)">
          <button type="submit" class="atv-btn atv-btn--sm atv-btn--ghost">Save alt</button>
        </form>
        <div class="img-card__acts">
          <form method="post" action="<?= $coverAction('delete') ?>" data-confirm="Remove this cover image?"><?php csrf_field(); ?><input type="hidden" name="partner_id" value="<?= e((string)$id) ?>"><button type="submit" class="atv-btn atv-btn--sm atv-btn--danger">Remove</button></form>
        </div>

        <span class="img-perm <?= e($coverPermClass) ?>"><?= e($coverPermLabel) ?></span>
        <details class="img-rights">
          <summary>Image rights</summary>
          <form method="post" action="<?= $coverAction('provenance') ?>" class="img-rights__form">
            <?php csrf_field(); ?><input type="hidden" name="partner_id" value="<?= e((string)$id) ?>">
            <label for="cover-perm">Permission status</label>
            <select id="cover-perm" name="permission_status">
              <?php foreach ($coverPermOpts as $k => $opt): ?>
                <option value="<?= e($k) ?>"<?= $coverPermStatus === $k ? ' selected' : '' ?>><?= e($opt[0]) ?></option>
              <?php endforeach; ?>
            </select>
            <label for="cover-src">Source</label>
            <input type="text" id="cover-src" name="image_source" value="<?= e((string)($partner['cover_image_source'] ?? '')) ?>" maxlength="100" placeholder="e.g. Provider, ATV shoot, Unsplash">
            <label for="cover-url">Source URL</label>
            <input type="text" id="cover-url" name="source_url" value="<?= e((string)($partner['cover_source_url'] ?? '')) ?>" maxlength="255">
            <label for="cover-by">Approved by</label>
            <input type="text" id="cover-by" name="provider_approved_by" value="<?= e((string)($partner['cover_provider_approved_by'] ?? '')) ?>" maxlength="255" placeholder="Who confirmed rights">
            <label for="cover-ad">Approval date</label>
            <input type="date" id="cover-ad" name="approval_date" value="<?= e((string)($partner['cover_approval_date'] ?? '')) ?>">
            <label for="cover-exp">Licence expiry</label>
            <input type="date" id="cover-exp" name="expires_at" value="<?= e((string)($partner['cover_expires_at'] ?? '')) ?>">
            <label for="cover-notes">Usage notes</label>
            <textarea id="cover-notes" name="usage_notes" rows="2"><?= e((string)($partner['cover_usage_notes'] ?? '')) ?></textarea>
            <button type="submit" class="atv-btn atv-btn--sm">Save image rights</button>
          </form>
        </details>
      </div>
    </div>
  <?php endif; ?>

  <form class="img-upload" method="post" action="<?= $coverAction('upload') ?>" enctype="multipart/form-data">
    <?php csrf_field(); ?><input type="hidden" name="partner_id" value="<?= e((string)$id) ?>">
    <input type="file" name="cover" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required aria-label="Choose cover image">
    <button type="submit" class="atv-btn atv-btn--sm"><?= $hasCoverImg ? 'Replace cover' : 'Upload cover' ?></button>
  </form>
</div>

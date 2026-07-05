<?php
declare(strict_types=1);

/**
 * Admin "Add provider" create form (U4d-4). Self-contained (mirrors the Basics +
 * About panels of partner-edit.php). Creating INSERTs a draft then redirects to
 * the edit page — the cover image manager needs a partner id first.
 * Expects $old, $errors, $emirates in scope.
 */
/** @var array $old @var array $errors @var array $emirates */
require_once __DIR__ . '/../../lib/partners.php';
require_once __DIR__ . '/../../lib/partner_admin.php';

$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$ta  = static fn(string $k): string => e((string)($old[$k] ?? ''));
?>
<p><a class="lead-back" href="<?= e(base_url('admin/partners')) ?>">&larr; Back to providers</a></p>

<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('admin/partners/new')) ?>" novalidate>
  <?php csrf_field(); ?>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Basics</h2>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-name">Name <span class="req">*</span></label>
        <input type="text" id="f-name" name="org_name" value="<?= $v('org_name') ?>" maxlength="255" class="<?= $has('org_name') ? 'is-invalid' : '' ?>">
        <?php $err('org_name'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label for="f-slug">Slug</label>
        <input type="text" id="f-slug" name="slug" value="<?= $v('slug') ?>" maxlength="191" class="<?= $has('slug') ? 'is-invalid' : '' ?>">
        <p class="lead-hint">Public URL (/providers/&lt;slug&gt;). Leave blank to generate one from the name.</p>
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
          <option value="">Default (derive later)</option>
          <?php foreach (partner_type_buckets() as $k => $b): ?>
            <option value="<?= e($k) ?>"<?= ((string)($old['partner_type'] ?? '') === $k) ? ' selected' : '' ?>><?= e($b[0]) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="lead-hint">Optional — leave blank to set/derive it later.</p>
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
    <button type="submit" class="atv-btn">Create provider</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/partners')) ?>">Cancel</a>
  </div>
  <p class="lead-hint">Save the provider first, then you can upload a cover image and finish the remaining details on the edit screen.</p>
</form>

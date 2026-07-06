<?php
declare(strict_types=1);

/**
 * #3 U-P5a — Provider portal: change-request form for REQUEST-tier fields
 * (name, slug, venue type, primary emirate). Prefilled with the venue's CURRENT
 * values. Submitting proposes changes for admin review — the venue is NOT edited
 * here. Reuses the admin form classes. Expects $venue, $old, $errors, $pdo.
 */
/** @var array $venue @var array $old @var array $errors @var PDO $pdo */
$v   = static fn(string $k): string => e((string)($old[$k] ?? ''));
$has = static fn(string $k) => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$id  = (int)$venue['id'];
?>
<p><a class="lead-back" href="<?= e(base_url('portal/venues/' . $id)) ?>">&larr; Back to venue</a></p>

<div class="lead-detail__head">
  <h1>Request changes</h1>
  <span class="lead-status lead-status--<?= e((string)$venue['status']) ?>"><?= e(venue_admin_status_label((string)$venue['status'])) ?></span>
</div>

<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('portal/venues/' . $id . '/request')) ?>" novalidate>
  <?php csrf_field(); ?>

  <div class="admin-panel">
    <p class="lead-hint mb-2">These changes are reviewed by All The Venues before they go live. Your venue stays unchanged until a request is approved.</p>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-name">Name</label>
        <input type="text" id="f-name" name="name" value="<?= $v('name') ?>" maxlength="255" class="<?= $has('name') ? 'is-invalid' : '' ?>">
        <?php $err('name'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label for="f-slug">Slug (public URL)</label>
        <input type="text" id="f-slug" name="slug" value="<?= $v('slug') ?>" maxlength="191" class="<?= $has('slug') ? 'is-invalid' : '' ?>">
        <p class="lead-hint">This changes the public web address (/venues/&hellip;) and is subject to review.</p>
        <?php $err('slug'); ?>
      </div>
      <div class="atv-field">
        <label for="f-type">Venue type</label>
        <select id="f-type" name="venue_type_id">
          <option value="">—</option>
          <?php foreach (venue_types_all($pdo) as $t): ?><option value="<?= (int)$t['id'] ?>"<?= $sel('venue_type_id', (int)$t['id']) ?>><?= e((string)$t['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="atv-field">
        <label for="f-emirate">Primary emirate</label>
        <select id="f-emirate" name="emirate_id">
          <option value="">—</option>
          <?php foreach (venue_emirates($pdo) as $em): ?><option value="<?= (int)$em['id'] ?>"<?= $sel('emirate_id', (int)$em['id']) ?>><?= e((string)$em['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn">Submit request</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/venues/' . $id)) ?>">Cancel</a>
  </div>
</form>

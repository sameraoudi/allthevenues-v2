<?php
declare(strict_types=1);

/**
 * Admin user add/edit form. Expects $isNew (bool), $old (display values),
 * $errors, $id (int), $meId (int). Never renders password hashes.
 */
/** @var bool $isNew @var array $old @var array $errors @var int $id @var int $meId */
$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel     = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$isSelf  = (!$isNew && (int)($id) === (int)$meId);
$action  = $isNew ? base_url('admin/users/new') : base_url('admin/users/edit');
?>
<p><a class="lead-back" href="<?= e(base_url('admin/users')) ?>">&larr; Back to users</a></p>

<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e($action) ?>" novalidate>
  <?php csrf_field(); ?>
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= e((string)$id) ?>"><?php endif; ?>

  <div class="admin-panel">
    <h2 class="admin-panel__title"><?= $isNew ? 'New user' : 'Edit user' ?></h2>
    <?php if ($isSelf): ?>
      <p class="lead-hint mb-2">This is your own account — you can’t change your role or disable yourself.</p>
    <?php endif; ?>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-name">Name <span class="req">*</span></label>
        <input type="text" id="f-name" name="name" value="<?= $v('name') ?>" maxlength="255" class="<?= $has('name') ? 'is-invalid' : '' ?>">
        <?php $err('name'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label for="f-email">Email <span class="req">*</span></label>
        <input type="email" id="f-email" name="email" value="<?= $v('email') ?>" maxlength="255" class="<?= $has('email') ? 'is-invalid' : '' ?>">
        <?php $err('email'); ?>
      </div>
      <div class="atv-field">
        <label for="f-role">Role <span class="req">*</span></label>
        <select id="f-role" name="role" class="<?= $has('role') ? 'is-invalid' : '' ?>">
          <?php foreach (user_admin_roles() as $k => $label): ?><option value="<?= e($k) ?>"<?= $sel('role', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <?php $err('role'); ?>
      </div>
      <div class="atv-field">
        <label for="f-status">Status <span class="req">*</span></label>
        <select id="f-status" name="status" class="<?= $has('status') ? 'is-invalid' : '' ?>">
          <?php foreach (user_admin_statuses() as $k => $label): ?><option value="<?= e($k) ?>"<?= $sel('status', $k) ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <?php $err('status'); ?>
      </div>
    </div>
  </div>

  <div class="admin-panel">
    <h2 class="admin-panel__title">Password</h2>
    <p class="lead-hint mb-2">
      <?= $isNew
          ? 'Set an initial password (min 10 characters), or tick “generate” to create a temporary one shown once.'
          : 'Leave blank to keep the current password. Enter a new one (min 10) or tick “generate” to reset.' ?>
    </p>
    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-password"><?= $isNew ? 'Initial password' : 'New password' ?></label>
        <input type="text" id="f-password" name="password" value="" maxlength="255" autocomplete="new-password" class="<?= $has('password') ? 'is-invalid' : '' ?>">
        <?php $err('password'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label class="atv-check"><input type="checkbox" name="gen_password" value="1"> <span>Generate a temporary password (shown once)</span></label>
      </div>
    </div>
  </div>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn"><?= $isNew ? 'Create user' : 'Save user' ?></button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/users')) ?>">Cancel</a>
  </div>
</form>

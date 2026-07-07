<?php
declare(strict_types=1);

/**
 * PU-A2 — Account content: read-only details (admin-managed) + change-password
 * form. Expects $me, $orgName, $errors, ?$flash. All output escaped.
 */
/** @var array $me @var string $orgName @var array $errors @var ?array $flash */
$me      = $me ?? [];
$orgName = $orgName ?? '';
$errors  = $errors ?? [];
$flash   = $flash ?? null;
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
?>
<div class="pcontent__head">
  <div>
    <h1 class="pcontent__title">Account</h1>
    <p class="pcontent__sub">Your details and sign-in password.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div>
<?php endif; ?>

<div class="admin-panel">
  <h2 class="admin-panel__title">Your details</h2>
  <p class="lead-hint mb-2">These are managed by All The Venues. To change them, <a href="<?= e(base_url('contact')) ?>">contact All The Venues</a>.</p>
  <div class="lead-detail__row"><span class="lead-detail__k">Name</span><span class="lead-detail__v"><?= e((string)($me['name'] ?? '—')) ?></span></div>
  <div class="lead-detail__row"><span class="lead-detail__k">Email</span><span class="lead-detail__v"><?= e((string)($me['email'] ?? '—')) ?></span></div>
  <div class="lead-detail__row"><span class="lead-detail__k">Partner</span><span class="lead-detail__v"><?= $orgName !== '' ? e($orgName) : '<span class="text-muted">&mdash;</span>' ?></span></div>
</div>

<div class="admin-panel">
  <h2 class="admin-panel__title">Change password</h2>
  <p class="lead-hint mb-2">Choose a strong password of at least 10 characters that isn&rsquo;t your name, email, or a common password.</p>
  <?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>
  <form class="admin-form" method="post" action="<?= e(base_url('portal/account')) ?>" novalidate>
    <?php csrf_field(); ?>
    <div class="atv-field atv-field--full">
      <label for="ac-cur">Current password</label>
      <input type="password" id="ac-cur" name="current_password" autocomplete="current-password" class="<?= isset($errors['current_password']) ? 'is-invalid' : '' ?>">
      <?php $err('current_password'); ?>
    </div>
    <div class="atv-field atv-field--full">
      <label for="ac-new">New password</label>
      <input type="password" id="ac-new" name="new_password" autocomplete="new-password" class="<?= isset($errors['new_password']) ? 'is-invalid' : '' ?>">
      <?php $err('new_password'); ?>
    </div>
    <div class="atv-field atv-field--full">
      <label for="ac-conf">Confirm new password</label>
      <input type="password" id="ac-conf" name="confirm_password" autocomplete="new-password">
    </div>
    <div class="admin-form__actions">
      <button type="submit" class="atv-btn">Update password</button>
    </div>
  </form>
</div>

<?php
declare(strict_types=1);

/**
 * Admin user add/edit form. Expects $isNew, $old, $errors, $id, $meId,
 * $providerOptions, and (edit-partner only) $inviteStatus, $passwordStatus,
 * $inviteLatest, ?$flash. Never renders password hashes. #3 U-P9a adds the
 * Venue Provider path: a provider selector + emailed set-password invite (no
 * password fields), plus the three-status detail + resend. Server enforces all
 * rules; the data-* fields are shown/hidden by app.js as a convenience.
 */
/** @var bool $isNew @var array $old @var array $errors @var int $id @var int $meId @var array $providerOptions */
$v   = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
$sel     = static fn(string $k, $val): string => ((string)($old[$k] ?? '') === (string)$val) ? ' selected' : '';
$isSelf  = (!$isNew && (int)($id) === (int)$meId);
$isPartner = ((string)($old['role'] ?? '') === 'partner');
$action  = $isNew ? base_url('admin/users/new') : base_url('admin/users/edit');
$flash   = $flash ?? null;
$providerOptions = $providerOptions ?? [];
$stChip = [
    'active' => ['Active', 'st-ok'], 'disabled' => ['Disabled', 'st-off'],
    'not_sent' => ['Not sent', 'st-off'], 'pending' => ['Pending', 'st-pend'],
    'accepted' => ['Accepted', 'st-ok'], 'expired' => ['Expired', 'st-exp'],
    'set' => ['Set', 'st-ok'], 'not_set' => ['Not set', 'st-off'],
];
$chip = static function (string $key) use ($stChip): string {
    [$label, $cls] = $stChip[$key] ?? [ucfirst($key), 'st-off'];
    return '<span class="onb-st ' . e($cls) . '">' . e($label) . '</span>';
};
?>
<p><a class="lead-back" href="<?= e(base_url('admin/users')) ?>">&larr; Back to users</a></p>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['msg']) ?></div><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<?php
/* ---- Edit a partner: three separate statuses + resend (#3 U-P9a) ---- */
if (!$isNew && $isPartner && isset($inviteStatus, $passwordStatus)):
    $showResend = ($passwordStatus === 'not_set' || $inviteStatus === 'expired');
?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Provider portal access</h2>
    <div class="onb-rows">
      <div class="onb-row"><span class="onb-k">Account status</span><span><?= $chip((string)($old['status'] ?? 'active')) ?></span></div>
      <div class="onb-row"><span class="onb-k">Invite status</span><span><?= $chip((string)$inviteStatus) ?><?= $inviteStatus === 'pending' ? ' — awaiting password setup' : '' ?></span></div>
      <div class="onb-row"><span class="onb-k">Password status</span><span><?= $chip((string)$passwordStatus) ?></span></div>
      <?php if (!empty($inviteLatest)): ?>
        <div class="onb-row"><span class="onb-k">Invite sent</span><span><?= e(date('j M Y, H:i', strtotime((string)$inviteLatest['created_at']))) ?><?= !empty($inviteLatest['sent_to']) ? ' · to ' . e((string)$inviteLatest['sent_to']) : '' ?></span></div>
        <div class="onb-row"><span class="onb-k">Invite expires</span><span><?= e(date('j M Y, H:i', strtotime((string)$inviteLatest['expires_at']))) ?></span></div>
        <?php if (!empty($inviteLatest['created_by_name'])): ?><div class="onb-row"><span class="onb-k">Last invite sent by</span><span><?= e((string)$inviteLatest['created_by_name']) ?></span></div><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php if ($showResend): ?>
      <form method="post" action="<?= e(base_url('admin/users/resend')) ?>" class="mt-2">
        <?php csrf_field(); ?>
        <input type="hidden" name="id" value="<?= e((string)$id) ?>">
        <button type="submit" class="atv-btn atv-btn--sm atv-btn--ghost" data-confirm="Resend the set-up invite? The previous link will stop working.">Resend invite</button>
        <span class="lead-hint">Shown only while the password is Not set or the invite has expired. Resending invalidates the previous link.</span>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= e($action) ?>" novalidate data-user-form>
  <?php csrf_field(); ?>
  <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= e((string)$id) ?>"><?php endif; ?>

  <div class="admin-panel">
    <h2 class="admin-panel__title"><?= $isNew ? 'New account' : 'Edit account' ?></h2>
    <?php if ($isNew): ?><p class="lead-hint mb-2">Create a staff account (Administrator / Editor) or a Venue Provider portal account.</p><?php endif; ?>
    <?php if ($isSelf): ?><p class="lead-hint mb-2">This is your own account — you can’t change your role or disable yourself.</p><?php endif; ?>
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
        <select id="f-role" name="role" class="<?= $has('role') ? 'is-invalid' : '' ?>" data-role-select>
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
      <div class="atv-field atv-field--full" data-partner-field<?= $isPartner ? '' : ' hidden' ?>>
        <label for="f-partner">Provider <span class="req">*</span></label>
        <select id="f-partner" name="partner_id" class="<?= $has('partner_id') ? 'is-invalid' : '' ?>">
          <option value="">— Select a provider —</option>
          <?php foreach ($providerOptions as $p): ?><option value="<?= (int)$p['id'] ?>"<?= $sel('partner_id', (int)$p['id']) ?>><?= e((string)$p['org_name']) ?></option><?php endforeach; ?>
        </select>
        <p class="lead-hint">Required for Venue Provider accounts. Staff roles have no provider.</p>
        <?php $err('partner_id'); ?>
        <div class="onb-scope">This user will be able to access venues assigned to the selected provider. They will not be able to access venues assigned to other providers.</div>
      </div>
    </div>
    <div class="onb-info" data-partner-field<?= $isPartner ? '' : ' hidden' ?>>
      <strong>No password is set here.</strong> When you create this account we email the provider a secure one-time link to set their own password. The account can’t sign in until they do. You can resend the invite from this page.
    </div>
  </div>

  <div class="admin-panel" data-staff-field<?= $isPartner ? ' hidden' : '' ?>>
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
    <button type="submit" class="atv-btn"><?= $isNew ? 'Create account' : 'Save account' ?></button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/users')) ?>">Cancel</a>
  </div>
</form>

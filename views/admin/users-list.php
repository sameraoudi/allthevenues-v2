<?php
declare(strict_types=1);

/**
 * Admin user list. Expects $rows (users), ?$flash, ?$tempPw, $me.
 * Never renders password hashes. $tempPw (if set) is a one-time reveal.
 */
/** @var array $rows @var array $me */
require_once __DIR__ . '/../../lib/users_admin.php';

$flash    = $flash ?? null;
$tempPw   = $tempPw ?? null;
$meId     = (int)($me['id'] ?? 0);
$roleLbls = user_admin_roles();
?>
<div class="lead-toolbar">
  <div class="lead-toolbar__counts"><strong><?= e((string)count($rows)) ?></strong> user<?= count($rows) === 1 ? '' : 's' ?></div>
  <a class="atv-btn atv-btn--sm" href="<?= e(base_url('admin/users/new')) ?>">Add user</a>
</div>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e($flash['type']) ?>" role="status"><?= e($flash['msg']) ?></div><?php endif; ?>

<?php if ($tempPw): ?>
  <div class="lead-flash lead-flash--temp" role="status">
    Temporary password for <strong><?= e($tempPw['email']) ?></strong>:
    <code class="temp-pw"><?= e($tempPw['password']) ?></code>
    — shown once. Share it securely; the user should change it after signing in.
  </div>
<?php endif; ?>

<div class="lead-table-wrap">
  <table class="lead-table">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $u): $edit = base_url('admin/users/edit') . query_string(['id' => (int)$u['id']]); ?>
        <tr>
          <td data-label="Name">
            <a class="lead-ref" href="<?= e($edit) ?>"><?= e($u['name']) ?></a>
            <?php if ((int)$u['id'] === $meId): ?><span class="user-you">You</span><?php endif; ?>
          </td>
          <td data-label="Email"><?= e($u['email']) ?></td>
          <td data-label="Role"><?= e($roleLbls[$u['role']] ?? auth_role_label((string)$u['role'])) ?></td>
          <td data-label="Status"><span class="lead-status lead-status--<?= e($u['status']) ?>"><?= e(user_admin_statuses()[$u['status']] ?? $u['status']) ?></span></td>
          <td data-label="Last login"><?= e($u['last_login_at'] ? date('j M Y, H:i', strtotime((string)$u['last_login_at'])) : '—') ?></td>
          <td data-label=""><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($edit) ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
declare(strict_types=1);

/** Placeholder / not-found panel within the admin shell.
 *  Expects $sectionTitle (string) and $admin_notfound (bool). */
/** @var string $sectionTitle @var bool $admin_notfound */
?>
<div class="admin-panel admin-panel--center">
  <?php if (!empty($admin_notfound)): ?>
    <h2 class="admin-panel__title">Page not found</h2>
    <p class="text-muted">That admin page doesn’t exist.</p>
  <?php else: ?>
    <h2 class="admin-panel__title"><?= e($sectionTitle) ?></h2>
    <p class="text-muted"><?= e($sectionTitle) ?> management is coming in an upcoming unit.</p>
  <?php endif; ?>
  <a class="atv-btn atv-btn--sm" href="<?= e(base_url('admin')) ?>">Back to dashboard</a>
</div>

<?php
declare(strict_types=1);

/** 403 panel within the admin shell (rendered by auth_require_role() on a
 *  role mismatch). No sensitive detail — just a clear message. */
?>
<div class="admin-panel admin-panel--center">
  <h2 class="admin-panel__title">Not authorized</h2>
  <p class="text-muted">You don’t have access to this area. If you think this is a mistake, contact an administrator.</p>
  <a class="atv-btn atv-btn--sm" href="<?= e(base_url('admin')) ?>">Back to dashboard</a>
</div>

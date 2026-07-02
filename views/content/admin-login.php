<?php
declare(strict_types=1);

/** Admin login form. Expects ?string $loginError, array $old. */
/** @var ?string $loginError @var array $old */
require_once __DIR__ . '/../../lib/csrf.php';
$old = $old ?? [];
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card admin-login">
      <header class="atv-enq__head">
        <h1>Admin sign in</h1>
        <p class="atv-enq__intro">Sign in to manage All The Venues.</p>
      </header>

      <?php if ($loginError !== null): ?>
        <div class="atv-enq__alert" role="alert"><?= e($loginError) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= e(base_url('admin/login')) ?>" novalidate>
        <?php csrf_field(); ?>
        <div class="atv-field">
          <label for="a-email">Email</label>
          <input type="email" id="a-email" name="email" value="<?= e((string)($old['email'] ?? '')) ?>"
                 required autocomplete="username" autofocus>
        </div>
        <div class="atv-field">
          <label for="a-pass">Password</label>
          <input type="password" id="a-pass" name="password" required autocomplete="current-password">
        </div>
        <div class="atv-enq-nav">
          <button type="submit" class="atv-btn">Sign in</button>
        </div>
      </form>
    </div>
  </div>
</section>

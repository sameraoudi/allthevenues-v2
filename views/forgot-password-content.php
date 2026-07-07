<?php
declare(strict_types=1);

/**
 * PU-B — "forgot password" request card. Renders by $fpMode: request | requested.
 * Built to docs/atv-portal-reset-password-preview.html. Expects $fpMode, ?$fpError.
 * Escapes everything; no inline styles/scripts (CSP self-only).
 */
/** @var string $fpMode @var ?string $fpError */
$fpError = $fpError ?? null;
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card admin-login onb-card">

      <?php if ($fpMode === 'requested'): ?>
        <header class="atv-enq__head"><h1>Check your email</h1></header>
        <div class="lead-flash lead-flash--success" role="status">If an account exists for that email, we’ve sent a reset link. It expires in 48 hours and can be used once. Check your inbox (and spam).</div>
        <p class="onb-expire"><a href="<?= e(base_url('portal/login')) ?>">Back to sign in</a></p>

      <?php else: ?>
        <header class="atv-enq__head">
          <h1>Reset your password</h1>
          <p class="atv-enq__intro">Enter your account email and we’ll send you a link to set a new password.</p>
        </header>
        <?php if ($fpError !== null): ?><div class="atv-enq__alert" role="alert"><?= e($fpError) ?></div><?php endif; ?>
        <form method="post" action="<?= e(base_url('forgot-password')) ?>" novalidate>
          <?php csrf_field(); ?>
          <div class="atv-field">
            <label for="fp-email">Email</label>
            <input type="email" id="fp-email" name="email" required autocomplete="username" autofocus>
          </div>
          <?php if (turnstile_field() !== ''): ?><div class="atv-field"><?= turnstile_field() ?></div><?php endif; ?>
          <div class="atv-enq-nav">
            <button type="submit" class="atv-btn">Send Reset Link</button>
          </div>
        </form>
        <p class="onb-expire"><a href="<?= e(base_url('portal/login')) ?>">Back to sign in</a></p>
      <?php endif; ?>

    </div>
  </div>
</section>
<?php if ($fpMode === 'request' && turnstile_script_tag() !== '') { echo turnstile_script_tag(); } ?>

<?php
declare(strict_types=1);

/**
 * PU-B — password RESET card + token-error states. Renders by $rpMode: form |
 * expired | used | invalid. Built to docs/atv-portal-reset-password-preview.html.
 * Expects $rpMode, ?$rpUser, ?$rpError, string $rpToken. Escapes everything; no
 * inline styles/scripts (CSP self-only).
 */
/** @var string $rpMode @var ?array $rpUser @var ?string $rpError @var string $rpToken */
$rpError    = $rpError ?? null;
$requestUrl = base_url('forgot-password');
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card admin-login onb-card">

      <?php if ($rpMode === 'form' && $rpUser !== null): ?>
        <header class="atv-enq__head">
          <h1>Choose a new password</h1>
          <p class="atv-enq__intro">for <?= e((string)$rpUser['email']) ?></p>
        </header>
        <?php if ($rpError !== null): ?><div class="atv-enq__alert" role="alert"><?= e($rpError) ?></div><?php endif; ?>
        <form method="post" action="<?= e(base_url('reset-password')) ?>" novalidate data-pw-form>
          <?php csrf_field(); ?>
          <input type="hidden" name="token" value="<?= e($rpToken) ?>">
          <div class="atv-field">
            <label for="rp-pw">New password <button type="button" class="onb-toggle" data-pw-toggle="rp-pw">show</button></label>
            <input type="password" id="rp-pw" name="password" required autocomplete="new-password" minlength="10">
          </div>
          <div class="atv-field">
            <label for="rp-pw2">Confirm password</label>
            <input type="password" id="rp-pw2" name="password_confirm" required autocomplete="new-password" minlength="10">
          </div>
          <p class="lead-hint">At least 10 characters. A longer passphrase is recommended. Don’t reuse a password from another site.</p>
          <?php if (turnstile_field() !== ''): ?><div class="onb-turnstile"><?= turnstile_field() ?></div><?php endif; ?>
          <div class="atv-enq-nav">
            <button type="submit" class="atv-btn">Set New Password &amp; Sign In</button>
          </div>
        </form>
        <p class="onb-expire">This link expires in 48 hours and can be used once.</p>

      <?php elseif ($rpMode === 'expired'): ?>
        <header class="atv-enq__head"><h1>This link has expired</h1></header>
        <p class="atv-enq__intro">Reset links are valid for a limited time. <a href="<?= e($requestUrl) ?>">Request a new link</a>.</p>

      <?php elseif ($rpMode === 'used'): ?>
        <header class="atv-enq__head"><h1>This link has already been used</h1></header>
        <p class="atv-enq__intro">If you’ve reset your password, <a href="<?= e(base_url('portal/login')) ?>">sign in</a>. Otherwise <a href="<?= e($requestUrl) ?>">request a new link</a>.</p>

      <?php else: /* invalid */ ?>
        <header class="atv-enq__head"><h1>This link is invalid</h1></header>
        <p class="atv-enq__intro">Please <a href="<?= e($requestUrl) ?>">request a new link</a> or contact All The Venues support.</p>
      <?php endif; ?>

    </div>
  </div>
</section>
<?php if ($rpMode === 'form' && turnstile_script_tag() !== '') { echo turnstile_script_tag(); } ?>

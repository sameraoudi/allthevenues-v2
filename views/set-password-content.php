<?php
declare(strict_types=1);

/**
 * #3 U-P9a — Public set-password card + token-error states + request-a-link form.
 * Renders by $spMode: form | expired | used | invalid | request | requested.
 * Expects $spMode, ?$spUser, ?$spError, string $spToken. Escapes everything; no
 * inline styles/scripts (CSP self-only). Copy matches the approved design lock.
 */
/** @var string $spMode @var ?array $spUser @var ?string $spError @var string $spToken */
$spError = $spError ?? null;
$requestUrl = base_url('set-password/request');
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card admin-login onb-card">

      <?php if ($spMode === 'form' && $spUser !== null): ?>
        <header class="atv-enq__head">
          <h1>Set your password</h1>
          <p class="atv-enq__intro">for <?= e((string)$spUser['email']) ?><?= !empty($spUser['provider_name']) ? ' · ' . e((string)$spUser['provider_name']) : '' ?></p>
        </header>
        <?php if ($spError !== null): ?><div class="atv-enq__alert" role="alert"><?= e($spError) ?></div><?php endif; ?>
        <form method="post" action="<?= e(base_url('set-password')) ?>" novalidate data-pw-form>
          <?php csrf_field(); ?>
          <input type="hidden" name="token" value="<?= e($spToken) ?>">
          <div class="atv-field">
            <label for="sp-pw">New password <button type="button" class="onb-toggle" data-pw-toggle="sp-pw">Show</button></label>
            <input type="password" id="sp-pw" name="password" required autocomplete="new-password" minlength="10">
          </div>
          <div class="atv-field">
            <label for="sp-pw2">Confirm password</label>
            <input type="password" id="sp-pw2" name="password_confirm" required autocomplete="new-password" minlength="10">
          </div>
          <p class="lead-hint">Use at least 10 characters. A longer passphrase is recommended. Don’t reuse a password from another site.</p>
          <?php if (turnstile_field() !== ''): ?><div class="onb-turnstile"><?= turnstile_field() ?></div><?php endif; ?>
          <div class="atv-enq-nav">
            <button type="submit" class="atv-btn">Set Password &amp; Sign In</button>
          </div>
        </form>
        <p class="onb-expire">This link expires in 48 hours and can be used once.</p>

      <?php elseif ($spMode === 'expired'): ?>
        <header class="atv-enq__head"><h1>This link has expired</h1></header>
        <p class="atv-enq__intro">For your security, password-setup links can only be used for a limited time. <a href="<?= e($requestUrl) ?>">Request a new link</a> or contact All The Venues support.</p>

      <?php elseif ($spMode === 'used'): ?>
        <header class="atv-enq__head"><h1>This link has already been used</h1></header>
        <p class="atv-enq__intro">If you’ve already set your password, please <a href="<?= e(base_url('portal/login')) ?>">sign in</a>. If you need help, <a href="<?= e($requestUrl) ?>">request a new password link</a>.</p>

      <?php elseif ($spMode === 'request'): ?>
        <header class="atv-enq__head">
          <h1>Request a new link</h1>
          <p class="atv-enq__intro">Enter your account email and we’ll send a fresh set-up link if an account is waiting.</p>
        </header>
        <form method="post" action="<?= e($requestUrl) ?>" novalidate>
          <?php csrf_field(); ?>
          <div class="atv-field">
            <label for="sp-email">Email</label>
            <input type="email" id="sp-email" name="email" required autocomplete="username" autofocus>
          </div>
          <div class="atv-enq-nav">
            <button type="submit" class="atv-btn">Send Link</button>
          </div>
        </form>

      <?php elseif ($spMode === 'requested'): ?>
        <header class="atv-enq__head"><h1>Check your email</h1></header>
        <p class="atv-enq__intro">If an account exists for that email and still needs a password, we’ve emailed a new set-up link. If you don’t receive it, contact All The Venues support.</p>

      <?php else: /* invalid */ ?>
        <header class="atv-enq__head"><h1>This link is invalid</h1></header>
        <p class="atv-enq__intro">Please <a href="<?= e($requestUrl) ?>">request a new link</a> or contact All The Venues support.</p>
      <?php endif; ?>

    </div>
  </div>
</section>
<?php if ($spMode === 'form' && turnstile_script_tag() !== '') { echo turnstile_script_tag(); } ?>

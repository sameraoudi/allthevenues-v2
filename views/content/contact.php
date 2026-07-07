<?php
declare(strict_types=1);

/**
 * Contact form (#7b). Expects: $errors, $formError, $old, $reasons.
 * Reuses the shared form styling (.atv-bvp-* + .atv-field). No new page design.
 */
$val   = static fn(string $k) => e((string)($old[$k] ?? ''));
$has   = static fn(string $k) => isset($errors[$k]);
$eline = static function (string $k) use ($errors) {
    return isset($errors[$k]) ? '<p class="atv-bvp-err" role="alert">' . e($errors[$k]) . '</p>' : '';
};
$reasons = $reasons ?? [];
?>
<section class="atv-bvp-hero">
  <div class="atv-bvp-hero__bg" aria-hidden="true"></div>
  <div class="atv-wrap atv-bvp-hero__wrap">
    <p class="atv-bvp-hero__eyebrow">We're here to help</p>
    <h1 class="atv-bvp-hero__title">Contact us</h1>
    <p class="atv-bvp-hero__lede">Have a question about a venue, your enquiry, listing your venues, or something
      else? Send us a message and our team will get back to you.</p>
  </div>
</section>

<div class="atv-wrap atv-bvp-body">
  <section class="atv-bvp-formsec atv-contact-formsec">
    <div class="atv-bvp-formwrap atv-contact-formwrap">
      <p class="atv-bvp-sub">Fields marked <span class="atv-bvp-req">*</span> are required.</p>

      <?php if (!empty($formError)): ?>
        <div class="atv-bvp-formerr" role="alert"><?= e($formError) ?></div>
      <?php endif; ?>

      <form class="atv-bvp-grid" method="post" action="<?= e(base_url('contact')) ?>" novalidate>
        <?php csrf_field(); ?>
        <input type="hidden" name="source_page" value="<?= e($old['source_page'] ?? '/contact') ?>">
        <div class="atv-hp" aria-hidden="true">
          <label>Fax<input type="text" name="contact_fax" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="atv-field atv-field--full">
          <label for="c-reason">Reason for contact <span class="atv-bvp-req">*</span></label>
          <select id="c-reason" name="reason"<?= $has('reason') ? ' aria-invalid="true"' : '' ?>>
            <option value="">Select a reason</option>
            <?php foreach ($reasons as $r): ?>
              <option value="<?= e($r) ?>"<?= (($old['reason'] ?? '') === $r) ? ' selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $eline('reason') ?>
        </div>

        <div class="atv-field">
          <label for="c-name">Name <span class="atv-bvp-req">*</span></label>
          <input type="text" id="c-name" name="name" maxlength="255" value="<?= $val('name') ?>"
                 placeholder="Your name"<?= $has('name') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('name') ?>
        </div>

        <div class="atv-field">
          <label for="c-email">Email <span class="atv-bvp-req">*</span></label>
          <input type="email" id="c-email" name="email" maxlength="255" value="<?= $val('email') ?>"
                 placeholder="you@example.com"<?= $has('email') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('email') ?>
        </div>

        <div class="atv-field atv-field--full">
          <label for="c-phone">Phone <span class="atv-bvp-fine">(optional)</span></label>
          <input type="tel" id="c-phone" name="phone" maxlength="50" value="<?= $val('phone') ?>"
                 placeholder="+971 …">
        </div>

        <div class="atv-field atv-field--full">
          <label for="c-message">Message <span class="atv-bvp-req">*</span></label>
          <textarea id="c-message" name="message" maxlength="5000"
                    placeholder="How can we help?"<?= $has('message') ? ' aria-invalid="true"' : '' ?>><?= $val('message') ?></textarea>
          <?= $eline('message') ?>
        </div>

        <label class="atv-bvp-check<?= $has('consent') ? ' atv-bvp-check--err' : '' ?>">
          <input type="checkbox" name="consent" value="1"<?= (($old['consent'] ?? '') !== '') ? ' checked' : '' ?>>
          <span>I agree to be contacted by All The Venues about this message, and to the
            <a href="<?= e(base_url('terms-of-use')) ?>" target="_blank" rel="noopener">Terms of Use</a> and
            <a href="<?= e(base_url('privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>. <span class="atv-bvp-req">*</span></span>
        </label>
        <?php if ($has('consent')): ?><div class="atv-bvp-full"><?= $eline('consent') ?></div><?php endif; ?>

        <?php if (turnstile_field() !== ''): ?>
          <div class="atv-bvp-full"><?= turnstile_field() ?></div>
        <?php endif; ?>

        <div class="atv-bvp-actions">
          <button type="submit" class="atv-btn">Send Message</button>
          <span class="atv-bvp-fine">We'll never publish your contact details.</span>
        </div>
      </form>
    </div>
  </section>
</div>
<?= turnstile_script_tag() ?>

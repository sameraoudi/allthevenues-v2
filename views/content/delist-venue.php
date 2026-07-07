<?php
declare(strict_types=1);

/**
 * Delist-2 Part C — public "Delist a venue" form. Expects: $errors, $formError,
 * $old, $reasons. Reuses the shared form styling (.atv-bvp-* + .atv-field).
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
    <p class="atv-bvp-hero__eyebrow">For venue operators</p>
    <h1 class="atv-bvp-hero__title">Delist a venue</h1>
    <p class="atv-bvp-hero__lede">Operate a venue that&rsquo;s listed on All The Venues and want it removed? Tell us
      below and our team will verify and take it offline. If you manage the venue in our provider portal, you can
      delist it yourself instantly instead.</p>
  </div>
</section>

<div class="atv-wrap atv-bvp-body">
  <section class="atv-bvp-formsec atv-contact-formsec">
    <div class="atv-bvp-formwrap atv-contact-formwrap">
      <p class="atv-bvp-sub">Fields marked <span class="atv-bvp-req">*</span> are required.</p>

      <?php if (!empty($formError)): ?>
        <div class="atv-bvp-formerr" role="alert"><?= e($formError) ?></div>
      <?php endif; ?>

      <form class="atv-bvp-grid" method="post" action="<?= e(base_url('delist-venue')) ?>" novalidate>
        <?php csrf_field(); ?>
        <div class="atv-hp" aria-hidden="true">
          <label>Fax<input type="text" name="delist_fax" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="atv-field">
          <label for="d-venue">Venue name <span class="atv-bvp-req">*</span></label>
          <input type="text" id="d-venue" name="venue_name" maxlength="255" value="<?= $val('venue_name') ?>"
                 placeholder="The venue as listed"<?= $has('venue_name') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('venue_name') ?>
        </div>

        <div class="atv-field">
          <label for="d-url">Public URL <span class="atv-bvp-fine">(optional)</span></label>
          <input type="text" id="d-url" name="venue_url" maxlength="500" value="<?= $val('venue_url') ?>"
                 placeholder="allthevenues.com/venues/…">
        </div>

        <div class="atv-field">
          <label for="d-name">Your name <span class="atv-bvp-req">*</span></label>
          <input type="text" id="d-name" name="name" maxlength="255" value="<?= $val('name') ?>"
                 placeholder="Your name"<?= $has('name') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('name') ?>
        </div>

        <div class="atv-field">
          <label for="d-email">Work email <span class="atv-bvp-req">*</span></label>
          <input type="email" id="d-email" name="email" maxlength="255" value="<?= $val('email') ?>"
                 placeholder="you@venue.com"<?= $has('email') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('email') ?>
        </div>

        <div class="atv-field">
          <label for="d-role">Your role at the venue <span class="atv-bvp-fine">(optional)</span></label>
          <input type="text" id="d-role" name="role" maxlength="120" value="<?= $val('role') ?>"
                 placeholder="e.g. Owner, General Manager">
        </div>

        <div class="atv-field">
          <label for="d-phone">Phone <span class="atv-bvp-fine">(optional)</span></label>
          <input type="tel" id="d-phone" name="phone" maxlength="50" value="<?= $val('phone') ?>"
                 placeholder="+971 …">
        </div>

        <div class="atv-field atv-field--full">
          <label for="d-reason">Reason <span class="atv-bvp-req">*</span></label>
          <select id="d-reason" name="reason"<?= $has('reason') ? ' aria-invalid="true"' : '' ?>>
            <option value="">Select a reason</option>
            <?php foreach ($reasons as $r): ?>
              <option value="<?= e($r) ?>"<?= (($old['reason'] ?? '') === $r) ? ' selected' : '' ?>><?= e($r) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $eline('reason') ?>
        </div>

        <div class="atv-field atv-field--full">
          <label for="d-proof">Proof of authority <span class="atv-bvp-fine">(optional — a link or note that shows you can act for this venue)</span></label>
          <input type="text" id="d-proof" name="proof" maxlength="500" value="<?= $val('proof') ?>"
                 placeholder="e.g. link to your role on the venue website">
        </div>

        <div class="atv-field atv-field--full">
          <label for="d-message">Anything else <span class="atv-bvp-fine">(optional)</span></label>
          <textarea id="d-message" name="message" maxlength="5000"
                    placeholder="Extra context for our team"><?= $val('message') ?></textarea>
        </div>

        <label class="atv-bvp-check<?= $has('consent') ? ' atv-bvp-check--err' : '' ?>">
          <input type="checkbox" name="consent" value="1"<?= (($old['consent'] ?? '') !== '') ? ' checked' : '' ?>>
          <span>I confirm I am authorised to request this venue&rsquo;s removal and agree to be contacted by All The
            Venues, and to the
            <a href="<?= e(base_url('terms-of-use')) ?>" target="_blank" rel="noopener">Terms of Use</a> and
            <a href="<?= e(base_url('privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>. <span class="atv-bvp-req">*</span></span>
        </label>
        <?php if ($has('consent')): ?><div class="atv-bvp-full"><?= $eline('consent') ?></div><?php endif; ?>

        <?php if (turnstile_field() !== ''): ?>
          <div class="atv-bvp-full"><?= turnstile_field() ?></div>
        <?php endif; ?>

        <div class="atv-bvp-actions">
          <button type="submit" class="atv-btn">Submit delist request</button>
          <span class="atv-bvp-fine">We verify every request before taking a venue offline.</span>
        </div>
      </form>
    </div>
  </section>
</div>
<?= turnstile_script_tag() ?>

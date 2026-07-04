<?php
/**
 * Become a Venue Partner — content view (hero + value cards + provider form).
 * Expects: $errors, $formError, $old, $providerTypes, $locationLabels.
 */
$val  = static fn(string $k) => e((string)($old[$k] ?? ''));
$has  = static fn(string $k) => isset($errors[$k]);
$eline = static function (string $k) use ($errors) {
    if (!isset($errors[$k])) { return ''; }
    return '<p class="atv-bvp-err" role="alert">' . e($errors[$k]) . '</p>';
};
$providerTypes = $providerTypes ?? [];
$locationLabels = $locationLabels ?? [];
?>
<section class="atv-bvp-hero">
  <div class="atv-bvp-hero__bg" aria-hidden="true"></div>
  <div class="atv-wrap atv-bvp-hero__wrap">
    <p class="atv-bvp-hero__eyebrow">For venue providers</p>
    <h1 class="atv-bvp-hero__title">Become a Venue Partner</h1>
    <p class="atv-bvp-hero__lede">Showcase your venues on All The Venues and receive structured event enquiries from
      people planning real events. Tell us about your spaces, and our team will follow up.</p>
  </div>
</section>

<div class="atv-wrap atv-bvp-body">
  <section class="atv-bvp-vals">
    <div class="atv-bvp-val">
      <?= icon('inbox', 'atv-bvp-val__icon') ?>
      <h3>Qualified leads</h3>
      <p>Receive event enquiries with the details that matter: date, guest count, budget, and event type.</p>
    </div>
    <div class="atv-bvp-val">
      <?= icon('shield', 'atv-bvp-val__icon') ?>
      <h3>Managed &amp; private</h3>
      <p>Enquiries are routed through ATV, so your direct contact details stay private.</p>
    </div>
    <div class="atv-bvp-val">
      <?= icon('star', 'atv-bvp-val__icon') ?>
      <h3>Premium visibility</h3>
      <p>Appear in a curated UAE venue platform, with Verified and Featured options available.</p>
    </div>
  </section>

  <section class="atv-bvp-formsec">
    <div class="atv-bvp-formwrap">
      <h2>Tell us about your venues</h2>
      <p class="atv-bvp-sub">Share a few details about your venues, and our team will follow up.
        Fields marked <span class="atv-bvp-req">*</span> are required.</p>

      <?php if (!empty($formError)): ?>
        <div class="atv-bvp-formerr" role="alert"><?= e($formError) ?></div>
      <?php endif; ?>

      <form class="atv-bvp-grid" method="post" action="<?= e(base_url('become-a-venue-partner')) ?>" novalidate>
        <?php csrf_field(); ?>
        <input type="hidden" name="source_page" value="<?= e($old['source_page'] ?? '/become-a-venue-partner') ?>">
        <!-- honeypot: real website field exists, so this spam-trap uses a different name -->
        <div class="atv-hp" aria-hidden="true">
          <label>Fax<input type="text" name="contact_fax" tabindex="-1" autocomplete="off"></label>
        </div>

        <div class="atv-field">
          <label for="bvp-company">Organization / venue provider name <span class="atv-bvp-req">*</span></label>
          <input type="text" id="bvp-company" name="company" maxlength="255" value="<?= $val('company') ?>"
                 placeholder="e.g. Pearl Hospitality Group"<?= $has('company') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('company') ?>
        </div>

        <div class="atv-field">
          <label for="bvp-name">Contact person <span class="atv-bvp-req">*</span></label>
          <input type="text" id="bvp-name" name="name" maxlength="255" value="<?= $val('name') ?>"
                 placeholder="Your name"<?= $has('name') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('name') ?>
        </div>

        <div class="atv-field">
          <label for="bvp-email">Email <span class="atv-bvp-req">*</span></label>
          <input type="email" id="bvp-email" name="email" maxlength="255" value="<?= $val('email') ?>"
                 placeholder="you@company.com"<?= $has('email') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('email') ?>
        </div>

        <div class="atv-field">
          <label for="bvp-phone">Phone / WhatsApp <span class="atv-bvp-req">*</span></label>
          <input type="tel" id="bvp-phone" name="phone" maxlength="50" value="<?= $val('phone') ?>"
                 placeholder="+971 …"<?= $has('phone') ? ' aria-invalid="true"' : '' ?>>
          <?= $eline('phone') ?>
        </div>

        <div class="atv-field">
          <label for="bvp-website">Website</label>
          <input type="url" id="bvp-website" name="website" maxlength="255" value="<?= $val('website') ?>"
                 placeholder="https://…">
        </div>

        <div class="atv-field">
          <label for="bvp-ptype">Provider type <span class="atv-bvp-req">*</span></label>
          <select id="bvp-ptype" name="provider_type"<?= $has('provider_type') ? ' aria-invalid="true"' : '' ?>>
            <option value="">Select type</option>
            <?php foreach ($providerTypes as $pt): ?>
              <option value="<?= e($pt) ?>"<?= (($old['provider_type'] ?? '') === $pt) ? ' selected' : '' ?>><?= e($pt) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $eline('provider_type') ?>
        </div>

        <div class="atv-field">
          <label for="bvp-loc">Primary location</label>
          <select id="bvp-loc" name="city_pref">
            <option value="">Select emirate</option>
            <?php foreach ($locationLabels as $loc): ?>
              <option value="<?= e($loc) ?>"<?= (($old['city_pref'] ?? '') === $loc) ? ' selected' : '' ?>><?= e($loc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="atv-field">
          <label for="bvp-vm">Number of venues managed</label>
          <input type="number" id="bvp-vm" name="venues_managed" min="0" value="<?= $val('venues_managed') ?>"
                 placeholder="e.g. 3">
        </div>

        <div class="atv-field atv-field--full">
          <label for="bvp-notes">Message / notes</label>
          <textarea id="bvp-notes" name="notes" maxlength="5000"
                    placeholder="Tell us about your venues, capacities and what you're looking for."><?= $val('notes') ?></textarea>
        </div>

        <label class="atv-bvp-check<?= $has('consent') ? ' atv-bvp-check--err' : '' ?>">
          <input type="checkbox" name="consent" value="1"<?= (($old['consent'] ?? '') !== '') ? ' checked' : '' ?>>
          <span>I agree to be contacted by All The Venues about partnership, and to the
            <a href="<?= e(base_url('venue-provider-terms')) ?>" target="_blank" rel="noopener">Venue Provider Terms</a> and
            <a href="<?= e(base_url('privacy-policy')) ?>" target="_blank" rel="noopener">Privacy Policy</a>. <span class="atv-bvp-req">*</span></span>
        </label>
        <?php if ($has('consent')): ?><div class="atv-bvp-full"><?= $eline('consent') ?></div><?php endif; ?>

        <?php if (turnstile_field() !== ''): ?>
          <div class="atv-bvp-full"><?= turnstile_field() ?></div>
        <?php endif; ?>

        <div class="atv-bvp-actions">
          <button type="submit" class="atv-btn">Send partner request</button>
          <span class="atv-bvp-fine">We'll never publish your contact details.</span>
        </div>
      </form>
    </div>
  </section>
</div>
<?= turnstile_script_tag() ?>

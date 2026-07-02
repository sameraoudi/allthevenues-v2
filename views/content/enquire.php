<?php
declare(strict_types=1);

/**
 * Enquiry form content — 3 steps (Event details → Requirements → Your details).
 * All fields are in the DOM; app.js enhances it into a stepper. Without JS it
 * is one scrollable form. Expects in scope:
 *   array $context, $eventTypes, $emirates, $errors, $old; ?string $formError.
 */

/** @var array $context @var array $eventTypes @var array $emirates */
/** @var array $errors @var array $old @var ?string $formError */

require_once __DIR__ . '/../../lib/enquiry.php';
require_once __DIR__ . '/../../lib/turnstile.php';

$old  = $old ?? [];
$val  = static fn(string $k, string $d = ''): string => e((string)($old[$k] ?? $d));
$has  = static fn(string $k): bool => isset($errors[$k]);
$errline = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) {
        echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
    }
};
$selected = static fn(string $k, string $v): string => ((string)($old[$k] ?? '') === $v) ? ' selected' : '';

$mode   = $context['mode'];
$venues = $context['venues'];

// Contextual heading/intro per mode.
$intro = 'Tell us about your event and we\'ll connect you with the right venues.';
if ($mode === 'single' && $venues) {
    $intro = 'Enquiring about ' . e($venues[0]['name']) . '.';
} elseif ($mode === 'multi' && $venues) {
    $intro = 'Enquiring about ' . count($venues) . ' shortlisted venues.';
} elseif ($mode === 'assisted') {
    $intro = 'Not sure where to start? Share your event details and our team will help you find the right venue.';
} elseif ($mode === 'partner') {
    $intro = 'Interested in listing your venue? Tell us about your event needs or partnership below and we\'ll be in touch.';
}
$hasErrors = !empty($errors) || $formError !== null;
?>
<section class="atv-enq">
  <div class="atv-wrap atv-enq__wrap">
    <div class="atv-enq__card">
      <header class="atv-enq__head">
        <h1>Send an enquiry</h1>
        <p class="atv-enq__intro"><?= $intro /* pre-escaped above */ ?></p>
      </header>

      <?php if ($venues): ?>
        <div class="atv-enq__venues">
          <?php foreach ($venues as $v): ?>
            <span class="atv-chip"><?= e($v['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($formError !== null): ?>
        <div class="atv-enq__alert" role="alert"><?= e($formError) ?></div>
      <?php endif; ?>

      <!-- Progress indicator (meaningful with JS; harmless without) -->
      <ol class="atv-enq__progress" data-enq-progress aria-hidden="true">
        <li class="is-active"><span>1</span> Event details</li>
        <li><span>2</span> Requirements</li>
        <li><span>3</span> Your details</li>
      </ol>

      <form class="atv-enq__form" method="post" action="<?= e(base_url('enquire')) ?>"
            data-enq-form<?= $hasErrors ? ' data-enq-errors="1"' : '' ?> novalidate>
        <?php csrf_field(); ?>
        <input type="hidden" name="mode" value="<?= e($mode) ?>">
        <input type="hidden" name="source_page" value="<?= $val('source_page', e((string)($_SERVER['REQUEST_URI'] ?? '/enquire'))) ?>">
        <?php foreach ($context['venue_ids'] as $vid): ?>
          <input type="hidden" name="venue_ids[]" value="<?= e((string)$vid) ?>">
        <?php endforeach; ?>

        <!-- Honeypot (do not fill) -->
        <div class="atv-hp" aria-hidden="true">
          <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        </div>

        <!-- Step 1: Event details -->
        <fieldset class="atv-enq-step is-active" data-step="1">
          <legend>Event details</legend>
          <div class="atv-enq-grid">
            <div class="atv-field">
              <label for="f-event">Event type <span class="req">*</span></label>
              <select id="f-event" name="event_type" class="<?= $has('event_type') ? 'is-invalid' : '' ?>" required>
                <option value="">Select event type</option>
                <?php foreach ($eventTypes as $et): ?>
                  <option value="<?= e((string)$et['id']) ?>"<?= $selected('event_type', (string)$et['id']) ?>><?= e($et['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <?php $errline('event_type'); ?>
            </div>
            <div class="atv-field">
              <label for="f-date">Preferred date</label>
              <input type="date" id="f-date" name="event_date" value="<?= $val('event_date') ?>"
                     min="<?= e(date('Y-m-d')) ?>" class="<?= $has('event_date') ? 'is-invalid' : '' ?>">
              <?php $errline('event_date'); ?>
            </div>
            <div class="atv-field">
              <label for="f-flex">Date flexibility</label>
              <select id="f-flex" name="date_flexibility">
                <option value="">Select…</option>
                <?php foreach (enquiry_date_flexibility() as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $selected('date_flexibility', $k) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="atv-field">
              <label for="f-emirate">Location</label>
              <select id="f-emirate" name="emirate">
                <option value="">Any emirate</option>
                <?php foreach ($emirates as $em): ?>
                  <option value="<?= e((string)$em['id']) ?>"<?= $selected('emirate', (string)$em['id']) ?>><?= e($em['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="atv-field">
              <label for="f-guests">Guest count</label>
              <select id="f-guests" name="guest_count">
                <option value="">Any number</option>
                <?php foreach (venue_guest_bands() as $k => [$label, $min]): ?>
                  <option value="<?= e($k) ?>"<?= $selected('guest_count', $k) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="atv-field">
              <label for="f-budget">Budget</label>
              <select id="f-budget" name="budget_range">
                <option value="">Any budget</option>
                <?php foreach (venue_pricing_levels() as $pl): ?>
                  <option value="<?= e($pl) ?>"<?= $selected('budget_range', $pl) ?>><?= e($pl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </fieldset>

        <!-- Step 2: Requirements -->
        <fieldset class="atv-enq-step" data-step="2">
          <legend>Requirements</legend>
          <div class="atv-enq-grid">
            <div class="atv-field">
              <label for="f-pref">Venue preference</label>
              <input type="text" id="f-pref" name="venue_preference" value="<?= $val('venue_preference') ?>"
                     placeholder="e.g. sea view, private terrace" maxlength="255">
            </div>
            <div class="atv-field">
              <label for="f-io">Indoor / outdoor</label>
              <select id="f-io" name="indoor_outdoor">
                <option value="">No preference</option>
                <?php foreach (venue_indoor_outdoor_options() as $k => $label): ?>
                  <option value="<?= e($k) ?>"<?= $selected('indoor_outdoor', $k) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="atv-field atv-field--full">
              <label for="f-fb">Food &amp; beverage requirements</label>
              <textarea id="f-fb" name="fb_requirements" rows="2" maxlength="2000"><?= $val('fb_requirements') ?></textarea>
            </div>
            <div class="atv-field atv-field--full">
              <label for="f-av">AV &amp; technical requirements</label>
              <textarea id="f-av" name="av_requirements" rows="2" maxlength="2000"><?= $val('av_requirements') ?></textarea>
            </div>
            <div class="atv-field atv-field--full">
              <label for="f-notes">Anything else?</label>
              <textarea id="f-notes" name="notes" rows="3" maxlength="4000"><?= $val('notes') ?></textarea>
            </div>
          </div>
        </fieldset>

        <!-- Step 3: Your details -->
        <fieldset class="atv-enq-step" data-step="3">
          <legend>Your details</legend>
          <div class="atv-enq-grid">
            <div class="atv-field">
              <label for="f-name">Name <span class="req">*</span></label>
              <input type="text" id="f-name" name="name" value="<?= $val('name') ?>"
                     class="<?= $has('name') ? 'is-invalid' : '' ?>" required maxlength="255" autocomplete="name">
              <?php $errline('name'); ?>
            </div>
            <div class="atv-field">
              <label for="f-email">Email <span class="req">*</span></label>
              <input type="email" id="f-email" name="email" value="<?= $val('email') ?>"
                     class="<?= $has('email') ? 'is-invalid' : '' ?>" required maxlength="255" autocomplete="email">
              <?php $errline('email'); ?>
            </div>
            <div class="atv-field">
              <label for="f-phone">Phone <span class="req">*</span></label>
              <input type="tel" id="f-phone" name="phone" value="<?= $val('phone') ?>"
                     class="<?= $has('phone') ? 'is-invalid' : '' ?>" required maxlength="50" autocomplete="tel">
              <?php $errline('phone'); ?>
            </div>
            <div class="atv-field">
              <label for="f-company">Company <span class="opt">(optional)</span></label>
              <input type="text" id="f-company" name="company" value="<?= $val('company') ?>" maxlength="255" autocomplete="organization">
            </div>
          </div>

          <div class="atv-field atv-enq-consent">
            <label class="atv-check">
              <input type="checkbox" name="consent_to_share" value="1"
                     class="<?= $has('consent_to_share') ? 'is-invalid' : '' ?>" required
                     <?= (($old['consent_to_share'] ?? '') === '1') ? 'checked' : '' ?>>
              <span>I agree to share my details with relevant venue partners. <span class="req">*</span></span>
            </label>
            <?php $errline('consent_to_share'); ?>
          </div>

          <?php if (turnstile_enabled()): ?>
            <div class="atv-field"><?= turnstile_field() ?></div>
          <?php endif; ?>

          <p class="atv-enq__reassure">Secure and confidential. Your details are only shared with relevant venue partners.</p>
        </fieldset>

        <div class="atv-enq-nav">
          <button type="button" class="atv-btn atv-btn--ghost step-back" data-enq-back>Back</button>
          <button type="button" class="atv-btn step-next" data-enq-next>Next</button>
          <button type="submit" class="atv-btn step-submit">Submit enquiry</button>
        </div>
      </form>
    </div>
  </div>
</section>
<?= turnstile_script_tag() ?>

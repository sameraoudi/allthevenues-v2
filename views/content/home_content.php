<?php
declare(strict_types=1);

/**
 * Homepage content. Expects in scope:
 *   array $eventTypes, $popularTypes, $emirates, $featured.
 * All dynamic output escaped with e(); icons from the inline set.
 */

/** @var array $eventTypes @var array $popularTypes @var array $emirates @var array $featured */
$venuesUrl = base_url('venues');
?>

<!-- Hero + guided search -->
<section class="atv-hero">
  <div class="atv-hero__bg" aria-hidden="true"></div>
  <div class="atv-wrap">
    <h1>Find the right UAE venue for your next event</h1>
    <p class="sub">Discover curated UAE venues, compare key details, and send one structured enquiry.</p>

    <form class="atv-search" method="get" action="<?= e($venuesUrl) ?>">
      <div class="top"><b>Tell us about your event</b></div>
      <div class="atv-fields">
        <div class="atv-field">
          <label for="h-event">Event type</label>
          <select id="h-event" name="event_type">
            <option value="">Select event type</option>
            <?php foreach ($eventTypes as $et): ?>
              <option value="<?= e($et['slug']) ?>"><?= e($et['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="atv-field">
          <label for="h-loc">Location</label>
          <select id="h-loc" name="emirate">
            <option value="">Select location</option>
            <?php foreach ($emirates as $em): ?>
              <option value="<?= e($em['slug']) ?>"><?= e($em['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="atv-field">
          <label for="h-guests">Guest count</label>
          <select id="h-guests" name="guest_count">
            <option value="">Any number</option>
            <?php foreach (venue_guest_bands() as $val => [$label, $min]): ?>
              <option value="<?= e($val) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="atv-field">
          <label for="h-budget">Budget</label>
          <select id="h-budget" name="budget">
            <option value="">Any budget</option>
            <?php foreach (venue_pricing_levels() as $pl): ?>
              <option value="<?= e($pl) ?>"><?= e($pl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="atv-btn">Find venues</button>
      </div>
      <p class="atv-assist">Not sure where to start?
        <a href="<?= e(base_url('enquire') . '?mode=assisted') ?>">Let us help you find a venue.</a>
      </p>
    </form>
  </div>
</section>

<!-- Popular event types -->
<section class="atv-sec">
  <div class="atv-wrap">
    <div class="atv-sec-head">
      <h2>Popular event types</h2>
      <a class="link" href="<?= e($venuesUrl) ?>">View all types</a>
    </div>
    <div class="atv-types">
      <?php foreach ($popularTypes as $et): ?>
        <a class="atv-type" href="<?= e($venuesUrl . query_string(['event_type' => $et['slug']])) ?>">
          <?= icon(event_type_icon((string)$et['slug'])) ?>
          <span><?= e($et['name']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Featured venues -->
<section class="atv-sec atv-sec--tight">
  <div class="atv-wrap">
    <div class="atv-sec-head">
      <h2>Featured venues</h2>
      <a class="link" href="<?= e($venuesUrl) ?>">View all venues</a>
    </div>
    <div class="atv-sub2">Hand-picked spaces for every kind of celebration</div>
    <?php if ($featured): ?>
      <div class="atv-cards">
        <?php foreach ($featured as $venue): ?>
          <?php require __DIR__ . '/../partials/venue-card.php'; ?>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="atv-sub2">Featured venues are coming soon.</p>
    <?php endif; ?>
  </div>
</section>

<!-- How it works -->
<section class="atv-how">
  <div class="atv-wrap">
    <h2>How it works</h2>
    <div class="atv-steps">
      <div class="atv-step">
        <div class="num">1</div>
        <b>Tell us what you're planning</b>
        <p>Share your event type, location, guest count, and budget.</p>
      </div>
      <div class="atv-step">
        <div class="num">2</div>
        <b>Compare suitable venues</b>
        <p>Browse curated matches with capacity, layouts, and key details side by side.</p>
      </div>
      <div class="atv-step">
        <div class="num">3</div>
        <b>Enquire with selected venues</b>
        <p>Send one structured enquiry and hear back from the right venue partners.</p>
      </div>
    </div>
  </div>
</section>

<!-- Trust row -->
<div class="atv-wrap">
  <div class="atv-trust">
    <div class="t">
      <span class="ic"><?= icon('check-circle') ?></span>
      <div><b>Curated &amp; verified</b><p>Venue information is reviewed for quality, clarity, and suitability before publication.</p></div>
    </div>
    <div class="t">
      <span class="ic"><?= icon('info-circle') ?></span>
      <div><b>Accurate information</b><p>Up-to-date capacity, layouts, facilities, and minimum spends.</p></div>
    </div>
    <div class="t">
      <span class="ic"><?= icon('hand-heart') ?></span>
      <div><b>Personal support</b><p>Our team is here to help you find the right venue, for free.</p></div>
    </div>
  </div>
</div>

<!-- Provider CTA band -->
<div class="atv-wrap">
  <div class="atv-band">
    <div>
      <h3>Are you a venue provider?</h3>
      <p>Showcase your venue to event planners and receive structured enquiries through a curated UAE venue platform.</p>
    </div>
    <a class="atv-btn atv-btn--sand" href="<?= e(base_url('enquire') . '?mode=partner') ?>">Become a venue partner</a>
  </div>
</div>

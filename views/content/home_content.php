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
    <p class="sub">Discover beautiful venues and enquire with ease.</p>

    <form class="atv-search" method="get" action="<?= e($venuesUrl) ?>">
      <div class="top"><b>Tell us about your event</b></div>
      <div class="atv-field atv-field--wide">
        <input type="search" id="h-q" name="q" aria-label="Search by venue or venue provider" placeholder="Search by venue or venue provider">
      </div>
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
        <button type="submit" class="atv-btn">Find Venues</button>
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

<!-- Featured Partners — static sister companies (services/products), NOT venue
     providers. Two hardcoded cards; no DB/admin layer. Built to
     docs/atv-featured-partners-preview.html. -->
<?php
$featuredPartners = [
    [
        'name'  => 'Bianca Event Styling',
        'body'  => 'Bianca Event Styling designs elegant event experiences across the UAE, with a focus on weddings, celebrations, and beautifully styled venue transformations.',
        'cta'   => 'Design your celebration',
        'url'   => 'https://www.instagram.com/bianca_events',
        'img'   => 'assets/img/featured-partners/bianca-hero.webp',
        'w'     => 1080,
        'h'     => 593,
        'grad'  => 'fp-card__img--bianca',
    ],
    [
        'name'  => 'Lilac Studio',
        'body'  => 'Lilac Studio curates elegant gifts and stylish pieces with a soft, polished aesthetic, making thoughtful gifting feel more beautiful and memorable.',
        'cta'   => 'Find the perfect gift',
        'url'   => 'https://lilacstudio.ae/',
        'img'   => 'assets/img/featured-partners/lilac-collage.webp',
        'w'     => 1080,
        'h'     => 645,
        'grad'  => 'fp-card__img--lilac',
    ],
];
?>
<section class="atv-sec fp-sec">
  <div class="atv-wrap">
    <div class="fp-head">
      <h2>Featured Partners</h2>
      <p>Trusted partners to make your event effortless &mdash; from styling to thoughtful gifting.</p>
    </div>
    <div class="fp-grid">
      <?php foreach ($featuredPartners as $fp): ?>
        <article class="fp-card">
          <div class="fp-card__img <?= e($fp['grad']) ?>">
            <?php /* Gradient shows through when the file isn't there yet — no broken icon. */ ?>
            <?php if (is_file(app_path($fp['img']))): ?>
              <img src="<?= e(base_url($fp['img'])) ?>" alt="<?= e($fp['name'] . ' — Featured Partner') ?>"
                   loading="lazy" width="<?= (int)$fp['w'] ?>" height="<?= (int)$fp['h'] ?>">
            <?php endif; ?>
            <span class="atv-badge fp-badge">Featured Partner</span>
          </div>
          <div class="fp-card__body">
            <h3><?= e($fp['name']) ?></h3>
            <p><?= e($fp['body']) ?></p>
            <a class="atv-btn fp-cta" href="<?= e($fp['url']) ?>" target="_blank" rel="noopener nofollow"><?= e($fp['cta']) ?> &nearr;</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="fp-foot">Featured Partners are curated collaborators, not venue listings. Interested in partnering? <a href="<?= e(base_url('contact')) ?>">Get in touch</a>.</p>
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
    <a class="atv-btn atv-btn--sand" href="<?= e(base_url('enquire') . '?mode=partner') ?>">Become a Venue Partner</a>
  </div>
</div>

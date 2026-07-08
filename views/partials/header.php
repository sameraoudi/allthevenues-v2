<?php declare(strict_types=1); ?>
<header class="site-header">
  <div class="atv-wrap">
    <a class="brand-lockup" href="<?= e(base_url('/')) ?>" aria-label="All The Venues — home">
      <img class="brand-lockup__icon"
           src="<?= e(base_url('assets/brand/web_exports/icon_light/icon_light_128.webp')) ?>"
           alt="" width="128" height="128" aria-hidden="true">
      <span class="brand-lockup__word brand-lockup__word--full">All The <span class="brand-lockup__accent">Venues</span></span>
      <span class="brand-lockup__word brand-lockup__word--abbr" aria-hidden="true">AT<span class="brand-lockup__accent">V</span></span>
    </a>

    <button class="nav-toggle" type="button" aria-label="Toggle menu"
            data-nav-toggle aria-controls="mainNav" aria-expanded="false">
      <?= icon('menu') ?>
    </button>

    <ul class="main-nav" id="mainNav">
      <li><a href="<?= e(base_url('venues')) ?>">Venues</a></li>
      <li><a href="<?= e(base_url('providers')) ?>">Venue Providers</a></li>
      <li><a href="<?= e(base_url('event-types')) ?>">Event Types</a></li>
      <li><a href="<?= e(base_url('locations')) ?>">Locations</a></li>
      <li><a href="<?= e(base_url('become-a-venue-partner')) ?>">Become a Venue Partner</a></li>
    </ul>

    <div class="right">
      <a class="shortlist" href="<?= e(base_url('shortlist')) ?>"
         data-shortlist-link data-shortlist-base="<?= e(base_url('shortlist')) ?>">
        <?= icon('heart') ?> Shortlist <span class="n" data-shortlist-count>0</span>
      </a>
      <a class="atv-btn atv-btn--sm" href="<?= e(base_url('enquire')) ?>">Enquire Now</a>
    </div>
  </div>
</header>

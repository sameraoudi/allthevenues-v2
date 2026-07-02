<?php declare(strict_types=1); ?>
<header class="site-header">
  <div class="atv-wrap">
    <a class="brand-logo" href="<?= e(base_url('/')) ?>" aria-label="All The Venues — home">
      <img src="<?= e(base_url('assets/brand/web_exports/horizontal_light/horizontal_light_512w.png')) ?>"
           alt="All The Venues" width="512" height="120">
    </a>

    <button class="nav-toggle" type="button" aria-label="Toggle menu"
            data-nav-toggle aria-controls="mainNav" aria-expanded="false">
      <?= icon('menu') ?>
    </button>

    <ul class="main-nav" id="mainNav">
      <li><a href="<?= e(base_url('venues')) ?>">Venues</a></li>
      <li><a href="<?= e(base_url('venues')) ?>">Event types</a></li>
      <li><a href="<?= e(base_url('venues')) ?>">Locations</a></li>
      <li><a href="<?= e(base_url('enquire')) ?>">For partners</a></li>
    </ul>

    <div class="right">
      <a class="shortlist" href="<?= e(base_url('venues')) ?>">
        <?= icon('heart') ?> Shortlist <span class="n">0</span>
      </a>
      <a class="atv-btn atv-btn--sm" href="<?= e(base_url('enquire')) ?>">Enquire now</a>
    </div>
  </div>
</header>

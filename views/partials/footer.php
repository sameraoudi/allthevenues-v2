<?php declare(strict_types=1); ?>
<footer class="site-footer mt-auto">
  <div class="atv-wrap">
    <div class="site-footer__top">
      <div class="site-footer__brand">
        <a class="brand-stacked" href="<?= e(base_url('/')) ?>" aria-label="All The Venues — home">
          <img src="<?= e(base_url('assets/brand/web_exports/primary_stacked_light/primary_stacked_light_176.webp')) ?>"
               alt="All The Venues" width="176" height="207" loading="lazy">
        </a>
        <p>Discover curated UAE venues, compare key details, and send one structured enquiry.</p>
      </div>

      <div class="footer-col">
        <h4>Explore</h4>
        <ul>
          <li><a href="<?= e(base_url('venues')) ?>">Venues</a></li>
          <li><a href="<?= e(base_url('providers')) ?>">Venue Providers</a></li>
          <li><a href="<?= e(base_url('event-types')) ?>">Event Types</a></li>
          <li><a href="<?= e(base_url('locations')) ?>">Locations</a></li>
          <li><a href="#" title="Coming soon">Inspiration</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>For partners</h4>
        <ul>
          <li><a href="<?= e(base_url('become-a-venue-partner')) ?>">Become a Venue Partner</a></li>
          <li><a href="<?= e(base_url('portal/login')) ?>">Partner Portal</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><a href="<?= e(base_url('about')) ?>">About us</a></li>
          <li><a href="<?= e(base_url('contact')) ?>">Contact</a></li>
          <li><a href="<?= e(base_url('delist-venue')) ?>">Delist a Venue</a></li>
          <li><a href="#" title="Coming soon">Blog</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Legal</h4>
        <ul>
          <li><a href="<?= e(base_url('terms-of-use')) ?>">Terms of Use</a></li>
          <li><a href="<?= e(base_url('privacy-policy')) ?>">Privacy Policy</a></li>
          <li><a href="<?= e(base_url('cookie-policy')) ?>">Cookie Notice</a></li>
        </ul>
      </div>
    </div>

    <div class="site-footer__bottom">
      <p class="copy">&copy; 2016&ndash;2026 All The Venues. All rights reserved.</p>
      <div class="soc">
        <a href="https://www.instagram.com/allthevenues/" aria-label="Instagram" rel="noopener noreferrer" target="_blank"><?= icon('instagram') ?></a>
        <a href="https://x.com/AllTheVenues" aria-label="X" rel="noopener noreferrer" target="_blank"><?= icon('x') ?></a>
        <a href="https://www.facebook.com/AllTheVenues/" aria-label="Facebook" rel="noopener noreferrer" target="_blank"><?= icon('facebook') ?></a>
      </div>
    </div>
  </div>
</footer>

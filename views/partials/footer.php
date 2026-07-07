<?php declare(strict_types=1); ?>
<footer class="site-footer mt-auto">
  <div class="atv-wrap">
    <div class="site-footer__top">
      <div class="site-footer__brand">
        <a class="brand-stacked" href="<?= e(base_url('/')) ?>" aria-label="All The Venues — home">
          <img src="<?= e(base_url('assets/brand/web_exports/primary_stacked_light/primary_stacked_light_512w.png')) ?>"
               alt="All The Venues" width="512" height="600">
        </a>
        <p>Discover curated UAE venues, compare key details, and send one structured enquiry.</p>
      </div>

      <div class="footer-col">
        <h4>Explore</h4>
        <ul>
          <li><a href="<?= e(base_url('venues')) ?>">Venues</a></li>
          <li><a href="<?= e(base_url('providers')) ?>">Venue Providers</a></li>
          <li><a href="<?= e(base_url('event-types')) ?>">Event types</a></li>
          <li><a href="<?= e(base_url('locations')) ?>">Locations</a></li>
          <li><a href="#" title="Coming soon">Inspiration</a></li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>For partners</h4>
        <ul>
          <li><a href="<?= e(base_url('become-a-venue-partner')) ?>">Become a Venue Partner</a></li>
          <?php if (function_exists('portal_enabled') && portal_enabled()): ?>
            <li><a href="<?= e(base_url('portal/login')) ?>">Partner login</a></li>
          <?php else: ?>
            <li><a href="#" title="Coming soon">Partner login</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Company</h4>
        <ul>
          <li><a href="<?= e(base_url('about')) ?>">About us</a></li>
          <li><a href="<?= e(base_url('contact')) ?>">Contact</a></li>
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
        <a href="https://www.instagram.com/" aria-label="Instagram" rel="noopener" target="_blank"><?= icon('instagram') ?></a>
        <a href="https://www.facebook.com/" aria-label="Facebook" rel="noopener" target="_blank"><?= icon('facebook') ?></a>
        <a href="https://www.linkedin.com/" aria-label="LinkedIn" rel="noopener" target="_blank"><?= icon('linkedin') ?></a>
      </div>
    </div>
  </div>
</footer>

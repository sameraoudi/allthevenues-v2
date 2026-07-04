<?php declare(strict_types=1); ?>
<section class="atv-404">
  <div class="atv-wrap atv-404__inner">
    <span class="atv-404__icon"><?= icon('map-pin') ?></span>
    <p class="atv-404__code">404</p>
    <h1 class="atv-404__title">This venue seems to have moved.</h1>
    <p class="atv-404__sub">The page you’re looking for is no longer here, but there are plenty of other UAE venues to explore.</p>
    <div class="atv-404__actions">
      <a class="atv-btn" href="<?= e(base_url('venues')) ?>">Browse venues <?= icon('arrow-right') ?></a>
      <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('/')) ?>">Back to home</a>
    </div>
  </div>
</section>

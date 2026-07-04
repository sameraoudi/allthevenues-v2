<?php
declare(strict_types=1);

/**
 * Locations content — editorial mosaic by emirate. Expects $mosaic (ordered
 * slug => [size, descriptor]), $counts (slug => published count), $covers
 * (slug => top venue image|null), $emirateNames (slug => name), $LOC_COUNT_MIN.
 * An emirate renders only with >=1 published venue. Reuses the Event Types
 * mosaic classes (.atv-et-*); icons are inline SVG.
 */
/** @var array $mosaic @var array $counts @var array $covers @var array $emirateNames @var int $LOC_COUNT_MIN */
?>
<div class="atv-wrap">

  <section class="atv-et-intro">
    <div class="atv-et-eyebrow">Browse UAE venues by location</div>
    <h1>Where's your event?</h1>
    <p>Explore venues across the Emirates, from Dubai's waterfront ballrooms and Abu Dhabi's grand halls to beach resorts, mountain retreats, and private coastal settings. Pick a location to see what's available.</p>
    <div class="atv-loc-chips">
      <?php foreach ($mosaic as $slug => $cfg): ?>
        <?php if ((int)($counts[$slug] ?? 0) < 1) { continue; } ?>
        <a href="<?= e(base_url('venues') . query_string(['emirate' => $slug])) ?>"><?= e($emirateNames[$slug] ?? ucwords(str_replace('-', ' ', $slug))) ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="atv-et-mosaic">
    <?php foreach ($mosaic as $slug => [$size, $descriptor]):
        $n = (int)($counts[$slug] ?? 0);
        if ($n < 1) {
            continue;   // gate: an emirate appears only with >=1 published venue
        }
        $name = $emirateNames[$slug] ?? ucwords(str_replace('-', ' ', $slug));
        $href = base_url('venues') . query_string(['emirate' => $slug]);
        $pill = $n >= $LOC_COUNT_MIN ? ($n . ' venue' . ($n === 1 ? '' : 's')) : 'Explore venues';

        // Cover priority: provided city image → top venue photo → gradient + pin.
        $cityRel = 'assets/img/locations/' . $slug . '.webp';
        $imgSrc  = null;
        if (is_file(app_path($cityRel))) {
            $imgSrc = base_url($cityRel);
        } elseif (!empty($covers[$slug]) && is_file(app_path((string)$covers[$slug]))) {
            $imgSrc = base_url((string)$covers[$slug]);
        }
        $grad = abs(crc32($slug)) % 6;   // seeded fallback gradient
    ?>
      <a class="atv-et-tile atv-et-tile--<?= e($size) ?><?= $imgSrc ? '' : ' cover-grad--' . $grad ?>"
         href="<?= e($href) ?>" aria-label="<?= e($name) ?> — browse venues">
        <?php if ($imgSrc !== null): ?>
          <img class="atv-et-tile__img" src="<?= e($imgSrc) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="atv-et-tile__icon"><?= icon('map-pin') ?></span>
        <?php endif; ?>
        <span class="atv-et-tile__scrim"></span>
        <span class="atv-et-tile__count"><?= e($pill) ?></span>
        <div class="atv-et-tile__label">
          <h3><?= e($name) ?></h3>
          <p><?= e($descriptor) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </section>

  <section class="atv-et-cta">
    <div>
      <h2>Not sure which area fits?</h2>
      <p>Tell us about your event and we'll suggest venues in the right location.</p>
    </div>
    <a class="atv-btn atv-btn--sand atv-et-cta__btn" href="<?= e(base_url('enquire')) ?>">Get help finding a venue</a>
  </section>

</div>

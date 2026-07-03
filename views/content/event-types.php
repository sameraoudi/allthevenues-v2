<?php
declare(strict_types=1);

/**
 * Event Types content — editorial mosaic. Expects $mosaic (ordered
 * slug => [size, descriptor]), $counts (slug => published count), $typeNames
 * (slug => name), $ET_COUNT_MIN. A type renders only when it has >=1 published
 * venue. All visuals come from brand.css (.atv-et-*); icons are inline SVG.
 */
/** @var array $mosaic @var array $counts @var array $typeNames @var int $ET_COUNT_MIN */
?>
<div class="atv-wrap">

  <section class="atv-et-intro">
    <div class="atv-et-eyebrow">Find your venue by occasion</div>
    <h1>What are you planning?</h1>
    <p>Start with the kind of event you're hosting — we'll show you the UAE venues suited to it, and connect you to the right people through one simple enquiry.</p>
  </section>

  <div class="atv-et-bridge">
    <p><b>Already know what you need?</b> Share your event details and we'll help match you with suitable venues.</p>
    <a class="atv-btn atv-btn--sand atv-et-bridge__btn" href="<?= e(base_url('enquire')) ?>">Make an enquiry</a>
  </div>

  <section class="atv-et-mosaic">
    <?php foreach ($mosaic as $slug => [$size, $descriptor]):
        $n = (int)($counts[$slug] ?? 0);
        if ($n < 1) {
            continue;   // gate: a type appears only with >=1 published venue
        }
        $name   = $typeNames[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
        $href   = base_url('venues') . query_string(['event_type' => $slug]);
        $rel    = 'assets/img/event-types/' . $slug . '.webp';
        $hasImg = is_file(app_path($rel));
        $grad   = abs(crc32($slug)) % 6;   // seeded fallback gradient
        $pill   = $n >= $ET_COUNT_MIN ? ($n . ' venue' . ($n === 1 ? '' : 's')) : 'Explore venues';
    ?>
      <a class="atv-et-tile atv-et-tile--<?= e($size) ?><?= $hasImg ? '' : ' cover-grad--' . $grad ?>"
         href="<?= e($href) ?>" aria-label="<?= e($name) ?> — browse venues">
        <?php if ($hasImg): ?>
          <img class="atv-et-tile__img" src="<?= e(base_url($rel)) ?>" alt="" loading="lazy">
        <?php else: ?>
          <span class="atv-et-tile__icon"><?= icon(event_type_icon($slug)) ?></span>
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
      <h2>Not sure which fits?</h2>
      <p>Tell us about your event and we'll point you to the right venues and providers.</p>
    </div>
    <a class="atv-btn atv-btn--sand atv-et-cta__btn" href="<?= e(base_url('enquire')) ?>">Get help finding a venue</a>
  </section>

</div>

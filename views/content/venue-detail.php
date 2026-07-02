<?php
declare(strict_types=1);

/**
 * Venue detail content view (Coastal UAE visual pass). Expects:
 *   array $venue, array $images, array $layouts, array $similar.
 *
 * Rich-text fields (description, facilities, food_beverage, av_support,
 * packages, special_offer, restrictions, atv_review) were sanitized at
 * import (U1b) and are rendered AS HTML. Everything else is e()-escaped.
 * map_embed is intentionally NOT rendered (deferred to the maps + CSP unit).
 */
/** @var array $venue @var array $images @var array $layouts @var array $similar */
require_once __DIR__ . '/../../lib/icons.php';

$name       = (string)($venue['name'] ?? 'Venue');
$area       = trim((string)($venue['area'] ?? ''));
$emirate    = trim((string)($venue['emirate_name'] ?? ''));
$address    = trim((string)($venue['address'] ?? ''));
$locShort   = trim(implode(', ', array_filter([$area, $emirate])));
$headAddr   = $address !== '' ? $address : $locShort;
$enquireUrl = base_url('enquire') . query_string(['venue' => (int)$venue['id']]);
$ioLabels   = venue_indoor_outdoor_options();
$ioLabel    = !empty($venue['indoor_outdoor']) ? ($ioLabels[$venue['indoor_outdoor']] ?? $venue['indoor_outdoor']) : '';
$bestForTags = tags_from($venue['best_for'] ?? null, 6);

// Rich-text section helper (sanitized HTML → rendered raw).
$section = static function (string $title, ?string $html): void {
    if (trim((string)$html) === '') return;
    echo '<h3 class="vd-sub">' . e($title) . '</h3>'
       . '<div class="venue-richtext">' . $html /* pre-sanitized */ . '</div>';
};

// Which tabs have content.
$hasFacilities = trim((string)($venue['facilities'] ?? '')) !== ''
    || trim((string)($venue['food_beverage'] ?? '')) !== ''
    || trim((string)($venue['av_support'] ?? '')) !== '';
$hasPackages = trim((string)($venue['packages'] ?? '')) !== ''
    || trim((string)($venue['special_offer'] ?? '')) !== ''
    || trim((string)($venue['restrictions'] ?? '')) !== '';
$hasLocation = ($address !== '' || $locShort !== '');

$tabs = ['overview' => 'Overview'];
if ($layouts)       $tabs['layouts']    = 'Layouts & Capacity';
if ($hasFacilities) $tabs['facilities'] = 'Facilities';
if ($hasPackages)   $tabs['packages']   = 'Packages';
if ($hasLocation)   $tabs['location']   = 'Location';

// Gallery images (primary first already).
$mainImg = venue_img_src($images[0]['file_path'] ?? null);
$mainAlt = (string)($images[0]['alt_text'] ?? $name);
$thumbs  = array_slice($images, 1, 2);
$more    = max(0, count($images) - 3);
?>
<div class="atv-wrap">
  <div class="vd-top">
    <div class="venues-crumb">
      <a href="<?= e(base_url('/')) ?>">Home</a> &rsaquo;
      <a href="<?= e(base_url('venues')) ?>">Venues</a> &rsaquo; <b><?= e($name) ?></b>
    </div>
    <div class="vd-top__acts">
      <button type="button" class="atv-btn atv-btn--sm atv-btn--ghost" aria-label="Add to shortlist"><?= icon('heart') ?> Shortlist</button>
      <a class="atv-btn atv-btn--sm" href="<?= e($enquireUrl) ?>">Enquire now</a>
    </div>
  </div>

  <!-- Gallery -->
  <div class="vd-gallery" data-gallery>
    <div class="vd-gallery__main">
      <img id="vdMain" src="<?= e($mainImg) ?>" alt="<?= e($mainAlt) ?>">
      <?php if (!empty($venue['is_featured'])): ?><span class="atv-badge vd-gallery__badge">Featured</span><?php endif; ?>
    </div>
    <?php if ($thumbs): ?>
      <div class="vd-gallery__col">
        <?php foreach ($thumbs as $i => $t): $src = venue_img_src($t['file_path'] ?? null); ?>
          <button type="button" class="vd-gallery__thumb" data-full="<?= e($src) ?>" aria-label="View image">
            <img src="<?= e($src) ?>" alt="<?= e($t['alt_text'] ?? $name) ?>">
            <?php if ($i === array_key_last($thumbs) && $more > 0): ?><span class="vd-gallery__more">+<?= e((string)$more) ?> more</span><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Title -->
  <div class="vd-head">
    <div>
      <h1><?= e($name) ?></h1>
      <?php if ($headAddr !== ''): ?><div class="vd-head__addr"><?= icon('building') ?> <?= e($headAddr) ?></div><?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <nav class="vd-tabs" data-tabs aria-label="Venue sections">
    <?php $first = true; foreach ($tabs as $key => $label): ?>
      <a class="vd-tab<?= $first ? ' is-active' : '' ?>" href="#tab-<?= e($key) ?>" data-tab="<?= e($key) ?>"><?= e($label) ?></a>
      <?php $first = false; endforeach; ?>
  </nav>

  <div class="vd-body">
    <div class="vd-content">
      <!-- Overview -->
      <section class="vd-panel is-active" data-tab-panel="overview" id="tab-overview">
        <?php if (trim((string)$venue['description']) !== ''): ?>
          <h2>About this venue</h2>
          <div class="venue-richtext"><?= $venue['description'] /* sanitized */ ?></div>
        <?php endif; ?>
        <?php if ($bestForTags): ?>
          <h2>What makes it special</h2>
          <div class="vd-highlights">
            <?php foreach ($bestForTags as $t): ?>
              <div><?= icon('check-circle') ?> <?= e($t) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (trim((string)($venue['atv_review'] ?? '')) !== ''): ?>
          <h2>Our take</h2>
          <div class="venue-richtext"><?= $venue['atv_review'] /* sanitized */ ?></div>
        <?php endif; ?>
      </section>

      <!-- Layouts & Capacity -->
      <?php if ($layouts): ?>
        <section class="vd-panel" data-tab-panel="layouts" id="tab-layouts">
          <h2>Layouts &amp; capacity</h2>
          <table class="vd-layouts">
            <thead><tr><th>Layout</th><th class="ta-r">Capacity</th></tr></thead>
            <tbody>
              <?php foreach ($layouts as $l): ?>
                <tr><td><?= e($l['layout_type']) ?></td><td class="ta-r"><?= e(number_format((int)$l['capacity'])) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      <?php endif; ?>

      <!-- Facilities -->
      <?php if ($hasFacilities): ?>
        <section class="vd-panel" data-tab-panel="facilities" id="tab-facilities">
          <h2>Facilities</h2>
          <?php $section('Facilities', $venue['facilities'] ?? ''); ?>
          <?php $section('Food &amp; beverage', $venue['food_beverage'] ?? ''); ?>
          <?php $section('AV &amp; technical', $venue['av_support'] ?? ''); ?>
        </section>
      <?php endif; ?>

      <!-- Packages -->
      <?php if ($hasPackages): ?>
        <section class="vd-panel" data-tab-panel="packages" id="tab-packages">
          <h2>Packages</h2>
          <?php $section('Packages', $venue['packages'] ?? ''); ?>
          <?php $section('Special offer', $venue['special_offer'] ?? ''); ?>
          <?php $section('Notes &amp; restrictions', $venue['restrictions'] ?? ''); ?>
        </section>
      <?php endif; ?>

      <!-- Location -->
      <?php if ($hasLocation): ?>
        <section class="vd-panel" data-tab-panel="location" id="tab-location">
          <h2>Location</h2>
          <?php if ($address !== ''): ?><p class="vd-loc"><?= e($address) ?></p><?php endif; ?>
          <?php if ($locShort !== ''): ?><p class="vd-loc text-muted"><?= e($locShort) ?></p><?php endif; ?>
        </section>
      <?php endif; ?>
    </div>

    <!-- Key info + enquire -->
    <aside class="vd-side">
      <div class="vd-keypanel">
        <h4>Key information</h4>
        <?php
          $krow = static function (string $k, ?string $v): void {
              if (trim((string)$v) === '') return;
              echo '<div class="vd-krow"><span class="k">' . e($k) . '</span><span class="v">' . e($v) . '</span></div>';
          };
          $krow('Venue type', $venue['venue_type_name'] ?? '');
          $krow('Best for', $bestForTags ? implode(', ', $bestForTags) : '');
          $krow('Guest count', ($venue['capacity_max'] ?? null) !== null ? 'Up to ' . number_format((int)$venue['capacity_max']) : '');
          $krow('Minimum guests', ($venue['capacity_min'] ?? null) ? number_format((int)$venue['capacity_min']) : '');
          $krow('Indoor / outdoor', $ioLabel);
          $krow('Location', $locShort);
          $krow('Pricing', $venue['pricing_level'] ?? '');
          $krow('Minimum spend', ($venue['minimum_spend'] ?? null) && (float)$venue['minimum_spend'] > 0 ? 'AED ' . number_format((float)$venue['minimum_spend']) : '');
          $krow('Managed by', $venue['partner_name'] ?? '');
        ?>
      </div>
      <div class="vd-enqbar">
        <h4>Interested in this venue?</h4>
        <p>Share your event details once, and we'll help connect you with the right contact.</p>
        <a class="atv-btn atv-btn--sand" href="<?= e($enquireUrl) ?>">Enquire about this venue</a>
      </div>
    </aside>
  </div>

  <!-- Similar venues -->
  <?php if ($similar): ?>
    <section class="vd-similar">
      <h2>Similar venues</h2>
      <div class="atv-sub2">You might also consider</div>
      <div class="atv-cards">
        <?php foreach ($similar as $venue): /* reuse card; $venue reassigned intentionally */ ?>
          <?php require __DIR__ . '/../partials/venue-card.php'; ?>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>

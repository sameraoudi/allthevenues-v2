<?php
declare(strict_types=1);

/**
 * Provider detail content. Expects $partner, array $partnerVenues, array $bestFor.
 * about is sanitized HTML (rendered raw); everything else e()-escaped.
 * NEVER renders email/phone — enquiries route through ATV.
 */
/** @var array $partner @var array $partnerVenues @var array $bestFor */
require_once __DIR__ . '/../../lib/partners.php';

$id       = (int)$partner['id'];
$name     = (string)($partner['org_name'] ?? 'Provider');
$type     = partner_type_label($partner['raw_org_type'] ?? null);
$emirate  = trim((string)($partner['emirate_name'] ?? ''));
$city     = trim((string)($partner['city_text'] ?? ''));
// Avoid "Dubai, Dubai" when the city text duplicates the emirate.
$locParts = array_values(array_unique(array_filter([$city, $emirate], static fn($s) => $s !== '')));
if (count($locParts) === 2 && mb_strtolower($locParts[0]) === mb_strtolower($locParts[1])) {
    array_pop($locParts);
}
$location = implode(', ', $locParts);
$vc       = (int)($partner['venue_count'] ?? 0);
$website  = trim((string)($partner['website'] ?? ''));
$verified = partner_is_verified($partner);
$badges   = partner_badges($partner);   // max two

// Cover: best published venue image if the file exists, else seeded gradient
// class (no inline styles — strict CSP style-src 'self').
$coverRel = trim((string)($partner['cover_image'] ?? ''));
$hasCover = $coverRel !== '' && is_file(app_path($coverRel));
$gradIdx  = partner_cover_gradient_index($name);

// Avatar: logo if present on disk, else monogram.
$logoRel = trim((string)($partner['logo_path'] ?? ''));
$hasLogo = $logoRel !== '' && is_file(app_path($logoRel));

// approved_at may be an epoch-0 sentinel from the migration (strtotime → 0,
// which is falsy); prefer a real approved_at, else fall back to created_at.
$approvedTs = $partner['approved_at'] ? (int)strtotime((string)$partner['approved_at']) : 0;
$createdTs  = $partner['created_at']  ? (int)strtotime((string)$partner['created_at'])  : 0;
$sinceTs    = ($approvedTs > 946684800) ? $approvedTs : $createdTs;   // after 2000-01-01
$sinceYr    = $sinceTs > 0 ? date('Y', $sinceTs) : '';

$enquireUrl = base_url('enquire') . query_string(['provider' => $id]);   // partner-mode
$venuesUrl  = base_url('venues') . query_string(['partner' => $id]);
?>
<div class="atv-wrap">
  <div class="venues-crumb">
    <a href="<?= e(base_url('/')) ?>">Home</a> &rsaquo;
    <a href="<?= e(base_url('providers')) ?>">Venue Providers</a> &rsaquo; <b><?= e($name) ?></b>
  </div>

  <!-- Cover + avatar header -->
  <div class="phero">
    <div class="phero__cover cover-grad--<?= $gradIdx ?>">
      <?php if ($hasCover): ?><img class="phero__cover-img" src="<?= e(base_url($coverRel)) ?>" alt=""><?php endif; ?>
    </div>
    <?php if ($badges): ?>
      <div class="phero__badges">
        <?php foreach ($badges as $b): ?>
          <span class="atv-badge<?= $b['variant'] === 'verified' ? ' atv-badge--verified' : '' ?>"><?= e($b['label']) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div class="phero__info">
      <div class="phero__id">
        <span class="phero__av<?= $hasLogo ? '' : ' phero__av--mono' ?>">
          <?php if ($hasLogo): ?><img src="<?= e(base_url($logoRel)) ?>" alt="<?= e($name) ?> logo"><?php else: ?><?= e(partner_monogram($name)) ?><?php endif; ?>
        </span>
        <div class="phero__txt">
          <h1><?= e($name) ?></h1>
          <div class="phero__meta">
            <span><?= icon('building') ?> <?= e($type) ?></span>
            <?php if ($location !== ''): ?><span><?= icon('map-pin') ?> <?= e($location) ?></span><?php endif; ?>
            <span><?= icon('glass-cheers') ?> <?= e((string)$vc) ?> venue<?= $vc === 1 ? '' : 's' ?></span>
          </div>
        </div>
      </div>
      <div class="phero__acts">
        <a class="atv-btn" href="<?= e($enquireUrl) ?>"><?= icon('send') ?> Send enquiry</a>
        <a class="atv-btn atv-btn--ghost" href="<?= e($venuesUrl) ?>"><?= icon('glass-cheers') ?> View venues</a>
      </div>
    </div>
  </div>
  <?php if ($website !== ''): ?>
    <div class="phero__subbar">
      <a href="<?= e($website) ?>" rel="noopener nofollow" target="_blank">Visit website ↗</a>
    </div>
  <?php endif; ?>

  <div class="vd-body">
    <div class="vd-content">
      <?php if (trim((string)$partner['about']) !== ''): ?>
        <h2>About <?= e($name) ?></h2>
        <div class="venue-richtext"><?= $partner['about'] /* sanitized HTML */ ?></div>
      <?php endif; ?>

      <section class="vd-similar" id="provider-venues">
        <h2>Venues by this provider</h2>
        <div class="atv-sub2"><?= e((string)$vc) ?> published venue<?= $vc === 1 ? '' : 's' ?></div>
        <?php if ($partnerVenues): ?>
          <div class="atv-cards">
            <?php foreach ($partnerVenues as $venue): ?>
              <?php require __DIR__ . '/../partials/venue-card.php'; ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="venues-empty"><p class="text-muted mb-0">This provider has no published venues yet.</p></div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="vd-side">
      <div class="vd-keypanel">
        <h4>Provider information</h4>
        <?php
          $krow = static function (string $k, ?string $v): void {
              if (trim((string)$v) === '') return;
              echo '<div class="vd-krow"><span class="k">' . e($k) . '</span><span class="v">' . e($v) . '</span></div>';
          };
          $krow('Type', $type);
          $krow('Location', $location);
          $krow('Venues listed', (string)$vc);
          if ($bestFor) { $krow('Best for', implode(' · ', $bestFor)); }
        ?>
        <?php if ($verified): ?>
          <div class="vd-krow"><span class="k">Verified by ATV</span><span class="v"><?= icon('check-circle') ?> Yes</span></div>
        <?php endif; ?>
        <?php
          $krow('Provider since', $sinceYr);
          $krow('Enquiries', 'Managed through ATV');
        ?>
      </div>
      <div class="vd-enqbar">
        <h4>Interested in a venue from <?= e($name) ?>?</h4>
        <p>Share your event details once, and we'll help connect you with the right contact.</p>
        <a class="atv-btn atv-btn--sand" href="<?= e($enquireUrl) ?>">Enquire about this provider</a>
      </div>
    </aside>
  </div>
</div>

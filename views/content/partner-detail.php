<?php
declare(strict_types=1);

/**
 * Partner detail content. Expects $partner, array $partnerVenues, array $bestFor.
 * about is sanitized HTML (rendered raw); everything else e()-escaped.
 * NEVER renders email/phone — enquiries route through ATV.
 */
/** @var array $partner @var array $partnerVenues @var array $bestFor */
require_once __DIR__ . '/../../lib/partners.php';

$id       = (int)$partner['id'];
$name     = (string)($partner['org_name'] ?? 'Partner');
$type     = partner_type_label($partner['raw_org_type'] ?? null);
$emirate  = trim((string)($partner['emirate_name'] ?? ''));
$city     = trim((string)($partner['city_text'] ?? ''));
$location = trim(implode(', ', array_filter([$city, $emirate])));
$vc       = (int)($partner['venue_count'] ?? 0);
$logoRel  = trim((string)($partner['logo_path'] ?? ''));
$hasLogo  = $logoRel !== '' && is_file(app_path($logoRel));
$website  = trim((string)($partner['website'] ?? ''));
// approved_at may be an epoch-0 sentinel from the migration (strtotime → 0,
// which is falsy); prefer a real approved_at, else fall back to created_at.
$approvedTs = $partner['approved_at'] ? (int)strtotime((string)$partner['approved_at']) : 0;
$createdTs  = $partner['created_at']  ? (int)strtotime((string)$partner['created_at'])  : 0;
$sinceTs    = ($approvedTs > 946684800) ? $approvedTs : $createdTs;   // after 2000-01-01
$sinceYr    = $sinceTs > 0 ? date('Y', $sinceTs) : '';
$updated    = $sinceTs > 0 ? date('M Y', $sinceTs) : '';
$enquireUrl = base_url('enquire') . query_string(['partner' => $id]);
$venuesUrl  = base_url('venues') . query_string(['partner' => $id]);
?>
<div class="atv-wrap">
  <div class="venues-crumb">
    <a href="<?= e(base_url('/')) ?>">Home</a> &rsaquo;
    <a href="<?= e(base_url('partners')) ?>">Partners</a> &rsaquo; <b><?= e($name) ?></b>
  </div>

  <!-- Profile header -->
  <div class="phead">
    <?php if ($hasLogo): ?>
      <span class="partner-logo partner-logo--lg"><img src="<?= e(base_url($logoRel)) ?>" alt="<?= e($name) ?> logo"></span>
    <?php else: ?>
      <span class="partner-logo partner-logo--lg partner-logo--mono"><?= e(partner_monogram($name)) ?></span>
    <?php endif; ?>
    <div class="phead__body">
      <?php if (!empty($partner['is_featured'])): ?>
        <div class="phead__badges"><span class="atv-badge">Featured partner</span></div>
      <?php endif; ?>
      <h1><?= e($name) ?></h1>
      <div class="phead__sub">
        <span><?= icon('building') ?> <?= e($type) ?></span>
        <?php if ($location !== ''): ?><span><?= icon('building') ?> <?= e($location) ?></span><?php endif; ?>
        <span><?= icon('grid') ?> <?= e((string)$vc) ?> venue<?= $vc === 1 ? '' : 's' ?></span>
        <?php if ($sinceYr !== ''): ?><span>Partner since <?= e($sinceYr) ?></span><?php endif; ?>
      </div>
      <?php if ($website !== ''): ?>
        <a class="phead__web" href="<?= e($website) ?>" rel="noopener nofollow" target="_blank">Visit website ↗</a>
      <?php endif; ?>
    </div>
    <div class="phead__acts">
      <a class="atv-btn" href="<?= e($enquireUrl) ?>">Enquire about this partner</a>
      <a class="atv-btn atv-btn--ghost" href="<?= e($venuesUrl) ?>">View venues</a>
    </div>
  </div>

  <div class="vd-body">
    <div class="vd-content">
      <?php if (trim((string)$partner['about']) !== ''): ?>
        <h2>About <?= e($name) ?></h2>
        <div class="venue-richtext"><?= $partner['about'] /* sanitized HTML */ ?></div>
      <?php endif; ?>

      <section class="vd-similar" id="partner-venues">
        <h2>Venues by this partner</h2>
        <div class="atv-sub2"><?= e((string)$vc) ?> published venue<?= $vc === 1 ? '' : 's' ?></div>
        <?php if ($partnerVenues): ?>
          <div class="atv-cards">
            <?php foreach ($partnerVenues as $venue): ?>
              <?php require __DIR__ . '/../partials/venue-card.php'; ?>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="venues-empty"><p class="text-muted mb-0">This partner has no published venues yet.</p></div>
        <?php endif; ?>
      </section>
    </div>

    <aside class="vd-side">
      <div class="vd-keypanel">
        <h4>Partner information</h4>
        <?php
          $krow = static function (string $k, ?string $v): void {
              if (trim((string)$v) === '') return;
              echo '<div class="vd-krow"><span class="k">' . e($k) . '</span><span class="v">' . e($v) . '</span></div>';
          };
          $krow('Type', $type);
          $krow('Location', $location);
          $krow('Venues listed', (string)$vc);
          if ($bestFor) { $krow('Best for', implode(' · ', $bestFor)); }
          $krow('Verified by ATV', 'Yes');
          $krow('Partner since', $sinceYr);
          $krow('Last updated', $updated);
          $krow('Enquiries', 'Managed through ATV');
        ?>
      </div>
      <div class="vd-enqbar">
        <h4>Interested in a venue from <?= e($name) ?>?</h4>
        <p>Share your event details once, and we'll help connect you with the right contact.</p>
        <a class="atv-btn atv-btn--sand" href="<?= e($enquireUrl) ?>">Enquire about this partner</a>
      </div>
    </aside>
  </div>
</div>

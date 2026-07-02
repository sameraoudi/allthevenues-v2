<?php
declare(strict_types=1);

/**
 * Partner card for the /partners grid. Expects $partner (a partner_list row).
 * Logo falls back to an initials monogram. NEVER renders email/phone.
 */
/** @var array $partner */
require_once __DIR__ . '/../../lib/partners.php';

$slug     = (string)($partner['slug'] ?? '');
$detail   = base_url('partners/' . rawurlencode($slug));
$type     = partner_type_label($partner['raw_org_type'] ?? null);
$emirate  = trim((string)($partner['emirate_name'] ?? ''));
$location = trim((string)($partner['city_text'] ?? '')) !== '' ? trim((string)$partner['city_text']) : $emirate;
$vc       = (int)($partner['venue_count'] ?? 0);
$logoRel  = trim((string)($partner['logo_path'] ?? ''));
$hasLogo  = $logoRel !== '' && is_file(app_path($logoRel));
$grp      = trim($type . ($emirate !== '' ? ' · ' . $emirate : ''), ' ·');
$about    = snippet($partner['about'] ?? '', 130);
?>
<article class="partner-card<?= !empty($partner['is_featured']) ? ' partner-card--featured' : '' ?>">
  <?php if (!empty($partner['is_featured'])): ?><span class="atv-badge partner-card__badge">Featured</span><?php endif; ?>
  <a class="partner-card__top" href="<?= e($detail) ?>">
    <?php if ($hasLogo): ?>
      <span class="partner-logo"><img src="<?= e(base_url($logoRel)) ?>" alt="<?= e($partner['org_name'] ?? 'Partner') ?> logo" loading="lazy"></span>
    <?php else: ?>
      <span class="partner-logo partner-logo--mono"><?= e(partner_monogram((string)($partner['org_name'] ?? 'AV'))) ?></span>
    <?php endif; ?>
    <span class="partner-card__id">
      <h3><?= e($partner['org_name'] ?? 'Partner') ?></h3>
      <?php if ($grp !== ''): ?><span class="partner-card__grp"><?= e($grp) ?></span><?php endif; ?>
    </span>
  </a>
  <?php if ($about !== ''): ?><p class="partner-card__about"><?= e($about) ?></p><?php endif; ?>
  <div class="partner-card__meta">
    <?php if ($location !== ''): ?><span><?= icon('building') ?> <?= e($location) ?></span><?php endif; ?>
    <?php if (!empty($partner['is_featured'])): ?><span><?= icon('check-circle') ?> Featured partner</span><?php endif; ?>
  </div>
  <div class="partner-card__foot">
    <span class="partner-card__vn"><?= e((string)$vc) ?> venue<?= $vc === 1 ? '' : 's' ?></span>
    <a class="partner-card__lk" href="<?= e($detail) ?>">View partner <?= icon('arrow-right') ?></a>
  </div>
</article>

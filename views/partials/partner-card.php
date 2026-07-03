<?php
declare(strict_types=1);

/**
 * Image-led provider card for the /providers grid. Expects $partner (a
 * partner_list row incl. cover_image). Cover image priority: the provider's
 * best published venue image → seeded gradient fallback. A provider-type icon
 * chip overlaps the cover (no per-provider logo request). NEVER renders
 * email/phone.
 */
/** @var array $partner */
require_once __DIR__ . '/../../lib/partners.php';

$slug     = (string)($partner['slug'] ?? '');
$detail   = base_url('providers/' . rawurlencode($slug));
$name     = (string)($partner['org_name'] ?? 'Provider');
$type     = partner_type_label($partner['raw_org_type'] ?? null);
$emirate  = trim((string)($partner['emirate_name'] ?? ''));
$location = trim((string)($partner['city_text'] ?? '')) !== '' ? trim((string)$partner['city_text']) : $emirate;
$vc       = (int)($partner['venue_count'] ?? 0);
$verified = partner_is_verified($partner);

// Cover: real venue image if the file exists on disk, else a seeded gradient
// class (no inline styles — strict CSP style-src 'self').
$coverRel = trim((string)($partner['cover_image'] ?? ''));
$hasCover = $coverRel !== '' && is_file(app_path($coverRel));
$gradIdx  = partner_cover_gradient_index($name);

$badges = partner_badges($partner);
$cover  = $badges[0] ?? null;   // single badge on the card cover
?>
<article class="pcard">
  <a class="pcard__cover cover-grad--<?= $gradIdx ?>" href="<?= e($detail) ?>" aria-label="<?= e($name) ?>">
    <?php if ($hasCover): ?><img class="pcard__cover-img" src="<?= e(base_url($coverRel)) ?>" alt="" loading="lazy"><?php endif; ?>
    <?php if ($cover): ?><span class="atv-badge<?= $cover['variant'] === 'verified' ? ' atv-badge--verified' : '' ?> pcard__badge"><?= e($cover['label']) ?></span><?php endif; ?>
    <span class="pcard__count"><?= e((string)$vc) ?> venue<?= $vc === 1 ? '' : 's' ?></span>
    <span class="pcard__type" title="<?= e($type) ?>"><?= icon(provider_type_icon($type)) ?></span>
  </a>
  <div class="pcard__body">
    <h3><a href="<?= e($detail) ?>"><?= e($name) ?></a></h3>
    <div class="pcard__tl">
      <?= icon('building') ?> <?= e($type) ?><?php if ($location !== ''): ?> &nbsp;·&nbsp; <?= icon('map-pin') ?> <?= e($location) ?><?php endif; ?>
    </div>
    <div class="pcard__foot">
      <?php if ($verified): ?>
        <span class="pcard__vn pcard__vn--verified"><?= icon('check-circle') ?> Verified provider</span>
      <?php else: ?>
        <span class="pcard__vn">Venue provider</span>
      <?php endif; ?>
      <a class="pcard__lk" href="<?= e($detail) ?>">View <?= icon('arrow-right') ?></a>
    </div>
  </div>
</article>

<?php
declare(strict_types=1);

/**
 * PU-A1 / #7b — My Venues list. The owned-venue table with a per-venue status chip
 * + request badge (Under review / Changes requested / Edit under review / Delist
 * requested / Delisted / Live). Expects $myVenues (partner-scoped safe rows) +
 * $pendingCrs (portal_owned_pending_crs map). All output escaped.
 */
/** @var array $myVenues @var array $pendingCrs */
$myVenues  = $myVenues ?? [];
$pendingCrs = $pendingCrs ?? [];
?>
<div class="pcontent__head">
  <div>
    <h1 class="pcontent__title">My Venues</h1>
    <p class="pcontent__sub"><?= e(number_format(count($myVenues))) ?> venue<?= count($myVenues) === 1 ? '' : 's' ?> on your account</p>
  </div>
  <div class="pcontent__actions">
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/claim')) ?>">Claim a venue</a>
    <a class="atv-btn" href="<?= e(base_url('portal/venues/new')) ?>"><?= icon('plus', 'atv-btn__ico') ?> Add a venue</a>
  </div>
</div>

<div class="admin-panel<?= !$myVenues ? ' admin-panel--center' : '' ?>">
  <?php require __DIR__ . '/_venues-table.php'; ?>
</div>

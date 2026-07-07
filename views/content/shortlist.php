<?php
declare(strict_types=1);

/**
 * Shortlist content. Expects: $rows (published venue-card rows, in order),
 * $requested (int[] ids from ?ids), $resolved (int[] published ids), $enquireHref.
 * The heart on each card is the REMOVE toggle (app.js). No inline JS/CSS.
 */
/** @var array $rows @var array $requested @var array $resolved @var string $enquireHref */
require_once __DIR__ . '/../../lib/icons.php';

$reqCsv = implode(',', array_map('intval', $requested));
$resCsv = implode(',', array_map('intval', $resolved));
?>
<section class="atv-sl">
  <div class="atv-wrap" data-shortlist-requested="<?= e($reqCsv) ?>" data-shortlist-resolved="<?= e($resCsv) ?>">

    <?php if ($rows): ?>
      <div data-shortlist-populated>
        <div class="atv-sl-head">
          <div>
            <h1>Your shortlist</h1>
            <p class="atv-sl-sub"><span data-shortlist-count><?= count($rows) ?></span> venues saved on this device.
              Enquire once and we'll route your request to the right providers.</p>
          </div>
          <div class="atv-sl-head__cta">
            <a class="atv-btn" data-shortlist-enquire data-shortlist-base="<?= e(base_url('enquire')) ?>"
               href="<?= e($enquireHref) ?>">Enquire about these venues</a>
          </div>
        </div>

        <div class="atv-sl-bar">
          <span class="atv-sl-bar__t">Comparing <b><?= count($rows) ?> venue<?= count($rows) === 1 ? '' : 's' ?></b>.
            Remove any venue with the heart, then send one enquiry for the ones you like.</span>
          <span class="atv-sl-bar__acts">
            <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('venues')) ?>">Browse More Venues</a>
            <a class="atv-sl-clear" role="button" tabindex="0" data-shortlist-clear href="<?= e(base_url('venues')) ?>">Clear shortlist</a>
          </span>
        </div>

        <?php if (count($resolved) < count($requested)): ?>
          <p class="atv-sl-note">Some saved venues are no longer available and were removed.</p>
        <?php endif; ?>

        <div class="atv-cards atv-sl-grid">
          <?php foreach ($rows as $venue): ?>
            <div class="atv-sl-item" data-shortlist-item data-venue-id="<?= (int)$venue['id'] ?>">
              <?php require __DIR__ . '/../partials/venue-card.php'; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <section class="atv-sl-empty"<?= $rows ? ' hidden' : '' ?> data-shortlist-empty>
      <span class="atv-sl-empty__icon" aria-hidden="true"><?= icon('heart') ?></span>
      <h2>Your shortlist is empty</h2>
      <p>Tap the heart on any venue to save it here. When you're ready, send one enquiry for the venues you're comparing.</p>
      <div class="atv-sl-empty__acts">
        <a class="atv-btn" href="<?= e(base_url('venues')) ?>">Browse Venues</a>
        <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('enquire') . '?mode=assisted') ?>">Get Help Finding a Venue</a>
      </div>
    </section>

  </div>
</section>

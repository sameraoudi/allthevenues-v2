<?php
declare(strict_types=1);

/**
 * PU-C #9 — roomy "Add proof" screen. Compact venue summary + a prominent
 * "All The Venues asked:" box (latest proof_requested note) + a large response
 * textarea + a proof-link field. Built to the design lock. Posts to
 * /portal/claim/{id}/proof (handled by claim.php → portal_add_claim_proof, which
 * APPENDS a proof_added event). Expects $claim, string $reqNote, ?$flash.
 */
/** @var array $claim @var string $reqNote @var ?array $flash */
$flash  = $flash ?? null;
$reqNote = $reqNote ?? '';
$rid    = (int)$claim['id'];
$loc = trim(implode(', ', array_filter([
    trim((string)($claim['venue_area'] ?? '')),
    trim((string)($claim['venue_emirate'] ?? '')),
])));
?>
<p><a class="lead-back" href="<?= e(base_url('portal/claim/' . $rid . '/view')) ?>">&larr; Back to claim</a></p>

<?php if (!empty($flash)): ?>
  <div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div>
<?php endif; ?>

<div class="lead-detail__head"><h1>Add proof</h1></div>

<div class="admin-panel">
  <div class="claim-vsum">
    <span class="claim-vsum__th" aria-hidden="true"></span>
    <div><strong><?= e((string)($claim['venue_name'] ?? 'Venue')) ?></strong><?php if ($loc !== ''): ?> &middot; <?= e($loc) ?><?php endif; ?></div>
  </div>

  <?php if (trim($reqNote) !== ''): ?>
    <div class="claim-reqnote"><strong>All The Venues asked:</strong> <?= nl2br(e(trim($reqNote))) ?></div>
  <?php else: ?>
    <div class="claim-reqnote"><strong>All The Venues asked</strong> for more evidence that you&rsquo;re authorised to manage this venue.</div>
  <?php endif; ?>

  <form class="admin-form" method="post" action="<?= e(base_url('portal/claim/' . $rid . '/proof')) ?>" novalidate>
    <?php csrf_field(); ?>
    <div class="atv-field atv-field--full">
      <label for="pf-message">Your response / evidence</label>
      <textarea id="pf-message" name="message" rows="6" maxlength="2000" placeholder="Explain your authority and reference the proof you&rsquo;re providing&hellip;"></textarea>
    </div>
    <div class="atv-field atv-field--full">
      <label for="pf-proof">Proof link</label>
      <input type="text" id="pf-proof" name="proof_url" maxlength="255" placeholder="https://… — management agreement, company page, or venue-domain email">
      <p class="lead-hint">Submitting sends your proof back to All The Venues and your claim returns to review. Your original claim and our request stay on record.</p>
    </div>
    <div class="admin-form__actions">
      <button type="submit" class="atv-btn">Submit Proof</button>
      <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/claim/' . $rid . '/view')) ?>">Cancel</a>
    </div>
  </form>
</div>

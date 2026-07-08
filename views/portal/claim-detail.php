<?php
declare(strict_types=1);

/**
 * PU-C #11 — partner claim detail: the full history timeline + current status +
 * a contextual action. Expects $claim (portal_claim_for_partner row), $events
 * (portal_claim_timeline), ?$flash. Escapes everything; no inline styles/scripts.
 */
/** @var array $claim @var array $events @var ?array $flash */
$flash  = $flash ?? null;
$events = $events ?? [];
$rid    = (int)$claim['id'];
$status = (string)$claim['status'];
$statusMeta = [
    'pending'       => ['Pending review',  'st-pending'],
    'needs_changes' => ['Proof requested', 'st-need'],
    'approved'      => ['Approved',        'st-ok'],
    'rejected'      => ['Rejected',        'st-rej'],
    'withdrawn'     => ['Withdrawn',       'st-arch'],
];
[$stLabel, $stClass] = $statusMeta[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'st-arch'];
$loc = trim(implode(', ', array_filter([
    trim((string)($claim['venue_area'] ?? '')),
    trim((string)($claim['venue_emirate'] ?? '')),
])));
?>
<p><a class="lead-back" href="<?= e(base_url('portal/claim')) ?>">&larr; Back to Claims</a></p>

<?php if (!empty($flash)): ?>
  <div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div>
<?php endif; ?>

<div class="lead-detail__head">
  <h1>Claim: <?= e((string)($claim['venue_name'] ?? 'Venue')) ?></h1>
  <span class="claim-st <?= e($stClass) ?>"><?= e($stLabel) ?></span>
</div>
<?php if ($loc !== ''): ?><p class="portal-sub mb-2"><?= e($loc) ?></p><?php endif; ?>

<?php if ($status === 'needs_changes'): ?>
  <div class="admin-panel">
    <p class="lead-hint mb-2">All The Venues asked for more information before this claim can be approved.</p>
    <a class="atv-btn" href="<?= e(base_url('portal/claim/' . $rid . '/proof')) ?>">Add proof</a>
  </div>
<?php elseif ($status === 'approved'): ?>
  <div class="admin-panel">
    <p class="lead-hint mb-0">This claim was approved &mdash; the venue is now in <a href="<?= e(base_url('portal/venues')) ?>">My Venues</a>.</p>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <h2 class="admin-panel__title">Claim history</h2>
  <?php require __DIR__ . '/_claim-timeline.php'; ?>
  <p class="lead-hint mt-2">Nothing is overwritten &mdash; each step (your original claim, our requests, your proof, and the final decision) is kept with its date.</p>
</div>

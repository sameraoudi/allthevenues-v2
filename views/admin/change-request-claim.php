<?php
declare(strict_types=1);

/**
 * #3 U-P8b — Claim review screen. Expects $req (from cr_admin_get), $cl (from
 * cr_load_claim), ?$flash, ?$noteError, ?$note. Approving reassigns the venue to
 * the claimant (server-gated on a verification confirm when contested). Decision
 * actions render only for a still-pending claim. Escapes everything; no inline
 * styles/scripts (CSP self-only). Built to the approved design lock.
 */
/** @var array $req @var array $cl @var ?array $flash @var ?string $noteError @var string $note */
$flash     = $flash ?? null;
$noteError = $noteError ?? null;
$note      = $note ?? '';
$id        = (int)$req['id'];
$venueId   = (int)($req['venue_id'] ?? 0);
$isPending = ((string)$req['status'] === 'pending');
[$stLabel, $stClass] = cr_status_meta((string)$req['status']);
$claim   = $cl['claim'] ?? [];
$review  = $cl['review'] ?? [];
$contested = !empty($cl['contested']);
$dc      = (string)($cl['domain_check'] ?? 'unknown');
$evStatuses = cr_claim_evidence_statuses();
$evTypes    = cr_claim_evidence_types();
$f = static function (string $label, ?string $value, bool $full = false, bool $raw = false) {
    $val = trim((string)$value);
    if ($val === '') { return; }
    echo '<div class="clr-f' . ($full ? ' clr-f--full' : '') . '"><div class="clr-k">' . e($label)
        . '</div><div class="clr-v">' . ($raw ? $val : e($val)) . '</div></div>';
};
$roleLabel = trim((string)($claim['role'] ?? ''));
$requesterLine = trim((string)($cl['requester_name'] ?? '') . ($roleLabel !== '' ? ' · ' . $roleLabel : ''));
?>
<p><a class="lead-back" href="<?= e(base_url('admin/change-requests')) ?>">&larr; Back to change requests</a></p>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['msg']) ?></div><?php endif; ?>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title">Claim: <?= e((string)($req['venue_name'] ?? 'Venue')) ?></h2>
    <?php if ($venueId): ?><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('admin/venues/' . $venueId . '/edit')) ?>" target="_blank" rel="noopener">Open venue record &#8599;</a><?php endif; ?>
  </div>
  <p class="lead-hint mb-0">
    Claimed by <strong><?= e((string)($cl['claimant_name'] ?? '')) ?></strong>
    · submitted by <?= e((string)($cl['requester_email'] ?? '—')) ?> · <?= e(date('j M Y H:i', strtotime((string)$req['created_at']))) ?>
    &nbsp; <span class="lead-mode lead-mode--claim">Claim</span>
    <span class="lead-status lead-status--<?= e($stClass) ?>"><?= e($stLabel) ?></span>
    <span class="cr-risk cr-risk--high" title="Approving reassigns who manages a public venue.">High risk</span>
  </p>
</div>

<div class="admin-panel">
  <?php if ($contested): ?>
    <div class="clr-conflict"><strong>Ownership conflict:</strong> this venue is currently managed by
      <strong><?= e((string)($cl['current_owner_name'] ?? 'another account')) ?></strong>
      <?php if (!empty($cl['management_source']) || !empty($cl['assigned_at'])): ?>(<?= e((string)($cl['management_source'] ?? '')) ?><?= !empty($cl['assigned_at']) ? ', ' . e(date('j M Y', strtotime((string)$cl['assigned_at']))) : '' ?>)<?php endif; ?>.
      Approving this claim reassigns the venue to <?= e((string)($cl['claimant_name'] ?? 'the claimant')) ?> and removes it from the current account.
      <strong>If ownership is unclear, request proof before approving.</strong></div>
  <?php endif; ?>

  <h3 class="admin-panel__title">Claimant</h3>
  <div class="clr-grid">
    <?php
      $f('Requesting provider', (string)($cl['claimant_name'] ?? ''));
      $f('Requester & role', $requesterLine);
      $f('Work email', (string)($claim['work_email'] ?? ''));
      // Domain check badge.
      $dcHtml = $dc === 'match'
          ? '<span class="clr-match clr-match--ok">&#10003; Domain match</span>'
          : ($dc === 'no_match'
              ? '<span class="clr-match clr-match--no">&#10007; No direct domain match — manual verification required</span>'
              : '<span class="clr-match clr-match--unk">Domain check unavailable</span>');
      $f('Domain check', $dcHtml, false, true);
      $proof = trim((string)($claim['proof_url'] ?? ''));
      if ($proof !== '' && preg_match('~^https?://~i', $proof)) {
          $f('Proof of authorisation', '<a href="' . e($proof) . '" target="_blank" rel="noopener nofollow">' . e($proof) . ' &#8599;</a>', true, true);
      }
      $f('Message', (string)($claim['message'] ?? ''), true);
    ?>
  </div>

  <?php if ($contested || !empty($cl['current_owner_name'])): ?>
    <h3 class="admin-panel__title mt-2">Current assignment</h3>
    <div class="clr-grid">
      <?php
        $f('Managed by', (string)($cl['current_owner_name'] ?? ''));
        $since = trim((string)($cl['management_source'] ?? '') . (!empty($cl['assigned_at']) ? ' · ' . date('j M Y', strtotime((string)$cl['assigned_at'])) : ''));
        $f('Source · since', $since);
      ?>
    </div>
  <?php endif; ?>
</div>

<?php /* PU-C #10 — the same append-only claim history the partner sees. */ ?>
<div class="admin-panel">
  <h3 class="admin-panel__title">Claim history</h3>
  <?php $events = portal_claim_timeline($req); require __DIR__ . '/../portal/_claim-timeline.php'; ?>
  <p class="lead-hint mt-2">Each step is preserved. Your decision below appends to this timeline.</p>
</div>

<?php if ($isPending): ?>
  <form class="admin-form" method="post" action="<?= e(base_url('admin/change-requests/' . $id)) ?>" data-claim-form data-claim-contested="<?= $contested ? '1' : '0' ?>">
    <?php csrf_field(); ?>

    <div class="admin-panel">
      <h3 class="admin-panel__title">Evidence review</h3>
      <div class="admin-form__grid">
        <div class="atv-field">
          <label for="ev-status">Evidence status</label>
          <select id="ev-status" name="evidence_status">
            <?php foreach ($evStatuses as $k => $label): ?><option value="<?= e($k) ?>"<?= (string)($review['evidence_status'] ?? 'not_verified') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="atv-field">
          <label for="ev-type">Evidence type</label>
          <select id="ev-type" name="evidence_type">
            <?php foreach ($evTypes as $k => $label): ?><option value="<?= e($k) ?>"<?= (string)($review['evidence_type'] ?? 'manual_confirmation') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="atv-field atv-field--full">
          <label for="ev-note">Internal note <span class="text-muted">(not shown to the provider)</span></label>
          <input type="text" id="ev-note" name="internal_note" maxlength="2000" value="<?= e((string)($review['internal_note'] ?? '')) ?>" placeholder="e.g. Called venue reception, confirmed the requester is the GM.">
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <h3 class="admin-panel__title">Decision</h3>
      <?php if ($noteError): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($noteError) ?></div><?php endif; ?>
      <div class="atv-field atv-field--full">
        <label for="review_note">Review note <span class="text-muted">— required when rejecting or requesting proof</span></label>
        <textarea id="review_note" name="review_note" rows="3"><?= e($note) ?></textarea>
      </div>

      <?php if ($contested): ?>
        <label class="clr-gate">
          <input type="checkbox" name="verified_confirm" value="1" data-claim-gate>
          <span>I have verified evidence that this provider is authorised to manage this venue. <span class="clr-gate__sub">Required to enable “Approve claim” while there is an ownership conflict.</span></span>
        </label>
        <div class="clr-notify">
          <div class="clr-notify__lbl">Notify the current provider (<?= e((string)($cl['current_owner_name'] ?? '')) ?>)</div>
          <label><input type="radio" name="notify" value="before" checked> Notify before reassignment (default)</label>
          <label><input type="radio" name="notify" value="after"> Notify after reassignment</label>
          <label><input type="radio" name="notify" value="none"> Do not notify</label>
        </div>
      <?php endif; ?>

      <div class="admin-form__actions">
        <button type="submit" class="atv-btn" name="decision" value="approve" data-claim-approve<?= $contested ? ' disabled' : '' ?>
          data-confirm="Approve this venue claim? This assigns the venue to the claimant<?= $contested ? ' and removes access from the current provider' : '' ?>. Only approve if you have verified the claimant is authorised to manage this venue.">Approve claim</button>
        <button type="submit" class="atv-btn atv-btn--ghost" name="decision" value="request_proof"
          data-confirm="Request proof? The claim stays pending and the requester is asked for evidence such as a management agreement or official email confirmation.">Request proof</button>
        <button type="submit" class="atv-btn atv-btn--danger" name="decision" value="reject"
          data-confirm="Reject this claim? The requester will be notified with your review note. The current venue assignment stays unchanged.">Reject claim</button>
      </div>
      <?php if ($contested): ?><p class="lead-hint mt-2">Approve claim is disabled until you tick the verification confirmation above.</p><?php endif; ?>
    </div>
  </form>
<?php else: ?>
  <div class="admin-panel">
    <p class="text-muted mb-0">This claim has been reviewed (<?= e($stLabel) ?>)<?= trim((string)($req['review_note'] ?? '')) !== '' ? ' — “' . e((string)$req['review_note']) . '”' : '' ?>.</p>
  </div>
<?php endif; ?>

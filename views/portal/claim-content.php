<?php
declare(strict_types=1);

/**
 * #3 U-P8a — Provider portal: claim-a-venue content. Built to
 * docs/atv-portal-claim-preview.html. Expects $q, $results, $target,
 * $openOnTarget, $myClaims, $claimRoles, $userEmail, ?$flash. Escapes everything;
 * no inline styles/scripts (CSP self-only). Submitting a claim never changes the
 * venue — admin reviews in U-P8b.
 */
/** @var string $q @var array $results @var ?array $target @var ?array $openOnTarget */
/** @var array $myClaims @var array $claimRoles @var string $userEmail @var ?array $flash */
$flash    = $flash ?? null;
$searchUrl = base_url('portal/claim');
$loc = static function (array $v): string {
    return trim(implode(' · ', array_filter([
        trim(implode(', ', array_filter([trim((string)($v['area'] ?? '')), trim((string)($v['emirate'] ?? ''))]))),
        trim((string)($v['venue_type_name'] ?? '')),
    ])));
};
$claimStatus = [
    'pending'       => ['Pending review',  'st-pending'],
    'needs_changes' => ['Proof requested', 'st-need'],
    'approved'      => ['Approved',        'st-ok'],
    'rejected'      => ['Rejected',        'st-rej'],
    'withdrawn'     => ['Withdrawn',       'st-arch'],
];
?>
<p><a class="lead-back" href="<?= e(base_url('portal')) ?>">&larr; Back to my venues</a></p>

<?php if (!empty($flash)): ?>
  <div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div>
<?php endif; ?>

<div class="lead-detail__head"><h1>Claim a venue</h1></div>
<p class="portal-sub mb-2">If your venue is already listed on All The Venues, you can request access to manage it. Find the venue, tell us how you&rsquo;re connected, and our team will review the claim before anything changes. Most claims are reviewed within a few working days, depending on the evidence provided.</p>

<div class="admin-panel">
  <h2 class="admin-panel__title">Find your venue</h2>
  <form class="claim-search" method="get" action="<?= e($searchUrl) ?>">
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search by venue name — e.g. Marina Pavilion" aria-label="Search venues">
    <button type="submit" class="atv-btn atv-btn--sm">Search</button>
  </form>

  <?php if ($q !== '' && !$results): ?>
    <p class="lead-hint mt-2">No claimable venues match &ldquo;<?= e($q) ?>&rdquo;.</p>
  <?php elseif ($results): ?>
    <div class="claim-results">
      <?php foreach ($results as $r): $rid = (int)$r['id']; $open = (int)($r['has_open_claim'] ?? 0) === 1; ?>
        <div class="claim-vrow">
          <div class="claim-vrow__info">
            <div class="claim-nm"><?= e((string)$r['name']) ?></div>
            <?php if ($loc($r) !== ''): ?><div class="claim-lo"><?= e($loc($r)) ?></div><?php endif; ?>
            <?php if ($open): ?>
              <span class="claim-badge claim-badge--already">You already have an open claim</span>
            <?php elseif (($r['partner_id'] ?? null) === null): ?>
              <span class="claim-badge claim-badge--free">Unassigned — available to claim</span>
            <?php else: ?>
              <span class="claim-badge claim-badge--managed">Managed by another account</span>
            <?php endif; ?>
          </div>
          <?php if ($open): ?>
            <a class="atv-btn atv-btn--sm atv-btn--ghost" href="#claims">View claim status</a>
          <?php else: ?>
            <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/claim/' . $rid)) ?>">Claim this venue</a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <p class="lead-hint mt-2">Only published venues you don&rsquo;t already manage appear here. Can&rsquo;t find it? You can <a href="<?= e(base_url('portal/venues/new')) ?>">submit it as a new venue</a> instead.</p>
</div>

<?php if ($target !== null): $tid = (int)$target['id']; $contested = (int)($target['contested'] ?? 0) === 1; ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Your claim</h2>
    <div class="claim-selected">
      <div>
        <div class="claim-nm"><?= e((string)$target['name']) ?></div>
        <?php if ($loc($target) !== ''): ?><div class="claim-lo"><?= e($loc($target)) ?></div><?php endif; ?>
      </div>
    </div>

    <?php if ($openOnTarget !== null): ?>
      <p class="lead-hint">You already have an open claim for this venue — see <a href="#claims">Your claims</a> below.</p>
    <?php else: ?>
      <?php if ($contested): ?>
        <div class="claim-conflict"><strong>Heads up:</strong> this venue is currently managed by another account. Your claim will be reviewed carefully, and we may ask for proof of authorisation before making any changes.</div>
      <?php endif; ?>

      <form class="admin-form" method="post" action="<?= e($searchUrl) ?>" data-claim-form>
        <?php csrf_field(); ?>
        <input type="hidden" name="venue_id" value="<?= $tid ?>">
        <div class="admin-form__grid">
          <div class="atv-field">
            <label for="claim-role">Your role at this venue</label>
            <select id="claim-role" name="role" data-claim-role>
              <?php foreach ($claimRoles as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="atv-field">
            <label for="claim-email">Work email for verification</label>
            <input type="email" id="claim-email" name="work_email" value="<?= e($userEmail) ?>" maxlength="255">
            <p class="lead-hint">Pre-filled from your account. A work email on the venue&rsquo;s own domain helps us verify faster.</p>
          </div>
        </div>
        <div class="claim-agency" data-claim-agency hidden>Because you selected <strong>Agency or representative</strong>: please explain who authorised you to manage this venue, and provide proof where possible.</div>
        <div class="atv-field atv-field--full">
          <label for="claim-msg">Message to our team</label>
          <textarea id="claim-msg" name="message" rows="3" maxlength="2000" placeholder="Tell us who you are and how you're connected to this venue — anything that helps us verify the claim."></textarea>
        </div>
        <div class="atv-field atv-field--full">
          <label for="claim-proof">Proof of authorisation<?= $contested ? ' — recommended for this venue' : ' (optional)' ?></label>
          <input type="text" id="claim-proof" name="proof_url" maxlength="255" placeholder="https://…">
          <p class="lead-hint">Link to an official venue page, company profile, management announcement, or another source that helps verify your authority.<?= $contested ? ' Because this venue is managed by another account, a claim without proof may take longer or be declined.' : '' ?></p>
        </div>
        <label class="pimg-consent">
          <input type="checkbox" name="consent" value="1" required data-claim-consent>
          <span><strong>Required —</strong> I confirm that I am authorised to manage this venue on behalf of its owner or operator, and that the information I have provided is accurate. I understand that false or unsupported claims may be rejected or may affect my partner account.</span>
        </label>
        <p class="lead-hint mb-2">Submitting doesn&rsquo;t change anything yet — the venue stays as it is until our team approves your claim.</p>
        <div class="admin-form__actions">
          <button type="submit" class="atv-btn" data-claim-submit>Submit claim for review</button>
          <a class="atv-btn atv-btn--ghost" href="<?= e($searchUrl) ?>">Cancel</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="admin-panel" id="claims">
  <h2 class="admin-panel__title">Your claims</h2>
  <?php if (!$myClaims): ?>
    <p class="text-muted mb-0">You haven&rsquo;t submitted any claims yet.</p>
  <?php else: ?>
    <?php foreach ($myClaims as $c):
      $st = (string)$c['status'];
      [$stLabel, $stClass] = $claimStatus[$st] ?? [ucfirst(str_replace('_', ' ', $st)), 'st-arch'];
      $rid  = (int)$c['id'];
      $when = ($c['created_at'] ?? null);
      $data = json_decode((string)($c['proposed_changes_json'] ?? ''), true);
      $role = is_array($data) ? (string)($data['claim']['role'] ?? '') : '';
      $note = trim((string)($c['review_note'] ?? ''));
      $reviewedAt = $c['reviewed_at'] ?? null;
      // #11 — for rejected / proof-requested, surface the reviewer's reason + date.
      $showReason = in_array($st, ['rejected', 'needs_changes'], true) && $note !== '';
    ?>
      <div class="claim-row">
        <div>
          <div class="claim-nm"><?= e((string)($c['venue_name'] ?? 'Venue')) ?></div>
          <div class="claim-lo"><?= $when ? 'Submitted ' . e(date('j M Y', strtotime((string)$when))) : '' ?><?= $role !== '' ? ' · role: ' . e($role) : '' ?></div>
          <?php if ($showReason): ?>
            <div class="claim-reason<?= $st === 'rejected' ? ' claim-reason--rej' : '' ?>">
              <strong><?= $st === 'rejected' ? 'Reason' : 'Requested' ?>:</strong> <?= e($note) ?><?php if ($reviewedAt): ?> &middot; <?= e(date('j M Y', strtotime((string)$reviewedAt))) ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="claim-row__act">
          <span class="claim-st <?= e($stClass) ?>"><?= e($stLabel) ?></span>
          <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/claim/' . $rid . '/view')) ?>">View</a>
          <?php if ($st === 'needs_changes'): ?>
            <a class="atv-btn atv-btn--sm" href="<?= e(base_url('portal/claim/' . $rid . '/proof')) ?>">Add proof</a>
          <?php elseif ($st === 'pending'): ?>
            <form method="post" action="<?= e(base_url('portal/claim/' . $rid . '/withdraw')) ?>">
              <?php csrf_field(); ?>
              <button type="submit" class="atv-btn atv-btn--sm atv-btn--danger" data-confirm="Withdraw this claim?">Withdraw</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php
declare(strict_types=1);

/**
 * Delist-2 — Delist review screen. Expects $req (from cr_admin_get), $dl (from
 * cr_load_delist), ?$flash, ?$noteError, ?$note. Approving takes the venue offline
 * (status→delisted, 404 on its URL, out of search/event-type pages); reject keeps
 * it published (note required). Decision actions render only for a still-pending
 * request. Escapes everything; no inline styles/scripts (CSP self-only).
 */
/** @var array $req @var array $dl @var ?array $flash @var ?string $noteError @var string $note */
$flash     = $flash ?? null;
$noteError = $noteError ?? null;
$note      = $note ?? '';
$id        = (int)$req['id'];
$venueId   = (int)($req['venue_id'] ?? 0);
$isPending = ((string)$req['status'] === 'pending');
[$stLabel, $stClass] = cr_status_meta((string)$req['status']);
$venue     = $dl['venue'] ?? null;
$vStatus   = (string)($venue['status'] ?? '');
$f = static function (string $label, ?string $value, bool $full = false, bool $raw = false) {
    $val = trim((string)$value);
    if ($val === '') { return; }
    echo '<div class="clr-f' . ($full ? ' clr-f--full' : '') . '"><div class="clr-k">' . e($label)
        . '</div><div class="clr-v">' . ($raw ? $val : e($val)) . '</div></div>';
};
?>
<p><a class="lead-back" href="<?= e(base_url('admin/change-requests')) ?>">&larr; Back to change requests</a></p>

<?php if ($flash): ?><div class="lead-flash lead-flash--<?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['msg']) ?></div><?php endif; ?>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title">Delist: <?= e((string)($req['venue_name'] ?? 'Venue')) ?></h2>
    <?php if ($venueId): ?><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('admin/venues/' . $venueId . '/edit')) ?>" target="_blank" rel="noopener">Open venue record &#8599;</a><?php endif; ?>
  </div>
  <p class="lead-hint mb-0">
    Requested by <strong><?= e((string)($dl['requester_name'] ?? ($dl['requester_email'] ?? '—'))) ?></strong>
    <?php if (trim((string)($dl['requester_email'] ?? '')) !== ''): ?>· <?= e((string)$dl['requester_email']) ?><?php endif; ?>
    · <?= e(date('j M Y H:i', strtotime((string)$req['created_at']))) ?>
    &nbsp; <span class="lead-mode lead-mode--delist">Delist</span>
    <span class="lead-status lead-status--<?= e($stClass) ?>"><?= e($stLabel) ?></span>
    <span class="cr-risk cr-risk--high" title="Approving takes a live public venue offline.">High risk</span>
  </p>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Request</h3>
  <div class="clr-grid">
    <?php
      $curStatus = $vStatus !== ''
          ? '<span class="lead-status lead-status--' . e($vStatus) . '">' . e(venue_admin_status_label($vStatus)) . '</span>'
            . (trim((string)($venue['management_source'] ?? '')) !== '' ? ' · ' . e((string)$venue['management_source']) : '')
          : '—';
      $f('Current status', $curStatus, false, true);
      $f('Provider', (string)($venue['provider_name'] ?? ''));
      $f('Reason', (string)($dl['reason_label'] ?? '—'));
      $f('Details', (string)($dl['details'] ?? ''), true);
    ?>
  </div>
</div>

<?php if ($isPending): ?>
  <form class="admin-form" method="post" action="<?= e(base_url('admin/change-requests/' . $id)) ?>">
    <?php csrf_field(); ?>
    <div class="admin-panel">
      <h3 class="admin-panel__title">Decision</h3>
      <?php if ($noteError): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($noteError) ?></div><?php endif; ?>
      <p class="lead-hint mb-2">Approving hides the venue from the public site (status &rarr; <strong>delisted</strong>, its URL 404s, kept out of search + event-type pages). New enquiries pause. All data is preserved; the provider can re-list anytime.</p>
      <div class="atv-field atv-field--full">
        <label for="review_note">Review note <span class="text-muted">— required when rejecting</span></label>
        <textarea id="review_note" name="review_note" rows="3"><?= e($note) ?></textarea>
      </div>
      <div class="admin-form__actions">
        <button type="submit" class="atv-btn atv-btn--danger" name="decision" value="approve"
          data-confirm="Approve delisting? &quot;<?= e((string)($req['venue_name'] ?? 'This venue')) ?>&quot; will be hidden from the public site until it's re-listed.">Approve delisting</button>
        <button type="submit" class="atv-btn atv-btn--ghost" name="decision" value="reject"
          data-confirm="Reject this delisting request? The venue stays published and the provider is notified with your note.">Reject</button>
      </div>
      <p class="lead-hint mt-2">Reject keeps the venue published (note required). Every decision is audited and the provider is emailed. You can also delist / re-list directly from the venue editor&rsquo;s status dropdown.</p>
    </div>
  </form>
<?php else: ?>
  <div class="admin-panel">
    <p class="text-muted mb-0">This request has been reviewed (<?= e($stLabel) ?>)<?= trim((string)($req['review_note'] ?? '')) !== '' ? ' — &ldquo;' . e((string)$req['review_note']) . '&rdquo;' : '' ?>.</p>
  </div>
<?php endif; ?>

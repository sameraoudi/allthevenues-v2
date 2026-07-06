<?php
declare(strict_types=1);

/**
 * #3 U-P5b — Change-request diff + decision actions. Expects $req (from
 * cr_admin_get: incl. change_rows, risk, venue/provider context), ?$flash,
 * ?$noteError, ?$note. Actions render only for a pending type='edit' request.
 * Everything is escaped; FK old/new ids are pre-resolved to names in change_rows.
 */
/** @var array $req @var ?array $flash @var ?string $noteError @var string $note */
$flash     = $flash ?? null;
$noteError = $noteError ?? null;
$note      = $note ?? '';
$id        = (int)$req['id'];
$isEdit    = ($req['type'] === 'edit');
$isPending = ($req['status'] === 'pending');
[$stLabel, $stClass] = cr_status_meta((string)$req['status']);
$venueId   = (int)($req['venue_id'] ?? 0);
$submitter = trim((string)($req['submitter_name'] ?? '')) !== ''
    ? (string)$req['submitter_name'] : (string)($req['submitter_email'] ?? '—');
?>
<p><a class="lead-back" href="<?= e(base_url('admin/change-requests')) ?>">&larr; Back to change requests</a></p>

<?php if ($flash): ?>
  <div class="lead-flash lead-flash--<?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['msg']) ?></div>
<?php endif; ?>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title">
      <?php if ($venueId): ?><a href="<?= e(base_url('admin/venues/' . $venueId . '/edit')) ?>"><?= e((string)($req['venue_name'] ?? 'Venue')) ?></a><?php else: ?><?= e((string)($req['venue_name'] ?? 'New venue')) ?><?php endif; ?>
    </h2>
    <div>
      <span class="lead-mode lead-mode--<?= e((string)$req['type']) ?>"><?= e(cr_type_label((string)$req['type'])) ?></span>
      <?php if ($isEdit): ?><span class="cr-risk cr-risk--<?= e((string)$req['risk']) ?>"><?= e(cr_risk_label((string)$req['risk'])) ?> risk</span><?php endif; ?>
      <span class="lead-status lead-status--<?= e($stClass) ?>"><?= e($stLabel) ?></span>
    </div>
  </div>
  <?php
    echo '<div class="lead-detail__row"><span class="lead-detail__k">Provider</span><span class="lead-detail__v">' . e((string)($req['provider_name'] ?? '—')) . '</span></div>';
    echo '<div class="lead-detail__row"><span class="lead-detail__k">Submitted by</span><span class="lead-detail__v">' . e($submitter) . ' · ' . e(date('j M Y H:i', strtotime((string)$req['created_at']))) . '</span></div>';
    if (!$isPending && !empty($req['reviewed_at'])) {
        echo '<div class="lead-detail__row"><span class="lead-detail__k">Reviewed</span><span class="lead-detail__v">' . e(date('j M Y H:i', strtotime((string)$req['reviewed_at']))) . '</span></div>';
    }
    if (trim((string)($req['review_note'] ?? '')) !== '') {
        echo '<div class="lead-detail__row"><span class="lead-detail__k">Review note</span><span class="lead-detail__v">' . nl2br(e((string)$req['review_note'])) . '</span></div>';
    }
  ?>
</div>

<?php if (!$isEdit): ?>
  <div class="admin-panel">
    <p class="text-muted mb-0">This request type (<?= e(cr_type_label((string)$req['type'])) ?>) is reviewed in a later unit. No actions are available here yet.</p>
  </div>
<?php else: ?>
  <div class="admin-panel">
    <h3 class="admin-panel__title">Proposed changes</h3>
    <?php if (!$req['change_rows']): ?>
      <p class="text-muted mb-0">This request has no applicable changes.</p>
    <?php else: ?>
      <div class="lead-table-wrap">
        <table class="lead-table cr-diff">
          <thead><tr><th>Field</th><th>Current</th><th>Proposed</th></tr></thead>
          <tbody>
            <?php foreach ($req['change_rows'] as $row): $meta = $row['meta']; ?>
              <tr>
                <td data-label="Field">
                  <strong><?= e((string)$meta['label']) ?></strong>
                  <?php foreach (($meta['badges'] ?? []) as $b): ?><span class="cr-badge"><?= e($b) ?></span><?php endforeach; ?>
                </td>
                <td data-label="Current"><?= e((string)$row['old_disp']) ?></td>
                <td data-label="Proposed"><?= e((string)$row['new_disp']) ?></td>
              </tr>
              <?php if (!empty($meta['help'])): ?>
                <tr class="cr-diff__help"><td colspan="3"><span class="text-muted"><?= e((string)$meta['help']) ?></span></td></tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($isPending): ?>
    <div class="admin-panel">
      <h3 class="admin-panel__title">Decision</h3>
      <?php if ($noteError): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($noteError) ?></div><?php endif; ?>
      <form class="admin-form" method="post" action="<?= e(base_url('admin/change-requests/' . $id)) ?>">
        <?php csrf_field(); ?>
        <div class="atv-field atv-field--full">
          <label for="review_note">Review note <span class="text-muted">(required to reject or request changes)</span></label>
          <textarea id="review_note" name="review_note" rows="3"><?= e($note) ?></textarea>
        </div>
        <div class="admin-form__actions">
          <button type="submit" class="atv-btn" name="decision" value="approve">Approve &amp; apply</button>
          <button type="submit" class="atv-btn atv-btn--ghost" name="decision" value="needs_changes">Request changes</button>
          <button type="submit" class="atv-btn atv-btn--danger" name="decision" value="reject">Reject</button>
        </div>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>

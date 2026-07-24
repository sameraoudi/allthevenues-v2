<?php
declare(strict_types=1);

/**
 * Read-only view of a stored partner email (providers.manage-gated by the
 * controller). Expects $storedEmail (partner_emails row + sent_by_name),
 * $partner, $pid, $orgName. The branded body is re-rendered from the stored
 * plain text via the CSP-safe class-based preview (the sent HTML uses inline
 * styles the admin CSP would strip). Everything escaped.
 */
/** @var array $storedEmail @var int $pid @var string $orgName */
$statusMeta = [
    'sent'   => ['Sent',   'lead-status--approved'],
    'failed' => ['Failed', 'lead-status--rejected'],
    'draft'  => ['Draft',  'lead-status--new'],
];
[$stLabel, $stClass] = $statusMeta[$storedEmail['status']] ?? ['—', 'lead-status--new'];
$sentAt = $storedEmail['sent_at'] ?? $storedEmail['created_at'] ?? null;
?>
<p><a class="lead-back" href="<?= e(base_url('admin/partners/edit?id=' . $pid)) ?>">&larr; Back to <?= e($orgName) ?></a></p>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title"><?= e((string)$storedEmail['subject']) ?></h2>
    <span class="lead-status <?= e($stClass) ?>"><?= e($stLabel) ?></span>
  </div>
  <div class="lead-detail__row"><span class="lead-detail__k">Template</span><span class="lead-detail__v"><?= e(partner_email_template_label($storedEmail['template_key'] ?? null)) ?></span></div>
  <div class="lead-detail__row"><span class="lead-detail__k">Sent to</span><span class="lead-detail__v"><?= e((string)$storedEmail['recipient_email']) ?></span></div>
  <?php if (trim((string)($storedEmail['cc'] ?? '')) !== ''): ?>
    <div class="lead-detail__row"><span class="lead-detail__k">CC</span><span class="lead-detail__v"><?= e((string)$storedEmail['cc']) ?></span></div>
  <?php endif; ?>
  <?php if (trim((string)($storedEmail['bcc'] ?? '')) !== ''): ?>
    <div class="lead-detail__row"><span class="lead-detail__k">BCC</span><span class="lead-detail__v"><?= e((string)$storedEmail['bcc']) ?></span></div>
  <?php endif; ?>
  <div class="lead-detail__row"><span class="lead-detail__k">Sent by</span><span class="lead-detail__v"><?= e(trim((string)($storedEmail['sent_by_name'] ?? '')) !== '' ? (string)$storedEmail['sent_by_name'] : '—') ?></span></div>
  <div class="lead-detail__row"><span class="lead-detail__k">Date</span><span class="lead-detail__v"><?= $sentAt ? e(date('j M Y, H:i', strtotime((string)$sentAt))) : '—' ?></span></div>
  <?php if ($storedEmail['status'] === 'failed' && trim((string)($storedEmail['error_message'] ?? '')) !== ''): ?>
    <div class="lead-flash lead-flash--error mt-2" role="status">Delivery error: <?= e((string)$storedEmail['error_message']) ?></div>
  <?php endif; ?>
</div>

<div class="admin-panel">
  <h3 class="admin-panel__title">Email</h3>
  <div class="pe-preview">
    <?php /* CSP-safe class-based render of the exact text that was sent. */ ?>
    <?= partner_email_preview_html((string)($storedEmail['body_text'] ?? '')) ?>
  </div>
</div>

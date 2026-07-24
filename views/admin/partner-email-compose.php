<?php
declare(strict_types=1);

/**
 * Admin partner-email compose form (built to docs/atv-partner-email-preview.html).
 * Expects: $pdo, $partner, $pid, $orgName, $partnerEmail, $tpls, $form, $errors,
 * $noEmail, ?$previewHtml. Escapes everything. Template dropdown fills Subject +
 * Body via app.js fetching the same-origin JSON endpoint (CSP-safe; no inline JS).
 */
/** @var array $partner @var int $pid @var string $orgName @var array $tpls @var array $form @var array $errors @var bool $noEmail @var ?string $previewHtml */
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) { echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>'; }
};
$tplUrl = base_url('admin/partners/' . $pid . '/email/template');
?>
<p><a class="lead-back" href="<?= e(base_url('admin/partners/edit?id=' . $pid)) ?>">&larr; Back to <?= e($orgName) ?></a></p>

<?php if ($noEmail): ?>
  <div class="admin-panel">
    <div class="lead-flash lead-flash--info" role="status">
      <strong><?= e($orgName) ?></strong> has no email address on record, so there is no one to send to.
    </div>
    <p class="mt-2"><a class="atv-btn atv-btn--sm" href="<?= e(base_url('admin/partners/edit?id=' . $pid)) ?>">Add an email on the provider&rsquo;s Edit page</a></p>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php if (!empty($errors['_form'])): ?>
  <div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div>
<?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('admin/partners/' . $pid . '/email')) ?>" novalidate>
  <?php csrf_field(); ?>

  <div class="admin-panel">
    <div class="admin-form__grid">
      <div class="atv-field">
        <label for="pe-template">Template</label>
        <select id="pe-template" name="template_key" data-partner-email-template data-template-url="<?= e($tplUrl) ?>">
          <?php foreach ($tpls as $key => $t): ?>
            <option value="<?= e($key) ?>"<?= $form['template_key'] === $key ? ' selected' : '' ?>><?= e($t['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="lead-hint">Choosing a template fills the subject &amp; body (with the partner name / portal link inserted). You can edit before sending.</p>
      </div>
      <div class="atv-field">
        <label for="pe-to">To</label>
        <input type="email" id="pe-to" name="to" value="<?= e($form['to']) ?>" maxlength="255" class="<?= isset($errors['to']) ? 'is-invalid' : '' ?>">
        <?php $err('to'); ?>
        <p class="lead-hint"><a href="#" data-cc-toggle>+ Add CC / BCC</a></p>
      </div>
    </div>

    <div class="admin-form__grid" data-cc-row<?= ($form['cc'] === '' && $form['bcc'] === '' && !isset($errors['cc']) && !isset($errors['bcc'])) ? ' hidden' : '' ?>>
      <div class="atv-field">
        <label for="pe-cc">CC</label>
        <input type="text" id="pe-cc" name="cc" value="<?= e($form['cc']) ?>" maxlength="500" placeholder="comma-separated" class="<?= isset($errors['cc']) ? 'is-invalid' : '' ?>">
        <?php $err('cc'); ?>
      </div>
      <div class="atv-field">
        <label for="pe-bcc">BCC</label>
        <input type="text" id="pe-bcc" name="bcc" value="<?= e($form['bcc']) ?>" maxlength="500" placeholder="comma-separated" class="<?= isset($errors['bcc']) ? 'is-invalid' : '' ?>">
        <?php $err('bcc'); ?>
      </div>
    </div>

    <div class="atv-field atv-field--full">
      <label for="pe-subject">Subject</label>
      <input type="text" id="pe-subject" name="subject" value="<?= e($form['subject']) ?>" maxlength="255" class="<?= isset($errors['subject']) ? 'is-invalid' : '' ?>">
      <?php $err('subject'); ?>
    </div>

    <div class="atv-field atv-field--full">
      <label for="pe-body">Body</label>
      <textarea id="pe-body" name="body" rows="16" class="pe-body <?= isset($errors['body']) ? 'is-invalid' : '' ?>"><?= e($form['body']) ?></textarea>
      <?php $err('body'); ?>
      <p class="lead-hint">Plain text with blank-line paragraphs and &ldquo;- &rdquo; bullets &mdash; sent wrapped in the standard All The Venues branded email. Variables like <code>{{partner_name}}</code> / <code>{{portal_link}}</code> are filled when the template loads.</p>
    </div>

    <div class="admin-form__actions">
      <button type="submit" class="atv-btn" name="action" value="send" data-confirm-btn="Send this email to <?= e($form['to']) ?>?">Send email</button>
      <button type="submit" class="atv-btn atv-btn--ghost" name="action" value="preview">Preview</button>
      <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('admin/partners/edit?id=' . $pid)) ?>">Cancel</a>
    </div>
  </div>
</form>

<?php if ($previewHtml !== null): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Preview</h2>
    <p class="lead-hint mb-2">This is exactly how the email will render in the branded shell.</p>
    <div class="pe-preview">
      <?php /* Stored, escaped-on-build branded HTML — safe to echo raw. */ ?>
      <?= $previewHtml ?>
    </div>
  </div>
<?php endif; ?>

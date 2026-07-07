<?php
declare(strict_types=1);

/**
 * Delist-1 — Provider portal: request-delisting form for a PUBLISHED venue. Built
 * to docs/atv-portal-delisting-preview.html: the "what delisting means" explainer,
 * a required reason dropdown + optional details. Submitting proposes a delist for
 * admin review — the venue is NOT taken down here. Expects $venue, $old, $errors.
 */
/** @var array $venue @var array $old @var array $errors */
$id  = (int)$venue['id'];
$has = static fn(string $k): bool => isset($errors[$k]);
$err = static function (string $k) use ($errors): void {
    if (isset($errors[$k])) echo '<p class="atv-enq-err" role="alert">' . e($errors[$k]) . '</p>';
};
?>
<p><a class="lead-back" href="<?= e(base_url('portal/venues/' . $id)) ?>">&larr; Back to venue</a></p>

<div class="lead-detail__head">
  <h1>Request delisting</h1>
  <span class="lead-status lead-status--published">Published</span>
</div>

<?php if (!empty($errors['_form'])): ?><div class="lead-flash lead-flash--error" role="alert"><?= e($errors['_form']) ?></div><?php endif; ?>

<form class="admin-form" method="post" action="<?= e(base_url('portal/venues/' . $id . '/delist')) ?>" novalidate>
  <?php csrf_field(); ?>

  <div class="admin-panel">
    <p class="lead-flash lead-flash--info mb-2" role="note"><strong>What delisting means:</strong> your venue is hidden from the public site — it won&rsquo;t appear in search, listings, or event-type pages, and its page will return as unavailable. New enquiries pause. Your data, photos, and past enquiries are kept, and you can <strong>re-list it yourself anytime</strong> from the portal.</p>

    <div class="admin-form__grid">
      <div class="atv-field atv-field--full">
        <label for="f-reason">Why are you delisting this venue? <span class="req">*</span></label>
        <select id="f-reason" name="reason" class="<?= $has('reason') ? 'is-invalid' : '' ?>">
          <option value="">— Choose a reason —</option>
          <?php foreach (portal_delist_reasons() as $code => $label): ?>
            <option value="<?= e($code) ?>"<?= ((string)($old['reason'] ?? '') === (string)$code) ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
        <?php $err('reason'); ?>
      </div>
      <div class="atv-field atv-field--full">
        <label for="f-details">Details for All The Venues (optional)</label>
        <textarea id="f-details" name="details" rows="3" maxlength="2000" placeholder="Anything our team should know&hellip;"><?= e((string)($old['details'] ?? '')) ?></textarea>
      </div>
    </div>
    <p class="lead-hint">Requests are reviewed by All The Venues before the venue goes offline. It stays live until approved.</p>
  </div>

  <div class="admin-form__actions">
    <button type="submit" class="atv-btn atv-btn--danger">Submit delisting request</button>
    <a class="atv-btn atv-btn--ghost" href="<?= e(base_url('portal/venues/' . $id)) ?>">Cancel</a>
  </div>
</form>

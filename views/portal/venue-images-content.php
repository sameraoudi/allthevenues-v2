<?php
declare(strict_types=1);

/**
 * #3 U-P7a — Provider portal: venue-photos manager (submit + withdraw). Built to
 * docs/atv-portal-images-preview.html. Expects $venue, $pending, $rejected, $live,
 * ?$flash, int $vid. Provider-safe: no delete/reorder/primary on live images
 * (managed by ATV). Escapes everything; no inline styles/scripts (CSP self-only).
 */
/** @var array $venue @var array $pending @var array $rejected @var array $live @var int $vid */
$flash = $flash ?? null;
$loc = trim(implode(', ', array_filter([
    trim((string)($venue['area'] ?? '')),
    trim((string)($venue['emirate_name'] ?? '')),
])));
$ago = static function (?string $ts): string {
    if (!$ts) return '';
    $t = strtotime((string)$ts);
    if ($t === false) return '';
    $d = time() - $t;
    if ($d < 60)    return 'just now';
    if ($d < 3600)  return (int)floor($d / 60) . 'm ago';
    if ($d < 86400) return (int)floor($d / 3600) . 'h ago';
    return (int)floor($d / 86400) . 'd ago';
};
?>
<p><a class="lead-back" href="<?= e(base_url('portal/venues/' . $vid)) ?>">&larr; Back to <?= e((string)$venue['name']) ?></a></p>

<?php if (!empty($flash)): ?>
  <div class="lead-flash lead-flash--<?= e((string)($flash['type'] ?? 'success')) ?>" role="status"><?= e((string)($flash['msg'] ?? '')) ?></div>
<?php endif; ?>

<div class="lead-detail__head">
  <h1>Venue photos</h1>
  <span class="lead-status lead-status--<?= e((string)$venue['status']) ?>"><?= e(venue_admin_status_label((string)$venue['status'])) ?></span>
</div>
<?php if ($loc !== ''): ?><p class="portal-sub"><?= e((string)$venue['name']) ?> · <?= e($loc) ?></p><?php endif; ?>

<div class="admin-panel">
  <h2 class="admin-panel__title">Add photos of your venue</h2>
  <p class="lead-hint mb-0">Upload high-quality venue photos for review. All The Venues checks each image for quality and rights before it appears on your public listing. You can withdraw a photo any time before review.</p>
</div>

<div class="admin-panel" id="upload">
  <h2 class="admin-panel__title">Upload new photos</h2>
  <form class="admin-form" method="post" action="<?= e(base_url('portal/venues/' . $vid . '/images')) ?>" enctype="multipart/form-data" data-upload-form>
    <?php csrf_field(); ?>
    <input type="hidden" name="action" value="upload">

    <div class="atv-field atv-field--full">
      <label for="pimg-files">Choose images</label>
      <input type="file" id="pimg-files" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required data-upload-files>
      <p class="lead-hint">JPG, PNG or WebP, up to 12&nbsp;MB each — select several at once. Best photos are landscape, well lit, free of text overlays or watermarks, and clearly show the venue (at least 1200px wide). We optimise and re-encode every image automatically.</p>
    </div>

    <div class="atv-field atv-field--full">
      <label for="pimg-alt">Caption / alt text (optional)</label>
      <textarea id="pimg-alt" name="alt" rows="2" placeholder="e.g. Rooftop set for a sunset reception, skyline behind the stage."></textarea>
      <p class="lead-hint">A short description applied to the photos in this upload. Helps accessibility and SEO.</p>
    </div>

    <div class="atv-field atv-field--full">
      <label class="pimg-consent">
        <input type="checkbox" name="rights_confirm" value="1" required data-upload-consent>
        <span><strong>Required —</strong> I confirm that I own these images or have the necessary rights and permissions to submit them, and I grant All The Venues permission to display, crop, resize, optimise, and use them on allthevenues.com for this venue&rsquo;s listing and related venue discovery pages.</span>
      </label>
      <p class="lead-hint">Your confirmation is stored with each photo, including your name and the submission date, for our image-rights records.</p>
      <p class="lead-hint pimg-people"><strong>If people are clearly visible</strong>, please make sure you have permission to use the image for venue promotion — this includes guests, couples, staff, and attendees in event photos.</p>
    </div>

    <div class="admin-form__actions">
      <button type="submit" class="atv-btn" data-upload-submit>Submit for review</button>
      <span class="lead-hint">Enabled once you&rsquo;ve chosen a photo and ticked the box.</span>
    </div>
  </form>
</div>

<?php if ($pending): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Awaiting review <span class="lead-hint">· <?= count($pending) ?> photo<?= count($pending) === 1 ? '' : 's' ?></span></h2>
    <p class="lead-hint mb-2">These photos have been submitted and are with All The Venues for review. They&rsquo;re not on your public listing yet. You can withdraw one before it&rsquo;s reviewed.</p>
    <div class="pimg-grid">
      <?php foreach ($pending as $img): ?>
        <div class="pimg">
          <div class="pimg__img">
            <img src="<?= e(venue_img_src(($img['thumb_path'] ?? '') !== '' ? $img['thumb_path'] : ($img['file_path'] ?? null))) ?>" alt="<?= e((string)($img['alt_text'] ?? '')) ?>" loading="lazy">
            <span class="pimg__tag pimg__tag--pending">Pending review</span>
          </div>
          <div class="pimg__body">
            <?php if (trim((string)($img['alt_text'] ?? '')) !== ''): ?><span class="pimg__cap"><?= e((string)$img['alt_text']) ?></span><?php endif; ?>
            <span class="lead-hint">Submitted <?= e($ago($img['created_at'] ?? null)) ?></span>
            <div class="pimg__row">
              <span class="lead-hint">In review</span>
              <form method="post" action="<?= e(base_url('portal/venues/' . $vid . '/images')) ?>">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="withdraw">
                <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                <button type="submit" class="atv-btn atv-btn--sm atv-btn--danger" data-confirm="Withdraw this photo? It will be removed and not reviewed.">Withdraw</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($rejected): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Not approved <span class="lead-hint">· <?= count($rejected) ?> photo<?= count($rejected) === 1 ? '' : 's' ?></span></h2>
    <p class="lead-hint mb-2">All The Venues reviewed these photos and couldn&rsquo;t add them to your listing. You can upload a replacement that resolves the reason below.</p>
    <div class="pimg-grid">
      <?php foreach ($rejected as $img): ?>
        <div class="pimg">
          <div class="pimg__img pimg__img--rejected">
            <img src="<?= e(venue_img_src(($img['thumb_path'] ?? '') !== '' ? $img['thumb_path'] : ($img['file_path'] ?? null))) ?>" alt="<?= e((string)($img['alt_text'] ?? '')) ?>" loading="lazy">
            <span class="pimg__tag pimg__tag--rejected">Not approved</span>
          </div>
          <div class="pimg__body">
            <?php if (trim((string)($img['alt_text'] ?? '')) !== ''): ?><span class="pimg__cap"><?= e((string)$img['alt_text']) ?></span><?php endif; ?>
            <?php if (trim((string)($img['review_reason'] ?? '')) !== ''): ?><span class="lead-hint">Reason: <span class="pimg-reason"><?= e((string)$img['review_reason']) ?></span></span><?php endif; ?>
            <?php if (($img['reviewed_at'] ?? null)): ?><span class="lead-hint">Reviewed <?= e($ago($img['reviewed_at'])) ?></span><?php endif; ?>
            <div class="pimg__row">
              <span></span>
              <a class="atv-btn atv-btn--sm atv-btn--ghost" href="#upload">Upload replacement</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="lead-hint mt-2">Typical reasons: low image quality · watermark or text overlay · image rights unclear · people visible without confirmation · doesn&rsquo;t clearly show the venue · duplicate image.</p>
  </div>
<?php endif; ?>

<?php if ($live): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">On your public listing</h2>
    <div class="pimg-grid">
      <?php foreach ($live as $img): ?>
        <div class="pimg">
          <div class="pimg__img">
            <img src="<?= e(venue_img_src(($img['thumb_path'] ?? '') !== '' ? $img['thumb_path'] : ($img['file_path'] ?? null))) ?>" alt="<?= e((string)($img['alt_text'] ?? '')) ?>" loading="lazy">
            <?php if ((int)($img['is_primary'] ?? 0) === 1): ?><span class="pimg__tag pimg__tag--primary">Main photo</span><?php endif; ?>
          </div>
          <?php if (trim((string)($img['alt_text'] ?? '')) !== ''): ?><div class="pimg__body"><span class="pimg__cap"><?= e((string)$img['alt_text']) ?></span></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="lead-hint mt-2">These photos are live on your listing and managed by All The Venues (which one is the main photo, order and removal). Need one changed or removed? <a href="<?= e(base_url('portal/venues/' . $vid . '/request')) ?>">Request a change</a> and we&rsquo;ll take care of it.</p>
  </div>
<?php endif; ?>

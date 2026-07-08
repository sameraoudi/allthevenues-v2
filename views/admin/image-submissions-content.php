<?php
declare(strict_types=1);

/**
 * #3 U-P7b — Provider photo submissions queue. Built to
 * docs/atv-portal-image-review-preview.html. Expects $groups, int $count,
 * ?int $oldestDays, ?array $flash. Escapes everything; no inline styles/scripts
 * (CSP self-only). Approve/reject forms are CSRF-protected; the server enforces
 * the publish gate + required reject note regardless of the JS enhancements.
 */
/** @var array $groups @var int $count @var ?int $oldestDays @var ?array $flash */
/** @var int $filterVenueId @var string $filterVenueName */
$flash        = $flash ?? null;
$filterVenueId   = $filterVenueId ?? 0;
$filterVenueName = $filterVenueName ?? '';
$classifyOpts = image_submission_classify_options();
$rejectOpts   = image_submission_reject_reasons();
$blocked      = array_diff(array_keys($classifyOpts), venue_images_cleared_statuses()); // gated options
$actionUrl    = base_url('admin/image-submissions');

$fmtSize = static function ($bytes): string {
    $b = (int)$bytes;
    if ($b <= 0) return '';
    if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
    return max(1, (int)round($b / 1024)) . ' KB';
};
$ago = static function (?string $ts): string {
    if (!$ts) return '';
    $t = strtotime((string)$ts); if ($t === false) return '';
    $d = time() - $t;
    if ($d < 3600)  return (int)floor($d / 60) . 'm ago';
    if ($d < 86400) return (int)floor($d / 3600) . 'h ago';
    return (int)floor($d / 86400) . ' days ago';
};
$thumb = static fn(array $img): string => venue_img_src(($img['thumb_path'] ?? '') !== '' ? $img['thumb_path'] : ($img['file_path'] ?? null));
?>
<?php if ($flash): ?>
  <div class="lead-flash lead-flash--<?= e((string)$flash['type']) ?>" role="status"><?= e((string)$flash['msg']) ?></div>
<?php endif; ?>

<?php if ($filterVenueId > 0): /* PU-E (#20) — venue-filtered view (linked from the new-venue review) */ ?>
  <div class="lead-flash lead-flash--info" role="status">
    Showing pending photos for <strong><?= e($filterVenueName) ?></strong> only.
    <a href="<?= e($actionUrl) ?>">&larr; Back to all pending photos</a>
  </div>
<?php endif; ?>

<p class="lead-hint mb-2">Review photos submitted by venue providers. Photos remain hidden from the public site until approved. Provider rights confirmation is recorded on upload, but ATV still reviews each image for quality, suitability, and rights risk before publishing.</p>

<details class="isub-checklist">
  <summary>Review checklist (optional guide)</summary>
  <ul>
    <li>Clear venue view</li>
    <li>No watermark or text overlay</li>
    <li>No visible guests without permission</li>
    <li>Good resolution</li>
    <li>Suitable for public listing</li>
  </ul>
</details>

<div class="isub-kpis">
  <div class="isub-kpi"><span class="isub-kpi__n"><?= (int)$count ?></span><span class="isub-kpi__l">Photos awaiting review</span></div>
  <div class="isub-kpi"><span class="isub-kpi__n"><?= count($groups) ?></span><span class="isub-kpi__l">Venues with submissions</span></div>
  <div class="isub-kpi"><span class="isub-kpi__n"><?= $oldestDays === null ? '—' : (int)$oldestDays ?></span><span class="isub-kpi__l">Oldest waiting<?= $oldestDays === null ? '' : ' (days)' ?></span></div>
</div>

<?php if (!$groups): ?>
  <div class="admin-panel admin-panel--center">
    <p class="text-muted mb-0">No photos are waiting for review. 🎉</p>
  </div>
<?php else: ?>
  <?php foreach ($groups as $g): $v = $g['venue']; ?>
    <div class="admin-panel isub-group">
      <div class="isub-head">
        <div>
          <h2 class="admin-panel__title"><a href="<?= e(base_url('venues/' . $v['slug'])) ?>" target="_blank" rel="noopener"><?= e($v['name']) ?> &#8599;</a></h2>
          <?php if ($v['provider_name'] !== ''): ?><p class="lead-hint mb-0"><?= e($v['provider_name']) ?></p><?php endif; ?>
        </div>
        <?php if ($g['live']): ?>
          <div class="isub-live">
            <span class="lead-hint">On listing now</span>
            <?php foreach (array_slice($g['live'], 0, 5) as $li): ?>
              <img class="isub-live__thumb<?= (int)($li['is_primary'] ?? 0) === 1 ? ' isub-live__thumb--main' : '' ?>" src="<?= e($thumb($li)) ?>" alt="" loading="lazy"<?= (int)($li['is_primary'] ?? 0) === 1 ? ' title="Current main photo"' : '' ?>>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="isub-grid">
        <?php foreach ($g['pending'] as $img): $iid = (int)$img['id'];
          $rby = trim((string)($img['rights_confirmed_by'] ?? ''));
          $rat = $img['rights_confirmed_at'] ?? null;
          $uem = trim((string)($img['uploader_email'] ?? ''));
          $meta = array_filter([
              'WebP',
              ($img['img_width'] && $img['img_height']) ? ((int)$img['img_width'] . ' × ' . (int)$img['img_height']) : '',
              $fmtSize($img['file_size'] ?? 0),
              ($img['created_at'] ?? null) ? ('uploaded ' . $ago($img['created_at'])) : '',
              'Source: provider upload',
          ]);
        ?>
          <div class="isub-card">
            <div class="isub-img">
              <img src="<?= e($thumb($img)) ?>" alt="<?= e((string)($img['alt_text'] ?? '')) ?>" loading="lazy">
              <span class="isub-tag pimg__tag--pending">Pending review</span>
            </div>
            <div class="isub-body">
              <?php if (trim((string)($img['alt_text'] ?? '')) !== ''): ?><div class="isub-cap"><?= e((string)$img['alt_text']) ?></div><?php endif; ?>
              <div><span class="cr-badge">Rights confirmed by provider</span></div>

              <?php if ($rby !== '' || $uem !== ''): ?>
                <div class="isub-rights">
                  <?php if ($rby !== ''): ?>Rights confirmed by <strong><?= e($rby) ?></strong><?= $rat ? ', on ' . e(date('j M Y', strtotime((string)$rat))) : '' ?>.<?php endif; ?>
                  <?php if ($uem !== ''): ?><br><span class="text-muted">Submitted from provider account: <?= e($uem) ?></span><?php endif; ?>
                </div>
              <?php endif; ?>

              <?php if ($meta): ?><div class="isub-meta"><?= e(implode(' · ', $meta)) ?></div><?php endif; ?>

              <div class="isub-decide">
                <form method="post" action="<?= e($actionUrl) ?>" class="isub-approve" data-approve-form>
                  <?php csrf_field(); ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="image_id" value="<?= $iid ?>">
                  <?php if ($filterVenueId > 0): ?><input type="hidden" name="venue_id" value="<?= (int)$filterVenueId ?>"><?php endif; ?>
                  <div class="isub-field">
                    <label for="ps-<?= $iid ?>">Image rights</label>
                    <select id="ps-<?= $iid ?>" name="permission_status" data-classify>
                      <?php foreach ($classifyOpts as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= $k === 'approved_by_provider' ? ' selected' : '' ?><?= in_array($k, $blocked, true) ? ' data-block="1"' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <label class="isub-chk"><input type="checkbox" name="set_primary" value="1" data-set-primary> Set as main photo</label>
                  </div>
                  <div class="isub-actions">
                    <button type="submit" class="atv-btn atv-btn--sm" data-approve-btn
                            data-confirm="Approve this photo? It will go live on the venue listing."
                            data-confirm-main="Approve and set as main photo? This will publish the image and replace the current main photo on the venue listing.">Approve &amp; publish</button>
                    <button type="button" class="atv-btn atv-btn--sm atv-btn--danger" data-reject-toggle="rej-<?= $iid ?>">Reject&hellip;</button>
                  </div>
                  <p class="lead-hint">Approve &amp; publish is blocked when rights are &ldquo;permission required&rdquo; or &ldquo;unknown source.&rdquo;</p>
                </form>

                <form method="post" action="<?= e($actionUrl) ?>" class="isub-reject" id="rej-<?= $iid ?>" data-confirm="Reject this photo? The provider will be notified with the reason and note." hidden>
                  <?php csrf_field(); ?>
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="image_id" value="<?= $iid ?>">
                  <?php if ($filterVenueId > 0): ?><input type="hidden" name="venue_id" value="<?= (int)$filterVenueId ?>"><?php endif; ?>
                  <div class="isub-field">
                    <label for="rr-<?= $iid ?>">Reject reason</label>
                    <select id="rr-<?= $iid ?>" name="reason">
                      <?php foreach ($rejectOpts as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                  </div>
                  <textarea name="note" rows="2" required placeholder="Note to the provider (required) — e.g. There's a logo watermark bottom-right; please re-upload a clean version."></textarea>
                  <div class="isub-actions mt-2">
                    <button type="submit" class="atv-btn atv-btn--sm atv-btn--danger">Confirm reject</button>
                    <button type="button" class="atv-btn atv-btn--sm atv-btn--ghost" data-reject-toggle="rej-<?= $iid ?>">Cancel</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <p class="lead-hint mt-2">Approval publishes the photo, stores the selected rights classification, and optionally sets it as the venue&rsquo;s main image. Rejection keeps the photo off the site, stores the reason and note, and notifies the provider. All decisions are audited. Provider rights confirmation (name + date) is retained either way.</p>
<?php endif; ?>

<?php
declare(strict_types=1);

/**
 * PU-C #10 — shared claim history timeline. Renders the append-only events list
 * (from portal_claim_timeline). Used by the partner claim detail + the admin claim
 * review. Expects $events. Escapes all output; messages/notes as text (nl2br(e)).
 */
/** @var array $events */
$events = $events ?? [];
$meta = [
    'claim_submitted' => ['Claim submitted',                    ''],
    'proof_requested' => ['Proof requested by All The Venues',  'warn'],
    'proof_added'     => ['Proof added',                        'ok'],
    'approved'        => ['Claim approved',                     'ok'],
    'rejected'        => ['Claim declined',                     'rej'],
    'withdrawn'       => ['Claim withdrawn',                    ''],
];
$fmt = static function ($at): string {
    $at = trim((string)$at);
    if ($at === '') { return ''; }
    $t = strtotime($at);
    return $t !== false ? date('j M Y, H:i', $t) : '';
};
$link = static function ($url): string {
    $url = trim((string)$url);
    return preg_match('~^https?://~i', $url) ? $url : '';
};
?>
<ul class="claim-tl">
  <?php foreach ($events as $ev):
    $type = (string)($ev['type'] ?? '');
    [$label, $dot] = $meta[$type] ?? [ucfirst(str_replace('_', ' ', $type)), ''];

    $whenParts = [];
    $w = $fmt($ev['at'] ?? '');
    if ($w !== '') { $whenParts[] = $w; }
    if (trim((string)($ev['actor'] ?? '')) !== '') { $whenParts[] = 'by ' . (string)$ev['actor']; }
    elseif (($ev['by'] ?? '') === 'admin')          { $whenParts[] = 'by All The Venues'; }
    if (trim((string)($ev['role'] ?? '')) !== '')       { $whenParts[] = 'role: ' . (string)$ev['role']; }
    if (trim((string)($ev['work_email'] ?? '')) !== '') { $whenParts[] = (string)$ev['work_email']; }

    $body  = trim((string)($ev['message'] ?? '')) !== '' ? (string)$ev['message'] : (string)($ev['note'] ?? '');
    $body  = trim($body);
    $proof = $link($ev['proof_url'] ?? '');
  ?>
    <li class="claim-tl__i">
      <span class="claim-tl__dot<?= $dot !== '' ? ' claim-tl__dot--' . $dot : '' ?>" aria-hidden="true"></span>
      <div class="claim-tl__t"><?= e($label) ?></div>
      <?php if ($whenParts): ?><div class="claim-tl__when"><?= e(implode(' · ', $whenParts)) ?></div><?php endif; ?>
      <?php if ($body !== '' || $proof !== ''): ?>
        <div class="claim-tl__body">
          <?php if ($body !== ''): ?><?= nl2br(e($body)) ?><?php endif; ?>
          <?php if ($proof !== ''): ?><?php if ($body !== ''): ?><br><?php endif; ?><a href="<?= e($proof) ?>" target="_blank" rel="noopener nofollow"><?= e($proof) ?> &#8599;</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>

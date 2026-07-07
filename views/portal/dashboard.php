<?php
declare(strict_types=1);

/**
 * PU-A1 — Partner portal dashboard (landing). Greeting + five count tiles +
 * "Next steps" (actionable items) + "Recent decisions". The venue table now lives
 * on its own My Venues page. Expects: $counts, $nextSteps, $recent (from dispatch);
 * $me + $orgName are in scope from layout.php. All output escaped.
 */
/** @var array $counts @var array $nextSteps @var array $recent @var array $myVenues @var array $pendingCrs */
$me         = $me ?? (function_exists('auth_user') ? auth_user() : []);
$orgName    = $orgName ?? '';
$counts     = $counts ?? ['managed' => 0, 'pending_review' => 0, 'open_crs' => 0, 'claims' => 0, 'photos' => 0];
$nextSteps  = $nextSteps ?? [];
$recent     = $recent ?? [];
$myVenues   = $myVenues ?? [];
$pendingCrs = $pendingCrs ?? [];

$fullName  = trim((string)($me['name'] ?? ''));
$firstName = $fullName !== '' ? explode(' ', $fullName)[0] : 'there';

$crTypeLabel = static fn(string $t): string => [
    'edit' => 'change request', 'new_venue' => 'venue submission',
    'delist' => 'delisting request', 'claim' => 'claim', 'image' => 'photo review',
][$t] ?? 'request';
$ago = static function (?string $ts): string {
    if (!$ts) return '';
    $t = strtotime((string)$ts);
    if ($t === false) return '';
    $d = time() - $t;
    if ($d < 3600)  return 'just now';
    if ($d < 86400) return (int)floor($d / 3600) . 'h ago';
    return (int)floor($d / 86400) . 'd ago';
};

$tiles = [
    ['n' => $counts['managed'],        'l' => 'Venues managed', 'alert' => false],
    ['n' => $counts['pending_review'], 'l' => 'Pending review',        'alert' => $counts['pending_review'] > 0],
    ['n' => $counts['open_crs'],       'l' => 'Open change requests',  'alert' => $counts['open_crs'] > 0],
    ['n' => $counts['claims'],         'l' => 'Claims pending',        'alert' => $counts['claims'] > 0],
    ['n' => $counts['photos'],         'l' => 'Photos awaiting review','alert' => $counts['photos'] > 0],
];
?>
<div class="pcontent__head">
  <div>
    <h1 class="pcontent__title">Welcome back, <?= e($firstName) ?></h1>
    <p class="pcontent__sub"><?php if ($orgName !== ''): ?><?= e($orgName) ?> &middot; <?php endif; ?>here&rsquo;s where your venues stand.</p>
  </div>
  <a class="atv-btn" href="<?= e(base_url('portal/venues/new')) ?>"><?= icon('plus', 'atv-btn__ico') ?> Add a venue</a>
</div>

<div class="ptiles">
  <?php foreach ($tiles as $t): ?>
    <div class="ptile<?= $t['alert'] ? ' ptile--alert' : '' ?>">
      <div class="ptile__n"><?= e(number_format((int)$t['n'])) ?></div>
      <div class="ptile__l"><?= e($t['l']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="admin-panel">
  <h2 class="admin-panel__title">Next steps</h2>
  <?php if (!$nextSteps): ?>
    <p class="lead-hint mb-0">You&rsquo;re all caught up — nothing needs your attention right now.</p>
  <?php else: ?>
    <ul class="psteps">
      <?php foreach ($nextSteps as $s): ?>
        <li class="psteps__i">
          <span class="psteps__dot" aria-hidden="true"></span>
          <span class="psteps__txt"><strong><?= e((string)$s['name']) ?></strong> &mdash; <?= e((string)$s['text']) ?></span>
          <a class="psteps__act" href="<?= e((string)$s['href']) ?>"><?= $s['action'] /* pre-escaped label */ ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php if ($recent): ?>
  <div class="admin-panel">
    <h2 class="admin-panel__title">Recent decisions</h2>
    <ul class="psteps">
      <?php foreach ($recent as $r):
        $ok = (string)$r['status'] === 'approved';
        $verb = $ok ? 'approved' : 'declined';
      ?>
        <li class="psteps__i">
          <span class="psteps__tick psteps__tick--<?= $ok ? 'ok' : 'no' ?>" aria-hidden="true"><?= $ok ? '&#10003;' : '&times;' ?></span>
          <span class="psteps__txt"><strong><?= e((string)($r['venue_name'] ?? 'A venue')) ?></strong> <?= e($crTypeLabel((string)$r['type'])) ?> <?= e($verb) ?></span>
          <span class="psteps__when"><?= e($ago($r['reviewed_at'] ?? null)) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="admin-panel">
  <div class="lead-detail__head">
    <h2 class="admin-panel__title">My Venues</h2>
    <?php if ($myVenues): ?><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/venues')) ?>">View all</a><?php endif; ?>
  </div>
  <?php require __DIR__ . '/_venues-table.php'; ?>
</div>

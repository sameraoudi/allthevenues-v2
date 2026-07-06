<?php
declare(strict_types=1);

/**
 * #9c — Image-rights "needs review" report. Cross-venue + cover backlog of every
 * image whose permission_status is not cleared. Admin+editor (gated by dispatch).
 * Controller + content in one file: the controller sets up data then re-requires
 * itself as the layout's content view (guarded by a flag). Read-only report;
 * fixing happens in the linked venue/partner editors (#9b controls).
 * Expects $pdo (and $me) in scope from dispatch.
 */

if (!empty($GLOBALS['__img_review_content'])) {
    /* ============================ CONTENT ================================= */
    /** @var array $counts @var array $rows @var array $filters @var int $page @var int $totalPages @var int $total */
    $opts = venue_images_permission_options();
    $permClass = static function (string $s) use ($opts): string {
        if ($s === 'remove_replace') { return 'img-perm--remove'; }
        return !empty($opts[$s][1]) ? 'img-perm--ok' : 'img-perm--review';
    };
    $by      = $counts['by_status'] ?? [];
    $listUrl = base_url('admin/image-review');
    $cur     = (string)($filters['status'] ?? '');
    $sel     = static fn(string $v): string => ($cur === $v) ? ' selected' : '';
    ?>
    <div class="lead-toolbar">
      <div class="lead-toolbar__counts">
        <strong><?= e(number_format((int)($counts['total'] ?? 0))) ?></strong> need review
        · <?= e(number_format((int)($by['public_website_needs_permission'] ?? 0))) ?> from public sites
        · <?= e(number_format((int)($by['remove_replace'] ?? 0))) ?> to remove
      </div>
    </div>

    <form class="lead-filters" method="get" action="<?= e($listUrl) ?>">
      <select name="status" aria-label="Status">
        <option value="">All needing review</option>
        <?php foreach ($opts as $k => $opt): if (!empty($opt[1])) continue; /* skip cleared */ ?>
          <option value="<?= e($k) ?>"<?= $sel($k) ?>><?= e($opt[0]) ?> (<?= (int)($by[$k] ?? 0) ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="atv-btn atv-btn--sm">Filter</button>
      <?php if ($cur !== ''): ?><a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e($listUrl) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if (!$rows): ?>
      <div class="admin-panel admin-panel--center">
        <p class="text-muted mb-0">Nothing needs review here. 🎉</p>
      </div>
    <?php else: ?>
      <div class="lead-table-wrap">
        <table class="lead-table">
          <thead>
            <tr><th></th><th>Item</th><th>Type</th><th>Status</th><th>Source</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $kind   = (string)$r['kind'];
              $status = (string)$r['status'];
              $thumbRel = ($r['thumb'] ?? '') !== '' ? (string)$r['thumb'] : (string)($r['full'] ?? '');
              $thumb  = $kind === 'venue' ? venue_img_src($thumbRel) : base_url($thumbRel);
              $fixUrl = $kind === 'venue'
                  ? base_url('admin/venues/edit?id=' . (int)$r['entity_id'])
                  : base_url('admin/partners/edit?id=' . (int)$r['entity_id']);
            ?>
              <tr>
                <td data-label=""><img class="ir-thumb" src="<?= e($thumb) ?>" alt="" loading="lazy"></td>
                <td data-label="Item"><strong><?= e((string)$r['entity_name']) ?></strong></td>
                <td data-label="Type"><span class="lead-mode lead-mode--<?= $kind === 'venue' ? 'edit' : 'claim' ?>"><?= $kind === 'venue' ? 'Venue image' : 'Provider cover' ?></span></td>
                <td data-label="Status"><span class="img-perm <?= e($permClass($status)) ?>"><?= e($opts[$status][0] ?? $status) ?></span></td>
                <td data-label="Source"><?= ($r['source'] ?? '') !== '' ? e((string)$r['source']) : '<span class="text-muted">—</span>' ?></td>
                <td data-label=""><a class="atv-btn atv-btn--sm" href="<?= e($fixUrl) ?>">Fix</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php
        $pg_page  = $page; $pg_total = $totalPages;
        $carry    = $cur !== '' ? ['status' => $cur] : [];
        $pg_href  = static fn(int $i): string => $listUrl . query_string($carry + ['page' => $i]);
        require __DIR__ . '/../partials/admin-pager.php';
      ?>
    <?php endif; ?>
    <?php
    return;
}

/* ============================ CONTROLLER ================================== */
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/venue_images_admin.php';
require_once __DIR__ . '/../../lib/image_review_admin.php';

$counts  = image_review_counts($pdo);
$filters = ['status' => trim((string)($_GET['status'] ?? ''))];
$perPage = 30;
$page    = max(1, (int)($_GET['page'] ?? 1));
$res     = image_review_list($pdo, $filters, $perPage, ($page - 1) * $perPage);
$rows    = $res['rows'];
$total   = $res['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

$admin_active       = 'image-review';
$page_title         = 'Image review — Admin';
$admin_page_title   = 'Image review';
$GLOBALS['__img_review_content'] = true;
$admin_content_view = __FILE__;
require __DIR__ . '/layout.php';

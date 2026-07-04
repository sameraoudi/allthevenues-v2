<?php
declare(strict_types=1);

/**
 * Reusable windowed pager for admin lists.
 * Expects in scope:
 *   int      $pg_page   current page (1-based)
 *   int      $pg_total  total pages
 *   callable $pg_href   fn(int $page): string — RAW url for a page (this partial e()-escapes)
 * Renders nothing when there is a single page. Window = 2 pages each side.
 */
/** @var int $pg_page @var int $pg_total @var callable $pg_href */

if (($pg_total ?? 1) <= 1) { return; }

$start = max(1, $pg_page - 2);
$end   = min($pg_total, $pg_page + 2);
?>
<nav class="lead-pagination" aria-label="Pages">
  <?php if ($pg_page > 1): ?>
    <a class="lead-page lead-page--nav" href="<?= e($pg_href(1)) ?>">&laquo; First</a>
    <a class="lead-page lead-page--nav" href="<?= e($pg_href($pg_page - 1)) ?>">&lsaquo; Prev</a>
  <?php endif; ?>

  <?php if ($start > 1): ?>
    <a class="lead-page" href="<?= e($pg_href(1)) ?>">1</a>
    <?php if ($start > 2): ?><span class="lead-pagination__gap" aria-hidden="true">&hellip;</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $start; $i <= $end; $i++): ?>
    <a class="lead-page<?= $i === $pg_page ? ' is-active' : '' ?>"
       href="<?= e($pg_href($i)) ?>"<?= $i === $pg_page ? ' aria-current="page"' : '' ?>><?= e((string)$i) ?></a>
  <?php endfor; ?>

  <?php if ($end < $pg_total): ?>
    <?php if ($end < $pg_total - 1): ?><span class="lead-pagination__gap" aria-hidden="true">&hellip;</span><?php endif; ?>
    <a class="lead-page" href="<?= e($pg_href($pg_total)) ?>"><?= e((string)$pg_total) ?></a>
  <?php endif; ?>

  <?php if ($pg_page < $pg_total): ?>
    <a class="lead-page lead-page--nav" href="<?= e($pg_href($pg_page + 1)) ?>">Next &rsaquo;</a>
    <a class="lead-page lead-page--nav" href="<?= e($pg_href($pg_total)) ?>">Last &raquo;</a>
  <?php endif; ?>

  <span class="lead-pagination__info">Page <?= e((string)$pg_page) ?> of <?= e((string)$pg_total) ?></span>
</nav>

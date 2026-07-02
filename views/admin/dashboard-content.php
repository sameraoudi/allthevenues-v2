<?php
declare(strict_types=1);

/** Dashboard content. Expects $stats (array of ints). */
/** @var array $stats */
$cards = [
    ['label' => 'Total enquiries',  'value' => $stats['enquiries_total'],  'icon' => 'inbox',    'href' => base_url('admin/enquiries')],
    ['label' => 'New enquiries',    'value' => $stats['enquiries_new'],    'icon' => 'sparkles', 'href' => base_url('admin/enquiries')],
    ['label' => 'Published venues', 'value' => $stats['venues_published'], 'icon' => 'building', 'href' => base_url('admin/venues')],
    ['label' => 'Partners',         'value' => $stats['partners_total'],   'icon' => 'users',    'href' => base_url('admin/partners')],
];
?>
<div class="admin-stats">
  <?php foreach ($cards as $c): ?>
    <a class="stat-card" href="<?= e($c['href']) ?>">
      <span class="stat-card__icon"><?= icon($c['icon']) ?></span>
      <span class="stat-card__value"><?= e(number_format((int)$c['value'])) ?></span>
      <span class="stat-card__label"><?= e($c['label']) ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="admin-panel">
  <h2 class="admin-panel__title">Welcome back</h2>
  <p class="text-muted">Enquiry management, venue moderation, and partner tools arrive in the next units. The figures above are live from the database.</p>
</div>

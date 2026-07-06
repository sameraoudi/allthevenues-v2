<?php
declare(strict_types=1);

/**
 * Admin shell layout — left sidebar + top bar + content. Used by gated admin
 * pages. Expects in scope:
 *   string $page_title, $admin_page_title, $admin_active, $admin_content_view.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/icons.php';
require_once __DIR__ . '/../../lib/auth.php';

$page_title       = $page_title       ?? 'Admin — All The Venues';
$admin_page_title = $admin_page_title ?? 'Admin';
$admin_active     = $admin_active     ?? 'dashboard';
$me               = auth_current_user();
$roleLabel        = auth_role_label((string)($me['role'] ?? ''));

// New-enquiry count for the sidebar badge (cheap; ignore errors).
$newCount = 0;
try {
    $newCount = (int)db_pdo()->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn();
} catch (Throwable $e) {
    $newCount = 0;
}

// Pending change-request count (#3 U-P5b) — admin nav badge; admin-only route.
$crPending = 0;
if (auth_can('change_requests.manage')) {
    require_once __DIR__ . '/../../lib/change_request_admin.php';
    $crPending = cr_admin_pending_count(db_pdo());
}

// Image-rights needs-review count (#9c) — staff nav badge.
$imgReview = 0;
if (auth_can('venues.manage')) {
    require_once __DIR__ . '/../../lib/image_review_admin.php';
    try { $imgReview = (int)(image_review_counts(db_pdo())['total'] ?? 0); } catch (Throwable $e) { $imgReview = 0; }
}

// Role-aware nav: each item carries the capability required to see it. Items
// the current role can't reach are hidden (and the routes are server-gated too).
$nav = [
    'dashboard' => ['label' => 'Dashboard', 'url' => base_url('admin'),           'icon' => 'grid',     'cap' => null],
    'enquiries' => ['label' => 'Enquiries', 'url' => base_url('admin/enquiries'), 'icon' => 'inbox',    'cap' => 'enquiries.manage', 'badge' => $newCount],
    'venues'    => ['label' => 'Venues',    'url' => base_url('admin/venues'),    'icon' => 'building', 'cap' => 'venues.manage'],
    'partners'  => ['label' => 'Providers', 'url' => base_url('admin/partners'),  'icon' => 'users',    'cap' => 'providers.manage'],
    'reports'   => ['label' => 'Reports',   'url' => base_url('admin/reports'),   'icon' => 'chart',    'cap' => 'enquiries.manage'],
    'change-requests' => ['label' => 'Change Requests', 'url' => base_url('admin/change-requests'), 'icon' => 'inbox', 'cap' => 'change_requests.manage', 'badge' => $crPending],
    'image-review' => ['label' => 'Image Review', 'url' => base_url('admin/image-review'), 'icon' => 'building', 'cap' => 'venues.manage', 'badge' => $imgReview],
    'users'     => ['label' => 'Users',     'url' => base_url('admin/users'),     'icon' => 'shield',   'cap' => 'users.manage'],
    'settings'  => ['label' => 'Settings',  'url' => base_url('admin/settings'),  'icon' => 'settings', 'cap' => 'settings.manage'],
];
$nav = array_filter($nav, static fn($item) => empty($item['cap']) || auth_can($item['cap']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($page_title) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/brand/favicon_app_icon/favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/brand.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="admin-body">
  <div class="admin-shell">
    <aside class="admin-sidebar">
      <a class="admin-brand" href="<?= e(base_url('admin')) ?>">
        <img src="<?= e(base_url('assets/brand/web_exports/icon_light/icon_light_512x512.png')) ?>"
             alt="" width="512" height="512" aria-hidden="true">
        <span>Admin</span>
      </a>
      <nav class="admin-nav" aria-label="Admin">
        <?php foreach ($nav as $key => $item): ?>
          <a class="admin-nav__item<?= $admin_active === $key ? ' is-active' : '' ?>" href="<?= e($item['url']) ?>">
            <?= icon($item['icon']) ?><span><?= e($item['label']) ?></span>
            <?php if (!empty($item['badge'])): ?><span class="admin-nav__badge"><?= e((string)$item['badge']) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </aside>

    <div class="admin-main">
      <header class="admin-topbar">
        <h1 class="admin-topbar__title"><?= e($admin_page_title) ?></h1>
        <div class="admin-topbar__right">
          <span class="admin-topbar__user">
            <span class="admin-topbar__name"><?= e($me['name'] ?: 'Admin') ?></span>
            <?php if ($roleLabel !== ''): ?><span class="admin-topbar__role">· <?= e($roleLabel) ?></span><?php endif; ?>
          </span>
          <a class="atv-btn atv-btn--sm" href="<?= e(base_url('admin/logout')) ?>"><?= icon('logout') ?> Logout</a>
        </div>
      </header>

      <main class="admin-content">
        <?php
        if (isset($admin_content_view) && is_file($admin_content_view)) {
            require $admin_content_view;
        }
        ?>
      </main>
    </div>
  </div>
  <script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>

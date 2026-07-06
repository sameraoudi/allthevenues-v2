<?php
declare(strict_types=1);

/**
 * #3 U-P3 — Provider portal chrome (simplified admin shell). A top bar (brand →
 * /portal, signed-in provider name, Sign out) + a content container. Every portal
 * page is noindex. Self-hosted assets only (no CDN). Expects in scope:
 *   string $portal_content_view (required), $page_title, ?string $portal_active.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/icons.php';
require_once __DIR__ . '/../../lib/auth.php';

$page_title = $page_title ?? 'Provider Portal — All The Venues';
$robots     = 'noindex, nofollow';   // the portal is never indexed
$me         = auth_user();
$portal_content_view = $portal_content_view ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= e($robots) ?>">
    <title><?= e($page_title) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/brand/favicon_app_icon/favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/brand.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="admin-body">
  <header class="portal-topbar">
    <a class="portal-brand" href="<?= e(base_url('portal')) ?>">All The <span>Venues</span> <em>· Provider</em></a>
    <div class="portal-topbar__right">
      <span class="portal-user"><?= e($me['name'] ?? 'Provider') ?></span>
      <a class="atv-btn atv-btn--sm atv-btn--ghost" href="<?= e(base_url('portal/logout')) ?>"><?= icon('logout') ?> Sign out</a>
    </div>
  </header>

  <main class="portal-main atv-wrap">
    <?php
    if ($portal_content_view !== null && is_file($portal_content_view)) {
        require $portal_content_view;
    }
    ?>
  </main>

  <script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>

<?php
declare(strict_types=1);

/**
 * PU-A1 — Partner portal shell: a two-column app layout (navy left sidebar + main
 * content + minimal footer), built to docs/atv-portal-shell-preview.html. The
 * sidebar owns nav (Dashboard · My Venues · Add Venue · Claims) with active
 * highlighting ($portal_active) and count pills, plus the signed-in partner block
 * and Sign out. Every portal page is noindex. Self-hosted assets only; no inline
 * styles/scripts (classes in brand.css, the mobile toggle in app.js). Expects:
 *   string $portal_content_view (required), $page_title, ?string $portal_active.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/icons.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/portal.php';

$page_title = $page_title ?? 'Partner Portal — All The Venues';
$robots     = 'noindex, nofollow';   // the portal is never indexed
$me         = auth_user();
$active     = $portal_active ?? '';
$portal_content_view = $portal_content_view ?? null;

// Sidebar context: partner org name + nav pill counts (owns its own queries).
$partnerId = (int)($me['partner_id'] ?? 0);
$orgName   = '';
$navVenues = 0;
$navClaims = 0;
if ($partnerId > 0) {
    try {
        $pdoNav = db_pdo();
        $os = $pdoNav->prepare('SELECT org_name FROM partners WHERE id = :id LIMIT 1');
        $os->execute([':id' => $partnerId]);
        $orgName   = (string)($os->fetchColumn() ?: '');
        $navVenues = portal_owned_venue_count($pdoNav, $partnerId);
        $navClaims = portal_open_claims_count($pdoNav, $partnerId);
    } catch (Throwable $e) {
        error_log('portal shell nav counts failed: ' . $e->getMessage());
    }
}

$nav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => base_url('portal'),            'ico' => 'grid'],
    ['key' => 'venues',    'label' => 'My Venues',  'href' => base_url('portal/venues'),     'ico' => 'building', 'pill' => $navVenues],
    ['key' => 'add',       'label' => 'Add Venue',  'href' => base_url('portal/venues/new'), 'ico' => 'plus'],
    ['key' => 'claims',    'label' => 'Claims',     'href' => base_url('portal/claim'),      'ico' => 'shield',   'pill' => $navClaims],
    ['key' => 'guide',     'label' => 'Guide',      'href' => base_url('portal/guide'),      'ico' => 'info-circle'],
    ['key' => 'account',   'label' => 'Account',    'href' => base_url('portal/account'),    'ico' => 'settings'],
];
$year = (int)date('Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= e($robots) ?>">
    <title><?= e($page_title) ?></title>
    <link rel="icon" href="<?= e(asset_url('assets/brand/favicon_app_icon/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= e(asset_url('assets/brand/favicon_app_icon/favicon_32x32.png')) ?>" sizes="32x32">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset_url('assets/brand/favicon_app_icon/favicon_180x180.png')) ?>">
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?= e(base_url('assets/fonts/cormorant-garamond-latin.woff2')) ?>" crossorigin>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/brand.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
</head>
<body class="admin-body portal-shell-body">
  <div class="pshell" data-portal-shell>
    <aside class="pside" id="portalNav">
      <div class="pside__brand">
        <a class="pside__mark" href="<?= e(base_url('portal')) ?>" aria-label="All The Venues — Partner Portal">
          <img class="pside__icon" src="<?= e(base_url('assets/brand/web_exports/icon_light/icon_light_512x512.png')) ?>" alt="" width="512" height="512" aria-hidden="true">
          <span class="pside__word">All The <span class="pside__accent">Venues</span></span>
        </a>
        <span class="pside__tag">Partner Portal</span>
      </div>

      <nav class="pside__nav" aria-label="Portal">
        <?php foreach ($nav as $item): ?>
          <a class="pside__link<?= $active === $item['key'] ? ' is-active' : '' ?>" href="<?= e($item['href']) ?>"<?= $active === $item['key'] ? ' aria-current="page"' : '' ?>>
            <?= icon($item['ico'], 'pside__ico') ?>
            <span class="pside__label"><?= e($item['label']) ?></span>
            <?php if (isset($item['pill']) && (int)$item['pill'] > 0): ?><span class="pside__pill"><?= (int)$item['pill'] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <div class="pside__who">
        <b><?= e((string)($me['name'] ?? 'Partner')) ?></b>
        <?php if ($orgName !== ''): ?><span class="pside__org"><?= e($orgName) ?></span><?php endif; ?>
        <a class="pside__signout" href="<?= e(base_url('portal/logout')) ?>"><?= icon('logout', 'pside__ico') ?> Sign out</a>
      </div>
    </aside>

    <div class="pmain">
      <header class="ptopbar">
        <button class="ptopbar__toggle" type="button" aria-label="Toggle menu" data-portal-nav-toggle aria-controls="portalNav" aria-expanded="false"><?= icon('menu') ?></button>
        <a class="ptopbar__brand" href="<?= e(base_url('portal')) ?>">All The <span>Venues</span> <em>Partner Portal</em></a>
      </header>

      <main class="pcontent">
        <?php
        if ($portal_content_view !== null && is_file($portal_content_view)) {
            require $portal_content_view;
        }
        ?>
      </main>

      <footer class="pfoot">
        <span>&copy; <?= e((string)$year) ?> All The Venues &middot; Partner Portal</span>
        <span class="pfoot__links">
          <a href="<?= e(base_url('privacy-policy')) ?>">Privacy Policy</a>
          <a href="<?= e(base_url('terms-of-use')) ?>">Terms of Use</a>
          <a href="<?= e(base_url('contact')) ?>">Contact</a>
        </span>
      </footer>
    </div>
  </div>

  <script defer src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>

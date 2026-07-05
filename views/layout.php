<?php
declare(strict_types=1);

/**
 * Base HTML layout. Views set $page_title and $content_view, then
 * require this file. Bootstrap 5 is self-hosted under /assets.
 *
 * Expected in scope:
 *   string $page_title    Page <title> (plain text, escaped here).
 *   string $content_view  Absolute path to the view partial to render.
 */

$page_title   = $page_title   ?? 'All The Venues';
$content_view = $content_view ?? null;
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/icons.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <?php
      $meta_description = $meta_description ?? 'Discover and enquire about curated UAE event venues — weddings, corporate events, conferences and celebrations — through one simple, managed enquiry.';
      $canonical        = $canonical ?? base_url(ltrim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/'));
      // Staging must never be indexed (duplicate-content risk); the apex keeps
      // per-page overrides. Host-gated so no hardcoded noindex reaches production.
      $isStagingHost = str_starts_with(strtolower((string)($_SERVER['HTTP_HOST'] ?? '')), 'staging.');
      $robots           = $isStagingHost ? 'noindex, nofollow' : ($robots ?? 'index, follow');
      $og_title         = $og_title ?? $page_title;
      $og_description   = $og_description ?? $meta_description;
      $og_image         = $og_image ?? base_url('assets/brand/og_social/atv_og_deep_navy_1200x630.png');
    ?>
    <meta name="description" content="<?= e($meta_description) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="All The Venues">
    <meta property="og:title" content="<?= e($og_title) ?>">
    <meta property="og:description" content="<?= e($og_description) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($og_image) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($og_title) ?>">
    <meta name="twitter:description" content="<?= e($og_description) ?>">
    <meta name="twitter:image" content="<?= e($og_image) ?>">
    <link rel="icon" href="<?= e(base_url('assets/brand/favicon_app_icon/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= e(base_url('assets/brand/favicon_app_icon/atv_app_icon_deep_navy_32x32.png')) ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= e(base_url('assets/brand/favicon_app_icon/atv_app_icon_deep_navy_180x180.png')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/brand.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">

    <?php require __DIR__ . '/partials/header.php'; ?>

    <main class="flex-grow-1">
        <?php
        if ($content_view !== null && is_file($content_view)) {
            require $content_view;
        }
        ?>
    </main>

    <?php require __DIR__ . '/partials/footer.php'; ?>

    <script src="<?= e(base_url('assets/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>

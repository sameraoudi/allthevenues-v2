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

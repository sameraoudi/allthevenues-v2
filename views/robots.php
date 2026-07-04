<?php
declare(strict_types=1);

/**
 * Dynamic /robots.txt — disallows /admin and points crawlers at the sitemap
 * via base_url() (domain-agnostic, so it's correct on staging and the apex).
 */

require_once __DIR__ . '/../lib/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin\n";
echo "\n";
echo 'Sitemap: ' . base_url('sitemap.xml') . "\n";
exit;

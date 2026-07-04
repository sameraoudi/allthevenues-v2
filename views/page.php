<?php
declare(strict_types=1);

/**
 * Static info/legal page handler (#7a). Renders the four first-party content
 * pages from docs/legal/*.md into clean semantic HTML via a shared prose style.
 *
 * Routes here: /about, /terms-of-use, /privacy-policy, /cookie-policy.
 * Unknown slugs → 404. Indexable (default robots: index, follow).
 */

require_once __DIR__ . '/../lib/helpers.php';

/** Inline markdown: escape, then apply links / **bold** / *italic*. */
if (!function_exists('legal_inline')) {
    function legal_inline(string $text): string
    {
        $t = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        // [label](/internal or https://external)
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', static function (array $m): string {
            $label = $m[1];                                   // already escaped
            $url   = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            $href  = ($url !== '' && $url[0] === '/') ? base_url(ltrim($url, '/')) : $url;
            return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">' . $label . '</a>';
        }, $t);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);   // bold first
        $t = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $t);              // then italic
        return $t;
    }
}

/**
 * Convert a trusted first-party markdown file to HTML: h1/h2/h3, paragraphs
 * (single newlines → <br>), unordered lists, inline formatting + links.
 * Placeholders like [Insert date] are preserved verbatim (they aren't links).
 */
if (!function_exists('render_legal_md')) {
    function render_legal_md(string $path): string
    {
        $src = @file_get_contents($path);
        if ($src === false) { return ''; }
        $src = str_replace("\r\n", "\n", $src);

        // Group into blocks separated by blank lines.
        $blocks = [];
        $cur = [];
        foreach (explode("\n", $src) as $ln) {
            if (trim($ln) === '') {
                if ($cur) { $blocks[] = $cur; $cur = []; }
            } else {
                $cur[] = $ln;
            }
        }
        if ($cur) { $blocks[] = $cur; }

        $html = '';
        foreach ($blocks as $block) {
            $first = $block[0];
            if (strncmp($first, '### ', 4) === 0) {
                $html .= '<h3>' . legal_inline(substr($first, 4)) . "</h3>\n";
            } elseif (strncmp($first, '## ', 3) === 0) {
                $html .= '<h2>' . legal_inline(substr($first, 3)) . "</h2>\n";
            } elseif (strncmp($first, '# ', 2) === 0) {
                $html .= '<h1>' . legal_inline(substr($first, 2)) . "</h1>\n";
            } else {
                $isList = true;
                foreach ($block as $ln) { if (strncmp(ltrim($ln), '- ', 2) !== 0) { $isList = false; break; } }
                if ($isList) {
                    $html .= "<ul>\n";
                    foreach ($block as $ln) {
                        $html .= '<li>' . legal_inline(ltrim(ltrim($ln), '- ')) . "</li>\n";
                    }
                    $html .= "</ul>\n";
                } else {
                    $parts = array_map('legal_inline', $block);
                    $html .= '<p>' . implode('<br>', $parts) . "</p>\n";
                }
            }
        }
        return $html;
    }
}

$slug = trim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');

$pages = [
    'about' => [
        'title' => 'About All The Venues — All The Venues',
        'meta'  => 'All The Venues is a curated UAE venue discovery and enquiry platform — browse venues, shortlist favourites, and send one managed enquiry.',
        'view'  => __DIR__ . '/content/about.php',
    ],
    'terms-of-use' => [
        'title' => 'Terms of Use — All The Venues',
        'meta'  => 'The terms governing your use of allthevenues.com — venue information, enquiries, provider submissions, content licence, and liability.',
        'view'  => __DIR__ . '/content/terms.php',
    ],
    'privacy-policy' => [
        'title' => 'Privacy Policy — All The Venues',
        'meta'  => 'How All The Venues collects, uses, shares, and protects your personal information when you browse venues and submit enquiries.',
        'view'  => __DIR__ . '/content/privacy.php',
    ],
    'cookie-policy' => [
        'title' => 'Cookie Notice — All The Venues',
        'meta'  => 'How All The Venues uses cookies and local storage — essential session, shortlist storage, spam protection, and privacy-friendly analytics.',
        'view'  => __DIR__ . '/content/cookie.php',
    ],
];

if (!isset($pages[$slug])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$page_title       = $pages[$slug]['title'];
$meta_description = $pages[$slug]['meta'];
$content_view     = $pages[$slug]['view'];
require __DIR__ . '/layout.php';

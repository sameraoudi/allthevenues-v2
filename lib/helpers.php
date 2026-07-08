<?php
declare(strict_types=1);

/**
 * Small view/request helpers for All The Venues.
 * No side effects on include; pure functions only.
 */

if (!function_exists('e')) {
    /**
     * HTML-escape for output. Always use when echoing untrusted data
     * into markup. ENT_QUOTES escapes both single and double quotes.
     */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    /**
     * Return the site base URL (no trailing slash), optionally with a
     * path appended. Reads BASE_URL from config.php when available;
     * falls back to deriving from the current request.
     */
    function base_url(string $path = ''): string
    {
        $base = defined('BASE_URL') ? BASE_URL : '';

        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }

        $base = rtrim($base, '/');
        if ($path === '') {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('app_path')) {
    /** Absolute filesystem path inside the app docroot. */
    function app_path(string $rel = ''): string
    {
        $root = dirname(__DIR__);
        return $rel === '' ? $root : $root . '/' . ltrim($rel, '/');
    }
}

if (!function_exists('asset_url')) {
    /**
     * Cache-busted URL for a bundled static asset: base_url($rel) plus a
     * ?v=<filemtime> query so a changed file invalidates the year-long browser
     * cache (see .htaccess Perf-1). Falls back to ASSET_VERSION (or '1') when the
     * mtime is unavailable. Use for CSS/JS; fonts/images stay unversioned under
     * the long TTL (their preload/@font-face URLs must match, so no ?v=).
     */
    function asset_url(string $rel): string
    {
        $mtime = @filemtime(app_path($rel));
        $ver   = $mtime !== false ? (string)$mtime
               : (defined('ASSET_VERSION') ? (string)ASSET_VERSION : '1');
        return base_url($rel) . '?v=' . $ver;
    }
}

if (!function_exists('venue_img_src')) {
    /**
     * URL for a migrated media path, with graceful fallback.
     * Falls back to the bundled placeholder when the path is empty OR the
     * file is not present on disk (e.g. before the media copy runs, or a
     * missing record). Served from 'self' — CSP-safe.
     */
    function venue_img_src(?string $relPath): string
    {
        $relPath = trim((string)$relPath);
        if ($relPath !== '' && is_file(app_path($relPath))) {
            return base_url($relPath);
        }
        return base_url('assets/img/venue-placeholder.svg');
    }
}

if (!function_exists('tags_from')) {
    /**
     * Split a comma/newline-separated string into a list of trimmed,
     * de-duplicated, non-empty tags. Tags are plain text (escape on output).
     */
    function tags_from(?string $value, int $limit = 0): array
    {
        $value = strip_tags((string)$value);
        $parts = preg_split('/[,\r\n]+/', $value) ?: [];
        $tags = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '' && !in_array($p, $tags, true)) {
                $tags[] = $p;
            }
        }
        return ($limit > 0) ? array_slice($tags, 0, $limit) : $tags;
    }
}

if (!function_exists('snippet')) {
    /** Plain-text snippet from (possibly HTML) content, truncated on a word. */
    function snippet(?string $html, int $max = 140): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$html)) ?? '');
        if ($text === '' || mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max);
        $sp = mb_strrpos($cut, ' ');
        if ($sp !== false && $sp > 0) {
            $cut = mb_substr($cut, 0, $sp);
        }
        return $cut . '…';
    }
}

if (!function_exists('query_string')) {
    /**
     * Build a URL query string from a params map (page/filters), skipping
     * empty values. http_build_query URL-encodes keys+values; the caller
     * still e()s the whole attribute value in HTML.
     */
    function query_string(array $params): string
    {
        $clean = array_filter(
            $params,
            static fn($v) => $v !== '' && $v !== null
        );
        return $clean ? '?' . http_build_query($clean) : '';
    }
}

if (!function_exists('redirect')) {
    /**
     * Send a redirect and stop. Relative paths are resolved against
     * base_url(); absolute URLs are passed through.
     */
    function redirect(string $to, int $status = 302): never
    {
        $url = preg_match('#^https?://#i', $to) ? $to : base_url($to);
        header('Location: ' . $url, true, $status);
        exit;
    }
}

if (!function_exists('slugify')) {
    /** URL slug: lowercase, non-alnum → '-', trimmed. */
    function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}

if (!function_exists('portal_enabled')) {
    /** #3 provider portal dark-launch flag. Undefined ⇒ OFF (prod stays dark until launch). */
    function portal_enabled(): bool
    {
        return defined('PORTAL_ENABLED') && PORTAL_ENABLED === true;
    }
}

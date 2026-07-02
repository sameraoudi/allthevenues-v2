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

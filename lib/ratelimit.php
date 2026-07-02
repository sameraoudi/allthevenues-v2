<?php
declare(strict_types=1);

/**
 * Lightweight file-based rate limiter (no DB table required).
 *
 * Sliding-window per-key counters stored as JSON under storage/ratelimit/.
 * Used to throttle public enquiry submissions per-IP and per-email.
 *
 * Fails OPEN: if the storage dir is unwritable, we log and allow — a broken
 * limiter must not block legitimate enquiries (the platform's core action).
 */

/** Real client IP (site is direct-to-origin on LiteSpeed; REMOTE_ADDR trusted). */
function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'unknown';
    }
    return $ip;
}

function _ratelimit_dir(): string
{
    return dirname(__DIR__) . '/storage/ratelimit';
}

/**
 * Record a hit and report whether it is allowed under (limit, windowSeconds).
 * Returns true if allowed (under the limit), false if the limit is exceeded.
 * The hit is always recorded when allowed.
 */
function ratelimit_hit(string $action, string $key, int $limit, int $windowSeconds): bool
{
    $dir = _ratelimit_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    $bucket = $dir . '/' . sha1($action . '|' . strtolower(trim($key))) . '.json';
    $now    = time();
    $cutoff = $now - $windowSeconds;

    $fh = @fopen($bucket, 'c+');
    if ($fh === false) {
        error_log("ratelimit: cannot open bucket for $action (failing open)");
        return true;   // fail open
    }
    try {
        @flock($fh, LOCK_EX);
        $raw   = stream_get_contents($fh) ?: '';
        $times = json_decode($raw, true);
        if (!is_array($times)) {
            $times = [];
        }
        // Prune outside the window.
        $times = array_values(array_filter($times, static fn($t) => is_int($t) && $t > $cutoff));

        if (count($times) >= $limit) {
            return false;   // over the limit — do not record
        }
        $times[] = $now;

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($times));
        fflush($fh);
        return true;
    } finally {
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}

<?php
declare(strict_types=1);

/**
 * Cloudflare Turnstile — anti-bot for the public enquiry form.
 *
 * Keys come from config/config.php (TURNSTILE_SITE_KEY / TURNSTILE_SECRET_KEY).
 * Fails CLOSED: if verification can't confirm success, the submit is rejected.
 *
 * Dev convenience: when the secret is a Cloudflare test key (starts with "1x",
 * "always passes"), server-side verify auto-passes WITHOUT a network call so
 * local testing is deterministic and offline-safe. Real keys always hit the
 * siteverify API.
 */

require_once __DIR__ . '/../config/config.php';

const TURNSTILE_VERIFY_URL  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
const TURNSTILE_API_TIMEOUT = 5;

function turnstile_enabled(): bool
{
    return defined('TURNSTILE_SITE_KEY') && TURNSTILE_SITE_KEY !== ''
        && defined('TURNSTILE_SECRET_KEY') && TURNSTILE_SECRET_KEY !== '';
}

function turnstile_site_key(): string
{
    return defined('TURNSTILE_SITE_KEY') ? (string)TURNSTILE_SITE_KEY : '';
}

/** Widget markup for inside the form (no-op if disabled). */
function turnstile_field(): string
{
    if (!turnstile_enabled()) {
        return '';
    }
    $key = htmlspecialchars(turnstile_site_key(), ENT_QUOTES, 'UTF-8');
    return '<div class="cf-turnstile" data-sitekey="' . $key . '"></div>';
}

/** The Turnstile API script tag (challenges.cloudflare.com — CSP-allowed). */
function turnstile_script_tag(): string
{
    if (!turnstile_enabled()) {
        return '';
    }
    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/**
 * Verify the submitted token. Returns true iff Cloudflare confirms success.
 * Fails closed on any error. Auto-passes for the "1x" test secret (dev).
 */
function turnstile_verify(string $token, ?string $remoteIp = null): bool
{
    if (!turnstile_enabled()) {
        // No keys configured → treat as not-required (e.g. before setup).
        return true;
    }

    $secret = (string)TURNSTILE_SECRET_KEY;
    // Cloudflare "always passes" test secret — skip the network call in dev.
    if (str_starts_with($secret, '1x')) {
        return $token !== '';
    }

    if ($token === '') {
        return false;
    }

    $remoteIp = $remoteIp ?? ($_SERVER['REMOTE_ADDR'] ?? '');
    $payload  = http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $remoteIp,
    ]);

    $ch = curl_init(TURNSTILE_VERIFY_URL);
    if ($ch === false) {
        error_log('turnstile_verify: curl_init failed');
        return false;
    }
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, TURNSTILE_API_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, TURNSTILE_API_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        error_log("turnstile_verify network error: http=$code curl=$err");
        return false;
    }
    $result = json_decode((string)$body, true);
    if (!is_array($result)) {
        error_log('turnstile_verify malformed JSON');
        return false;
    }
    return ($result['success'] ?? false) === true;
}

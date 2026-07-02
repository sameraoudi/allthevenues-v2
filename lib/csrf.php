<?php
declare(strict_types=1);

/**
 * CSRF token helper for All The Venues.
 *
 * Per-session single token. Session-based, no DB. Ported from
 * sameraoudi.com's proven implementation.
 *
 * Session storage key: $_SESSION['csrf_token']
 * POST field name:     csrf_token
 *
 * This helper does NOT call session_start(). Callers must ensure a
 * session is active before invoking these functions (index.php does).
 * csrf_token()/csrf_regenerate() raise E_USER_ERROR without a session;
 * csrf_validate() fails closed (returns false).
 */

/**
 * Return the current session's CSRF token, generating one if absent.
 * Token format: 64-character hex string (32 random bytes encoded).
 * Idempotent within a session.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        trigger_error(
            'csrf_token() called without an active session. '
            . 'Caller must invoke session_start() before using CSRF helpers.',
            E_USER_ERROR
        );
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Echo a hidden CSRF input element. Convenience wrapper for templates.
 * Output: <input type="hidden" name="csrf_token" value="{token}">
 */
function csrf_field(): void
{
    echo '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

/**
 * Validate the submitted CSRF token against the session token.
 * Returns true iff $_POST['csrf_token'] is present, a non-empty string,
 * and matches $_SESSION['csrf_token']. Comparison is timing-safe.
 * Caller is responsible for the failure UX.
 */
function csrf_validate(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $session_token = $_SESSION['csrf_token'] ?? '';
    $posted_token  = $_POST['csrf_token']    ?? '';

    if (
        !is_string($session_token) || $session_token === ''
        || !is_string($posted_token) || $posted_token === ''
    ) {
        return false;
    }

    return hash_equals($session_token, $posted_token);
}

/**
 * Force-regenerate the session CSRF token. Called at auth trust
 * boundaries (e.g. after successful login) in later units.
 */
function csrf_regenerate(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        trigger_error(
            'csrf_regenerate() called without an active session.',
            E_USER_ERROR
        );
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

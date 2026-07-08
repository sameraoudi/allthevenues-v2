<?php
declare(strict_types=1);

/**
 * #3 U-P9a — one-time password-setup tokens (invite / reset). The RAW token is
 * returned once (for the emailed link) and never stored; only its SHA-256 hex is
 * persisted, so a DB read can't reconstruct a usable link. Tokens expire after
 * 48h and are single-use (used_at). Prepared statements throughout.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mail.php';           // send_mail()
require_once __DIR__ . '/helpers.php';        // base_url()
require_once __DIR__ . '/email_template.php'; // branded email shell + components

const PT_TTL_HOURS = 48;

/**
 * Issue a fresh token for ($userId,$purpose), invalidating any prior UNUSED ones.
 * Returns ['raw'=>string, 'expires'=>string 'Y-m-d H:i:s']. RAW is shown once.
 */
function pt_create(PDO $pdo, int $userId, string $purpose, ?int $createdBy, ?string $sentTo): array
{
    $purpose = in_array($purpose, ['invite', 'reset'], true) ? $purpose : 'invite';

    // Spend any outstanding unused tokens for this user+purpose (one live link).
    $pdo->prepare(
        'UPDATE password_tokens SET used_at = NOW()
         WHERE user_id = :u AND purpose = :p AND used_at IS NULL'
    )->execute([':u' => $userId, ':p' => $purpose]);

    $raw     = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $raw);
    $expires = date('Y-m-d H:i:s', time() + PT_TTL_HOURS * 3600);

    $pdo->prepare(
        'INSERT INTO password_tokens (user_id, purpose, token_hash, created_by, sent_to, expires_at)
         VALUES (:u, :p, :h, :by, :to, :exp)'
    )->execute([
        ':u' => $userId, ':p' => $purpose, ':h' => $hash,
        ':by' => $createdBy ?: null, ':to' => $sentTo ?: null, ':exp' => $expires,
    ]);

    return ['raw' => $raw, 'expires' => $expires];
}

/**
 * Resolve a raw token for a purpose. Returns ['state'=>..., 'row'=>?array].
 * state ∈ 'valid' | 'expired' | 'used' | 'invalid'. Constant-ish work regardless
 * of state (no early "not found" timing leak beyond the single indexed lookup).
 */
function pt_lookup(PDO $pdo, string $raw, string $purpose): array
{
    $raw = trim($raw);
    if ($raw === '' || !ctype_xdigit($raw)) {
        return ['state' => 'invalid'];
    }
    $hash = hash('sha256', $raw);
    $stmt = $pdo->prepare(
        'SELECT * FROM password_tokens WHERE token_hash = :h AND purpose = :p LIMIT 1'
    );
    $stmt->execute([':h' => $hash, ':p' => $purpose]);
    $row = $stmt->fetch();
    if ($row === false) {
        return ['state' => 'invalid'];
    }
    if ($row['used_at'] !== null) {
        return ['state' => 'used', 'row' => $row];
    }
    if (strtotime((string)$row['expires_at']) <= time()) {
        return ['state' => 'expired', 'row' => $row];
    }
    return ['state' => 'valid', 'row' => $row];
}

/** Spend a token (single-use guard). True if this call consumed it. */
function pt_consume(PDO $pdo, int $tokenId): bool
{
    $stmt = $pdo->prepare('UPDATE password_tokens SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
    $stmt->execute([':id' => $tokenId]);
    return $stmt->rowCount() > 0;
}

/** Invite lifecycle for a user: 'not_sent' | 'pending' | 'accepted' | 'expired'. */
function invite_status_for_user(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare(
        "SELECT used_at, expires_at FROM password_tokens
         WHERE user_id = :u AND purpose = 'invite' ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':u' => $userId]);
    $row = $stmt->fetch();
    if ($row === false)              { return 'not_sent'; }
    if ($row['used_at'] !== null)    { return 'accepted'; }
    if (strtotime((string)$row['expires_at']) <= time()) { return 'expired'; }
    return 'pending';
}

/** The latest invite token row for a user (for sent/expires/by display), or null. */
function invite_latest_for_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT pt.*, u.name AS created_by_name
         FROM password_tokens pt
         LEFT JOIN users u ON u.id = pt.created_by
         WHERE pt.user_id = :u AND pt.purpose = 'invite' ORDER BY pt.id DESC LIMIT 1"
    );
    $stmt->execute([':u' => $userId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Password status from a user row: 'set' | 'not_set' (empty hash = unusable). */
function password_status_for_user(array $user): string
{
    return trim((string)($user['password_hash'] ?? '')) === '' ? 'not_set' : 'set';
}

/**
 * Email a provider their one-time set-password link. Uses lib/mail (never throws;
 * logs + tolerates failure). $raw is the plaintext token (rawurlencoded into the link).
 */
function send_invite_email(array $user, string $raw, string $providerName): void
{
    $to = trim((string)($user['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('send_invite_email: no valid recipient for user ' . (int)($user['id'] ?? 0));
        return;
    }
    $name = trim((string)($user['name'] ?? ''));
    $link = base_url('set-password?token=' . rawurlencode($raw));

    $content = email_intro_row('Set up your partner account', [
            'Hello' . ($name !== '' ? ' ' . e($name) : '') . ',',
            'An All The Venues provider account has been created for <strong>' . e($providerName) . '</strong>. '
                . 'Use the button below to set your password and access the Partner Portal.',
        ])
        . email_button_row('Set Your Password', $link, true)
        . email_note_row('This link can be used once and expires in 48 hours. '
            . 'If you were not expecting this invitation, you can ignore this email.');
    $html = email_layout('Set up your partner account', $content,
        'Set up your All The Venues partner account',
        'You received this because a provider account was created for you on All The Venues.');

    $text = 'Hello' . ($name !== '' ? ' ' . $name : '') . ",\n\n"
        . 'An All The Venues provider account has been created for ' . $providerName . ".\n"
        . "Set your password (this one-time link expires in 48 hours):\n" . $link . "\n\n"
        . "If you were not expecting this invitation, you can ignore this email.\n\n— All The Venues";

    if (!send_mail($to, 'Set up your All The Venues provider account', $html, $text)) {
        error_log('send_invite_email: send failed to ' . $to . ' (user ' . (int)($user['id'] ?? 0) . ')');
    }
}

/**
 * PU-B — email a password-RESET link (mirrors send_invite_email; reset copy).
 * $raw is the plaintext 'reset' token (rawurlencoded into the link).
 */
function send_reset_email(array $user, string $raw): void
{
    $to = trim((string)($user['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('send_reset_email: no valid recipient for user ' . (int)($user['id'] ?? 0));
        return;
    }
    $name = trim((string)($user['name'] ?? ''));
    $link = base_url('reset-password?token=' . rawurlencode($raw));

    $content = email_intro_row('Reset your password', [
            'Hello' . ($name !== '' ? ' ' . e($name) : '') . ',',
            'We received a request to reset the password for your All The Venues account. '
                . 'Use the button below to set a new password.',
        ])
        . email_button_row('Reset Your Password', $link, true)
        . email_note_row('This link can be used once and expires in 48 hours. '
            . 'If you didn&rsquo;t request this, you can safely ignore this email — your password won&rsquo;t change.');
    $html = email_layout('Reset your password', $content,
        'Reset your All The Venues password',
        'You received this because a password reset was requested for your All The Venues account.');

    $text = 'Hello' . ($name !== '' ? ' ' . $name : '') . ",\n\n"
        . "We received a request to reset the password for your All The Venues account.\n"
        . "Set a new password (this one-time link expires in 48 hours):\n" . $link . "\n\n"
        . "If you didn't request this, you can safely ignore this email — your password won't change.\n\n— All The Venues";

    if (!send_mail($to, 'Reset your All The Venues password', $html, $text)) {
        error_log('send_reset_email: send failed to ' . $to . ' (user ' . (int)($user['id'] ?? 0) . ')');
    }
}

/**
 * PU-B — shared password policy (extracted from U-P9a set-password; identical
 * rules): ≥10 chars, confirmation match, not equal (case-insensitive) to the
 * user's email/name/provider name, and not a common password. Returns an error
 * message, or null when the password is acceptable. $user: email/name/provider_name.
 */
function password_policy_error(string $pw, string $confirm, array $user): ?string
{
    if (mb_strlen($pw) < 10) { return 'Use at least 10 characters.'; }
    if ($pw !== $confirm)    { return 'The two passwords don’t match.'; }

    $lc = mb_strtolower($pw);
    $identity = array_map('mb_strtolower', array_filter([
        (string)($user['email'] ?? ''),
        (string)($user['name'] ?? ''),
        (string)($user['provider_name'] ?? ''),
    ]));
    $deny = ['password', 'password1', 'password123', 'passw0rd', '1234567890', '12345678',
             'qwertyuiop', 'qwerty123', 'letmein123', 'iloveyou1', 'welcome123', 'admin1234',
             'allthevenues', 'changeme123'];

    if (in_array($lc, $identity, true) || in_array($lc, $deny, true)) {
        return 'Please choose a stronger password (not your email, name, or a common password).';
    }
    return null;
}

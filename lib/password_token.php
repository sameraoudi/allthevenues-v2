<?php
declare(strict_types=1);

/**
 * #3 U-P9a — one-time password-setup tokens (invite / reset). The RAW token is
 * returned once (for the emailed link) and never stored; only its SHA-256 hex is
 * persisted, so a DB read can't reconstruct a usable link. Tokens expire after
 * 48h and are single-use (used_at). Prepared statements throughout.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/mail.php';       // send_mail()
require_once __DIR__ . '/helpers.php';    // base_url()

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
    $esc  = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $name = trim((string)($user['name'] ?? ''));
    $link = base_url('set-password?token=' . rawurlencode($raw));

    $body = '<div style="font-family:Arial,sans-serif;color:#0E1B2A;line-height:1.6;">'
          . '<h2 style="font-size:18px;">All The Venues — Provider Portal</h2>'
          . '<p>Hello' . ($name !== '' ? ' ' . $esc($name) : '') . ',</p>'
          . '<p>An All The Venues provider account has been created for <strong>' . $esc($providerName) . '</strong>. '
          . 'Use the secure link below to set your password and access the provider portal.</p>'
          . '<p><a href="' . $esc($link) . '" style="display:inline-block;background:#426F94;color:#fff;'
          . 'padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:600;">Set your password</a></p>'
          . '<p style="color:#6b7b88;font-size:13px;">This link can be used once and expires in 48 hours. '
          . 'If you were not expecting this invitation, you can ignore this email.</p>'
          . '<p style="color:#6b7b88;">— The All The Venues team</p></div>';

    if (!send_mail($to, 'Set up your All The Venues provider account', $body)) {
        error_log('send_invite_email: send failed to ' . $to . ' (user ' . (int)($user['id'] ?? 0) . ')');
    }
}

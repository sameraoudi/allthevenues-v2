<?php
declare(strict_types=1);

/**
 * Contacts-A — per-provider contact gap-fill (admin-side). Fill-if-empty
 * semantics: a record "has a contact" when contact_name OR contact_email is
 * non-empty; a side that already has one is NEVER overwritten by the sync
 * helpers. All operations are idempotent + tenant-agnostic (admin ops). No schema
 * change.
 *
 * COLUMN NOTE: venues store the internal contact as contact_name/contact_email/
 * contact_phone; PROVIDERS (partners) store it as contact_name + email + phone.
 * Reads alias the partner columns to contact_email/contact_phone so the internal
 * representation is uniform; only the partner WRITE uses email/phone.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/audit.php';

/** True if a (uniform-keyed) row carries a contact — name OR email non-empty. */
function contact_has(array $row): bool
{
    return trim((string)($row['contact_name'] ?? '')) !== ''
        || trim((string)($row['contact_email'] ?? '')) !== '';
}

/** Provider contact row with uniform keys (contact_name/contact_email/contact_phone), or null. */
function _contact_provider_row(PDO $pdo, int $providerId): ?array
{
    $ps = $pdo->prepare('SELECT contact_name, email AS contact_email, phone AS contact_phone FROM partners WHERE id = :id LIMIT 1');
    $ps->execute([':id' => $providerId]);
    $row = $ps->fetch();
    return $row === false ? null : $row;
}

/**
 * Fill a venue's internal contact from its PROVIDER when the venue has none and
 * the provider has one. No-op otherwise (never overwrites). Returns true if filled.
 */
function contact_sync_for_venue(PDO $pdo, int $venueId, ?int $actorUserId = null): bool
{
    $vs = $pdo->prepare('SELECT id, partner_id, contact_name, contact_email, contact_phone FROM venues WHERE id = :id LIMIT 1');
    $vs->execute([':id' => $venueId]);
    $venue = $vs->fetch();
    if ($venue === false || contact_has($venue)) { return false; }   // gone, or already has a contact
    $pid = (int)($venue['partner_id'] ?? 0);
    if ($pid <= 0) { return false; }

    $prov = _contact_provider_row($pdo, $pid);
    if ($prov === null || !contact_has($prov)) { return false; }      // provider has nothing to copy

    $upd = $pdo->prepare('UPDATE venues SET contact_name = :n, contact_email = :e, contact_phone = :p WHERE id = :id');
    $upd->execute([
        ':n' => ($prov['contact_name']  ?? '') !== '' ? $prov['contact_name']  : null,
        ':e' => ($prov['contact_email'] ?? '') !== '' ? $prov['contact_email'] : null,
        ':p' => ($prov['contact_phone'] ?? '') !== '' ? $prov['contact_phone'] : null,
        ':id' => $venueId,
    ]);
    audit_log($pdo, $actorUserId, 'contact.fill', 'venue', $venueId, null,
        ['from' => 'provider', 'provider_id' => $pid,
         'contact_name' => $prov['contact_name'], 'contact_email' => $prov['contact_email']]);
    return true;
}

/**
 * Fill a PROVIDER's contact from its venues when the provider has none. Only when
 * UNAMBIGUOUS: exactly one venue with a contact, OR all venues-with-a-contact
 * share an identical name+email. If ambiguous (differing venue contacts) → no
 * write + ['ambiguous'=>true] so the caller can flag it. Never overwrites.
 * @return array{filled:bool, ambiguous:bool}
 */
function contact_sync_for_provider(PDO $pdo, int $providerId, ?int $actorUserId = null): array
{
    $prov = _contact_provider_row($pdo, $providerId);
    if ($prov === null || contact_has($prov)) { return ['filled' => false, 'ambiguous' => false]; }

    $vs = $pdo->prepare('SELECT id, contact_name, contact_email, contact_phone FROM venues WHERE partner_id = :pid ORDER BY id');
    $vs->execute([':pid' => $providerId]);
    $withContact = array_values(array_filter($vs->fetchAll(), 'contact_has'));
    if (!$withContact) { return ['filled' => false, 'ambiguous' => false]; }

    $key = static fn(array $r): string =>
        mb_strtolower(trim((string)($r['contact_name'] ?? ''))) . '|' . mb_strtolower(trim((string)($r['contact_email'] ?? '')));
    $first = $withContact[0];
    foreach ($withContact as $v) {
        if ($key($v) !== $key($first)) { return ['filled' => false, 'ambiguous' => true]; }
    }

    // Write the partner's real columns (contact_name + email + phone).
    $upd = $pdo->prepare('UPDATE partners SET contact_name = :n, email = :e, phone = :p WHERE id = :id');
    $upd->execute([
        ':n' => ($first['contact_name']  ?? '') !== '' ? $first['contact_name']  : null,
        ':e' => ($first['contact_email'] ?? '') !== '' ? $first['contact_email'] : null,
        ':p' => ($first['contact_phone'] ?? '') !== '' ? $first['contact_phone'] : null,
        ':id' => $providerId,
    ]);
    audit_log($pdo, $actorUserId, 'contact.fill', 'partner', $providerId, null,
        ['from' => 'venue', 'source_venue' => (int)$first['id'],
         'contact_name' => $first['contact_name'], 'contact_email' => $first['contact_email']]);
    return ['filled' => true, 'ambiguous' => false];
}

/**
 * Contacts-A #A4 — set a PROVIDER's contact (+ its venues) to a user (name+email;
 * phone untouched). When $overwrite is false, fill-if-empty only (provider + its
 * contactless venues). When true, overwrite the provider AND all its venues. The
 * CALLER enforces the role=partner + provider-present + overwrite-gating rules.
 * @return array{provider:bool, venues:int}
 */
function contact_set_from_user(PDO $pdo, int $providerId, string $name, string $email, bool $overwrite, ?int $actorUserId = null): array
{
    $name  = trim($name);
    $email = trim($email);
    $out = ['provider' => false, 'venues' => 0];

    $prov = _contact_provider_row($pdo, $providerId);
    if ($prov === null) { return $out; }

    if ($overwrite || !contact_has($prov)) {
        $pdo->prepare('UPDATE partners SET contact_name = :n, email = :e WHERE id = :id')
            ->execute([':n' => $name ?: null, ':e' => $email ?: null, ':id' => $providerId]);
        $out['provider'] = true;
    }

    $vs = $pdo->prepare('SELECT id, contact_name, contact_email FROM venues WHERE partner_id = :pid');
    $vs->execute([':pid' => $providerId]);
    $uv = $pdo->prepare('UPDATE venues SET contact_name = :n, contact_email = :e WHERE id = :id');
    foreach ($vs->fetchAll() as $v) {
        if ($overwrite || !contact_has($v)) {
            $uv->execute([':n' => $name ?: null, ':e' => $email ?: null, ':id' => (int)$v['id']]);
            $out['venues']++;
        }
    }

    if ($out['provider'] || $out['venues'] > 0) {
        audit_log($pdo, $actorUserId, 'contact.set', 'partner', $providerId, null,
            ['contact_name' => $name, 'contact_email' => $email, 'overwrite' => $overwrite,
             'provider_set' => $out['provider'], 'venues_updated' => $out['venues']]);
    }
    return $out;
}

/**
 * Read-only summary for the "View contacts" panel: the provider contact + the
 * DISTINCT venue contacts (deduped by name+email, each with a sample venue name +
 * how many venues share it). Admin-only caller.
 * @return array{provider: array, venues: array}
 */
function contact_provider_summary(PDO $pdo, int $providerId): array
{
    $ps = $pdo->prepare('SELECT org_name, contact_name, email AS contact_email, phone AS contact_phone FROM partners WHERE id = :id LIMIT 1');
    $ps->execute([':id' => $providerId]);
    $prov = $ps->fetch() ?: [];

    $vs = $pdo->prepare('SELECT name, contact_name, contact_email, contact_phone FROM venues WHERE partner_id = :pid ORDER BY name');
    $vs->execute([':pid' => $providerId]);

    $distinct = [];
    foreach ($vs->fetchAll() as $v) {
        if (!contact_has($v)) { continue; }
        $k = mb_strtolower(trim((string)$v['contact_name'])) . '|' . mb_strtolower(trim((string)$v['contact_email']));
        if (!isset($distinct[$k])) {
            $distinct[$k] = [
                'venue'         => (string)$v['name'],
                'contact_name'  => (string)($v['contact_name'] ?? ''),
                'contact_email' => (string)($v['contact_email'] ?? ''),
                'contact_phone' => (string)($v['contact_phone'] ?? ''),
                'count'         => 1,
            ];
        } else {
            $distinct[$k]['count']++;
        }
    }

    return [
        'provider' => [
            'org_name'      => (string)($prov['org_name'] ?? ''),
            'has'           => contact_has($prov),
            'contact_name'  => (string)($prov['contact_name'] ?? ''),
            'contact_email' => (string)($prov['contact_email'] ?? ''),
            'contact_phone' => (string)($prov['contact_phone'] ?? ''),
        ],
        'venues' => array_values($distinct),
    ];
}

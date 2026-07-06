<?php
declare(strict_types=1);

/**
 * #10 — slug-history 301s. Independent of lib/legacy_redirect.php (which keys on
 * legacy_id). Here we key on the OLD pretty slug and resolve to the entity's
 * CURRENT slug, only if that entity is currently published/approved.
 */
require_once __DIR__ . '/helpers.php';       // base_url()
require_once __DIR__ . '/../config/db.php';  // db_pdo() (already loaded by callers)

/** Record old→entity on a slug change. No-op if unchanged. Failures are logged, never fatal. */
function slug_redirect_capture(PDO $pdo, string $type, string $oldSlug, string $newSlug, int $entityId): void
{
    if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug || $entityId <= 0) {
        return;
    }
    try {
        // If the newly-adopted slug had a redirect row (slug reused/reverted), drop it → no loop.
        $del = $pdo->prepare('DELETE FROM slug_redirects WHERE entity_type = :t AND old_slug = :newslug');
        $del->execute([':t' => $type, ':newslug' => $newSlug]);

        // Record/repoint old_slug → this entity.
        $ins = $pdo->prepare(
            'INSERT INTO slug_redirects (entity_type, old_slug, entity_id) VALUES (:t, :oldslug, :eid)
             ON DUPLICATE KEY UPDATE entity_id = VALUES(entity_id)'
        );
        $ins->execute([':t' => $type, ':oldslug' => $oldSlug, ':eid' => $entityId]);
    } catch (Throwable $e) {
        error_log('slug_redirect_capture failed: ' . $e->getMessage());
    }
}

/** Current slug for an old slug, ONLY if the target is live. Null otherwise. */
function slug_redirect_lookup(PDO $pdo, string $type, string $oldSlug): ?string
{
    if ($oldSlug === '') {
        return null;
    }
    $sql = $type === 'venue'
        ? "SELECT v.slug FROM slug_redirects r JOIN venues v ON v.id = r.entity_id
             WHERE r.entity_type = 'venue' AND r.old_slug = :oldslug AND v.status = 'published' LIMIT 1"
        : "SELECT p.slug FROM slug_redirects r JOIN partners p ON p.id = r.entity_id
             WHERE r.entity_type = 'provider' AND r.old_slug = :oldslug AND p.status = 'approved' LIMIT 1";
    try {
        $st = $pdo->prepare($sql);
        $st->execute([':oldslug' => $oldSlug]);
        $s = $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('slug_redirect_lookup failed: ' . $e->getMessage());
        return null;
    }
    // Guard against a self-redirect loop.
    return ($s !== false && $s !== null && $s !== '' && $s !== $oldSlug) ? (string)$s : null;
}

/** If the old slug maps to a live entity, emit a single-hop 301 and exit. Else return. */
function slug_redirect_maybe_301(PDO $pdo, string $type, string $oldSlug): void
{
    $new = slug_redirect_lookup($pdo, $type, $oldSlug);
    if ($new !== null) {
        $prefix = $type === 'venue' ? 'venues/' : 'providers/';
        header('Location: ' . base_url($prefix . rawurlencode($new)), true, 301);
        exit;
    }
}

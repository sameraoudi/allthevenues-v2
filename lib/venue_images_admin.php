<?php
declare(strict_types=1);

/**
 * Admin venue-image data layer (U4b). Prepared statements throughout. Every
 * lookup/mutation is scoped to a venue id, so an image id from another venue
 * can never be touched. The one-primary-per-venue invariant is enforced in a
 * transaction. No schema change — operates on the existing venue_images table.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';   // app_path()

/** All active images for a venue, in display order. */
function venue_images_admin_list(PDO $pdo, int $venueId): array
{
    $stmt = $pdo->prepare(
        "SELECT id, venue_id, file_path, thumb_path, alt_text, is_primary, sort_order, status,
                permission_status, image_source, source_url, provider_approved_by,
                approval_date, usage_notes, expires_at
         FROM venue_images
         WHERE venue_id = :vid AND status = 'active'
         ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute([':vid' => $venueId]);
    return $stmt->fetchAll();
}

/** One image scoped to its venue (ownership guard), or null. */
function venue_images_get(PDO $pdo, int $venueId, int $imageId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, venue_id, file_path, thumb_path, alt_text, is_primary, sort_order, status,
                permission_status, image_source, source_url, provider_approved_by,
                approval_date, usage_notes, expires_at
         FROM venue_images WHERE id = :id AND venue_id = :vid LIMIT 1'
    );
    $stmt->execute([':id' => $imageId, ':vid' => $venueId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Next sort_order for a venue (append to the end). */
function venue_images_next_sort(PDO $pdo, int $venueId): int
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(MAX(sort_order), -1) + 1 FROM venue_images WHERE venue_id = :vid AND status = 'active'"
    );
    $stmt->execute([':vid' => $venueId]);
    return (int)$stmt->fetchColumn();
}

/** Count of active images for a venue. */
function venue_images_count(PDO $pdo, int $venueId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM venue_images WHERE venue_id = :vid AND status = 'active'");
    $stmt->execute([':vid' => $venueId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Insert an image row. The FIRST image of a venue becomes primary automatically.
 * @return int new image id
 */
function venue_images_add(PDO $pdo, int $venueId, string $filePath, string $thumbPath, string $alt = ''): int
{
    $isPrimary = venue_images_count($pdo, $venueId) === 0 ? 1 : 0;
    $sort      = venue_images_next_sort($pdo, $venueId);
    $stmt = $pdo->prepare(
        "INSERT INTO venue_images (venue_id, file_path, thumb_path, alt_text, is_primary, sort_order, status)
         VALUES (:vid, :fp, :tp, :alt, :prim, :sort, 'active')"
    );
    $stmt->execute([
        ':vid' => $venueId, ':fp' => $filePath, ':tp' => $thumbPath !== '' ? $thumbPath : null,
        ':alt' => $alt !== '' ? $alt : null, ':prim' => $isPrimary, ':sort' => $sort,
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Make $imageId the sole primary for its venue (transaction). Returns false if
 * the image doesn't belong to the venue.
 */
function venue_images_set_primary(PDO $pdo, int $venueId, int $imageId): bool
{
    if (venue_images_get($pdo, $venueId, $imageId) === null) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE venue_images SET is_primary = 0 WHERE venue_id = :vid')
            ->execute([':vid' => $venueId]);
        $pdo->prepare('UPDATE venue_images SET is_primary = 1 WHERE id = :id AND venue_id = :vid')
            ->execute([':id' => $imageId, ':vid' => $venueId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('venue_images_set_primary failed: ' . $e->getMessage());
        return false;
    }
}

/** Update one image's alt text (scoped to venue). */
function venue_images_update_alt(PDO $pdo, int $venueId, int $imageId, string $alt): bool
{
    $stmt = $pdo->prepare('UPDATE venue_images SET alt_text = :alt WHERE id = :id AND venue_id = :vid');
    $stmt->execute([':alt' => $alt !== '' ? $alt : null, ':id' => $imageId, ':vid' => $venueId]);
    return $stmt->rowCount() >= 0;
}

/**
 * Move an image one step within its venue's order by swapping sort_order with
 * the adjacent image ($dir: -1 up, +1 down). Transactional. No-op at the edge.
 */
function venue_images_move(PDO $pdo, int $venueId, int $imageId, int $dir): bool
{
    $rows = venue_images_admin_list($pdo, $venueId);   // ordered
    $idx  = null;
    foreach ($rows as $i => $r) {
        if ((int)$r['id'] === $imageId) { $idx = $i; break; }
    }
    if ($idx === null) {
        return false;
    }
    $swap = $idx + ($dir < 0 ? -1 : 1);
    if ($swap < 0 || $swap >= count($rows)) {
        return true;   // already at the edge — nothing to do
    }
    $a = $rows[$idx];
    $b = $rows[$swap];
    $pdo->beginTransaction();
    try {
        $u = $pdo->prepare('UPDATE venue_images SET sort_order = :so WHERE id = :id AND venue_id = :vid');
        $u->execute([':so' => (int)$b['sort_order'], ':id' => (int)$a['id'], ':vid' => $venueId]);
        $u->execute([':so' => (int)$a['sort_order'], ':id' => (int)$b['id'], ':vid' => $venueId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('venue_images_move failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Delete an image (row + files). If it was primary, promote the next image
 * (lowest sort_order) to primary. Returns the deleted row (for audit/unlink)
 * or null if not found / not owned. File unlink is fail-safe.
 */
function venue_images_delete(PDO $pdo, int $venueId, int $imageId): ?array
{
    $row = venue_images_get($pdo, $venueId, $imageId);
    if ($row === null) {
        return null;
    }
    $wasPrimary = (int)$row['is_primary'] === 1;

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM venue_images WHERE id = :id AND venue_id = :vid')
            ->execute([':id' => $imageId, ':vid' => $venueId]);

        if ($wasPrimary) {
            $next = $pdo->prepare(
                "SELECT id FROM venue_images WHERE venue_id = :vid AND status = 'active'
                 ORDER BY sort_order ASC, id ASC LIMIT 1"
            );
            $next->execute([':vid' => $venueId]);
            $nextId = $next->fetchColumn();
            if ($nextId !== false) {
                $pdo->prepare('UPDATE venue_images SET is_primary = 1 WHERE id = :id AND venue_id = :vid')
                    ->execute([':id' => (int)$nextId, ':vid' => $venueId]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('venue_images_delete failed: ' . $e->getMessage());
        return null;
    }

    // Remove files from disk (fail-safe — a missing file is fine).
    foreach ([$row['file_path'] ?? '', $row['thumb_path'] ?? ''] as $rel) {
        $rel = trim((string)$rel);
        if ($rel !== '') {
            $abs = app_path($rel);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }
    return $row;
}

/**
 * #9b — permission_status ENUM (from migration 020) → [label, cleared?]. The
 * "cleared" flag drives the badge colour + (later, #9b gate) publish eligibility.
 * @return array<string,array{0:string,1:bool}>
 */
function venue_images_permission_options(): array
{
    return [
        'approved_by_provider'            => ['Approved by provider', true],
        'owned_by_atv'                    => ['ATV-owned', true],
        'licensed_stock'                  => ['Licensed (stock)', true],
        'legacy_needs_review'             => ['Needs review', false],
        'public_website_needs_permission' => ['From public site — needs permission', false],
        'remove_replace'                  => ['Remove / replace', false],
    ];
}

/**
 * #9b — set an image's provenance/permission (scoped to its venue). Validates
 * permission_status against the ENUM allowlist (rejects a forged value with no
 * write). Dates are parsed strictly (invalid → NULL). Returns false only when
 * permission_status is invalid; otherwise true.
 */
function venue_images_update_provenance(PDO $pdo, int $venueId, int $imageId, array $in): bool
{
    $opts = venue_images_permission_options();
    $status = trim((string)($in['permission_status'] ?? ''));
    if (!isset($opts[$status])) {
        return false;   // forged/invalid permission_status — no write
    }

    $plain = static function ($v, int $max): ?string {
        $s = mb_substr(trim(strip_tags((string)$v)), 0, $max);
        return $s !== '' ? $s : null;
    };
    $date = static function ($v): ?string {
        $s = trim((string)$v);
        if ($s === '') { return null; }
        $d = DateTime::createFromFormat('!Y-m-d', $s);
        $err = DateTime::getLastErrors();
        if ($d === false || ($err !== false && (($err['warning_count'] ?? 0) > 0 || ($err['error_count'] ?? 0) > 0))) {
            return null;   // e.g. '2026-13-40' → NULL, never fatal
        }
        return $d->format('Y-m-d');
    };

    $stmt = $pdo->prepare(
        'UPDATE venue_images
            SET permission_status = :ps, image_source = :src, source_url = :url,
                provider_approved_by = :by, approval_date = :ad, usage_notes = :notes, expires_at = :exp
          WHERE id = :id AND venue_id = :vid'
    );
    $stmt->execute([
        ':ps'    => $status,
        ':src'   => $plain($in['image_source'] ?? '', 100),
        ':url'   => $plain($in['source_url'] ?? '', 255),
        ':by'    => $plain($in['provider_approved_by'] ?? '', 255),
        ':ad'    => $date($in['approval_date'] ?? ''),
        ':notes' => $plain($in['usage_notes'] ?? '', 65535),
        ':exp'   => $date($in['expires_at'] ?? ''),
        ':id'    => $imageId,
        ':vid'   => $venueId,
    ]);
    return true;
}

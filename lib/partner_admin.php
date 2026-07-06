<?php
declare(strict_types=1);

/**
 * Admin provider (partner) management data access (U4d-3a). Admin/editor-gated
 * callers only. Unlike the public layer, this sees partners of EVERY status.
 * Prepared statements; distinct placeholders (no reused-name HY093 trap).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/partners.php';   // partner_org_type_expr(), type labels
require_once __DIR__ . '/venue_images_admin.php';   // #9c shared permission options (cover provenance)

/** Partner status enum → [label, css-modifier] (reuses lead-status modifiers). */
function partner_admin_statuses(): array
{
    return [
        'draft'     => ['Draft',     'draft'],
        'pending'   => ['Pending',   'pending'],
        'approved'  => ['Approved',  'published'],
        'suspended' => ['Suspended', 'archived'],
    ];
}

function partner_admin_status_label(string $s): string
{
    return partner_admin_statuses()[$s][0] ?? ucfirst(str_replace('_', ' ', $s));
}

/** Normalise list filters from $_GET. */
function partner_admin_filters(array $in): array
{
    $out = [];
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') $out['q'] = mb_substr($q, 0, 100);
    $s = trim((string)($in['status'] ?? ''));
    if (isset(partner_admin_statuses()[$s])) $out['status'] = $s;
    if (($em = (int)($in['emirate'] ?? 0)) > 0) $out['emirate'] = $em;
    return $out;
}

/** WHERE fragment + bound params (distinct placeholders). */
function partner_admin_where(array $f): array
{
    $sql = '';
    $p = [];
    if (isset($f['q']))       { $sql .= ' AND p.org_name LIKE :q';       $p[':q'] = '%' . $f['q'] . '%'; }
    if (isset($f['status']))  { $sql .= ' AND p.status = :status';       $p[':status'] = $f['status']; }
    if (isset($f['emirate'])) { $sql .= ' AND p.emirate_id = :emirate';  $p[':emirate'] = $f['emirate']; }
    return [$sql, $p];
}

/** @return array{rows: array, total: int} */
function partner_admin_list(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$where, $params] = partner_admin_where($filters);

    $cnt = $pdo->prepare('SELECT COUNT(*) FROM partners p WHERE 1=1' . $where);
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $sql = "SELECT p.id, p.org_name, p.slug, p.status, p.is_featured, p.is_verified, p.email,
                   e.name AS emirate_name,
                   (SELECT COUNT(*) FROM venues v WHERE v.partner_id = p.id AND v.status = 'published') AS venue_count,
                   (" . partner_org_type_expr() . ") AS raw_org_type
            FROM partners p
            LEFT JOIN emirates e ON e.id = p.emirate_id
            WHERE 1=1" . $where . "
            ORDER BY p.org_name ASC
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $val) { $stmt->bindValue($k, $val); }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Full partner row for editing (any status) + effective type, or null. */
function partner_admin_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.*, (' . partner_org_type_expr('p') . ') AS raw_org_type
         FROM partners p WHERE p.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * INSERT a partner from a validated $data map (col => value) and return the new
 * id. Columns/placeholders are built from array_keys($data) — the caller
 * guarantees a valid 'status' + slug and clean values. Prepared/bound.
 */
function partner_admin_create(PDO $pdo, array $data): int
{
    $cols = array_keys($data);
    $ph   = array_map(static fn($c) => ':' . $c, $cols);
    $stmt = $pdo->prepare(
        'INSERT INTO partners (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')'
    );
    foreach ($data as $col => $val) { $stmt->bindValue(':' . $col, $val); }
    $stmt->execute();
    return (int)$pdo->lastInsertId();
}

/** True if $slug is free (optionally excluding a partner id). */
function partner_admin_slug_available(PDO $pdo, string $slug, int $excludeId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM partners WHERE slug = :slug AND id <> :id LIMIT 1');
    $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
    return $stmt->fetchColumn() === false;
}

/** Rich-text fields that must pass through html_sanitize() before save. */
function partner_admin_richtext_fields(): array
{
    return ['about'];
}

/* ---- Cover image (single provider-owned hero) --------------------------- */

/** Set the cover image paths (leaves cover_image_alt untouched). */
function partner_cover_replace(PDO $pdo, int $id, string $filePath, string $thumbPath): void
{
    $stmt = $pdo->prepare(
        'UPDATE partners SET cover_image_path = :fp, cover_thumb_path = :tp WHERE id = :id'
    );
    $stmt->execute([':fp' => $filePath, ':tp' => $thumbPath, ':id' => $id]);
}

/** Update the cover alt text (null when empty). */
function partner_cover_update_alt(PDO $pdo, int $id, string $alt): void
{
    $stmt = $pdo->prepare('UPDATE partners SET cover_image_alt = :alt WHERE id = :id');
    $stmt->execute([':alt' => $alt !== '' ? $alt : null, ':id' => $id]);
}

/** Clear the cover image (paths + alt) — caller unlinks the files. */
function partner_cover_clear(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare(
        'UPDATE partners SET cover_image_path = NULL, cover_thumb_path = NULL, cover_image_alt = NULL WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
}

/**
 * #9c — set the provider cover's provenance/permission (mirrors
 * venue_images_update_provenance). Validates permission_status against the shared
 * allowlist (forged → no write, false); dates strict Y-m-d→NULL; text plain/
 * null-if-empty. Writes the cover_* columns for one partner. (partner_admin_get's
 * `SELECT p.*` already returns these columns — no SELECT change needed.)
 */
function partner_cover_update_provenance(PDO $pdo, int $partnerId, array $in): bool
{
    $opts   = venue_images_permission_options();
    $status = trim((string)($in['permission_status'] ?? ''));
    if (!isset($opts[$status])) {
        return false;   // forged/invalid — no write
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
            return null;
        }
        return $d->format('Y-m-d');
    };

    $stmt = $pdo->prepare(
        'UPDATE partners
            SET cover_permission_status = :ps, cover_image_source = :src, cover_source_url = :url,
                cover_provider_approved_by = :by, cover_approval_date = :ad, cover_usage_notes = :notes,
                cover_expires_at = :exp
          WHERE id = :pid'
    );
    $stmt->execute([
        ':ps'    => $status,
        ':src'   => $plain($in['image_source'] ?? '', 100),
        ':url'   => $plain($in['source_url'] ?? '', 255),
        ':by'    => $plain($in['provider_approved_by'] ?? '', 255),
        ':ad'    => $date($in['approval_date'] ?? ''),
        ':notes' => $plain($in['usage_notes'] ?? '', 65535),
        ':exp'   => $date($in['expires_at'] ?? ''),
        ':pid'   => $partnerId,
    ]);
    return true;
}

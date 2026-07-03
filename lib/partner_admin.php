<?php
declare(strict_types=1);

/**
 * Admin provider (partner) management data access (U4d-3a). Admin/editor-gated
 * callers only. Unlike the public layer, this sees partners of EVERY status.
 * Prepared statements; distinct placeholders (no reused-name HY093 trap).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/partners.php';   // partner_org_type_expr(), type labels

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

/** Full partner row for editing (any status), or null. */
function partner_admin_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM partners WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
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

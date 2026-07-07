<?php
declare(strict_types=1);

/**
 * Admin venue management data access (U4a). Admin-gated callers only.
 * Unlike the public layer, this sees venues of EVERY status. Prepared
 * statements; distinct placeholders (no reused-name HY093 trap).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venues.php';

/** Venue status enum → [label, css-modifier]. */
function venue_admin_statuses(): array
{
    return [
        'draft'         => ['Draft',          'draft'],
        'pending'       => ['Pending',        'pending'],
        'published'     => ['Published',      'published'],
        'needs_changes' => ['Needs changes',  'needs_changes'],
        'archived'      => ['Archived',       'archived'],
    ];
}

function venue_admin_status_label(string $s): string
{
    return venue_admin_statuses()[$s][0] ?? ucfirst(str_replace('_', ' ', $s));
}

/** Provenance label for venues.management_source (#6-1). */
function venue_management_source_label(string $s): string
{
    return [
        'unassigned'       => 'Not provider-managed',
        'admin_assigned'   => 'Admin-assigned',
        'provider_created' => 'Provider-created',
        'provider_claimed' => 'Provider-claimed',
        'legacy_import'    => 'Legacy import',
    ][$s] ?? ucfirst(str_replace('_', ' ', $s));
}

/** Normalise list filters from $_GET. */
function venue_admin_filters(array $in): array
{
    $out = [];
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') $out['q'] = mb_substr($q, 0, 100);
    $s = trim((string)($in['status'] ?? ''));
    if (isset(venue_admin_statuses()[$s])) $out['status'] = $s;
    if (($em = (int)($in['emirate'] ?? 0)) > 0) $out['emirate'] = $em;
    if (($vt = (int)($in['venue_type'] ?? 0)) > 0) $out['venue_type'] = $vt;
    return $out;
}

/** WHERE fragment + bound params (distinct placeholders). */
function venue_admin_where(array $f): array
{
    $sql = '';
    $p = [];
    if (isset($f['q']))          { $sql .= ' AND v.name LIKE :q';               $p[':q'] = '%' . $f['q'] . '%'; }
    if (isset($f['status']))     { $sql .= ' AND v.status = :status';           $p[':status'] = $f['status']; }
    if (isset($f['emirate']))    { $sql .= ' AND v.emirate_id = :emirate';      $p[':emirate'] = $f['emirate']; }
    if (isset($f['venue_type'])) { $sql .= ' AND v.venue_type_id = :vtype';     $p[':vtype'] = $f['venue_type']; }
    return [$sql, $p];
}

/** @return array{rows: array, total: int} */
function venue_admin_list(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$where, $params] = venue_admin_where($filters);

    $cnt = $pdo->prepare('SELECT COUNT(*) FROM venues v WHERE 1=1' . $where);
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $sql = "SELECT v.id, v.name, v.slug, v.status, v.is_featured, v.updated_at,
                   vt.name AS venue_type_name, e.name AS emirate_name, p.org_name AS partner_name
            FROM venues v
            LEFT JOIN venue_types vt ON vt.id = v.venue_type_id
            LEFT JOIN emirates    e  ON e.id  = v.emirate_id
            LEFT JOIN partners    p  ON p.id  = v.partner_id
            WHERE 1=1" . $where . "
            ORDER BY v.updated_at DESC, v.id DESC
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $val) { $stmt->bindValue($k, $val); }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Full venue row for editing (any status), or null. Joins the assigner's name
 *  (provider_assigned_by → users.id) for the read-only provenance display. */
function venue_admin_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT v.*, u.name AS assigned_by_name
         FROM venues v LEFT JOIN users u ON u.id = v.provider_assigned_by
         WHERE v.id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** All providers (any status) for the venue's provider-assignment select. */
function venue_partner_options(PDO $pdo): array
{
    return $pdo->query('SELECT id, org_name FROM partners ORDER BY org_name')->fetchAll();
}

/**
 * INSERT a venue from a validated $data map (col => value) and return the new
 * id. Columns/placeholders are built from array_keys($data) — the caller
 * guarantees a valid 'status' and clean values. Prepared/bound.
 */
function venue_admin_create(PDO $pdo, array $data): int
{
    $cols = array_keys($data);
    $ph   = array_map(static fn($c) => ':' . $c, $cols);
    $stmt = $pdo->prepare(
        'INSERT INTO venues (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')'
    );
    foreach ($data as $col => $val) { $stmt->bindValue(':' . $col, $val); }
    $stmt->execute();
    return (int)$pdo->lastInsertId();
}

/** True if $slug is free (optionally excluding a venue id). */
function venue_slug_available(PDO $pdo, string $slug, int $excludeId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM venues WHERE slug = :slug AND id <> :id LIMIT 1');
    $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
    return $stmt->fetchColumn() === false;
}

/**
 * Upsert/prune a venue's layout-capacity rows in one transaction.
 * $input = [layout_type => raw capacity string]. For each of the 8 canonical
 * venue_layout_types(): a positive int → INSERT … ON DUPLICATE KEY UPDATE (the
 * table has UNIQUE (venue_id, layout_type)); blank/0/invalid → DELETE that row.
 * Only the 8 known layout_types are accepted; anything else is ignored.
 */
function venue_layout_capacity_save(PDO $pdo, int $venueId, array $input): void
{
    $ins = $pdo->prepare(
        'INSERT INTO venue_layout_capacity (venue_id, layout_type, capacity)
         VALUES (:v, :t, :c)
         ON DUPLICATE KEY UPDATE capacity = VALUES(capacity)'
    );
    $del = $pdo->prepare(
        'DELETE FROM venue_layout_capacity WHERE venue_id = :v AND layout_type = :t'
    );

    $pdo->beginTransaction();
    try {
        foreach (array_keys(venue_layout_types()) as $type) {
            $raw = trim((string)($input[$type] ?? ''));
            $cap = ($raw !== '' && ctype_digit($raw)) ? (int)$raw : 0;
            if ($cap > 0) {
                $ins->execute([':v' => $venueId, ':t' => $type, ':c' => $cap]);
            } else {
                $del->execute([':v' => $venueId, ':t' => $type]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('venue_layout_capacity_save failed (venue=' . $venueId . '): ' . $e->getMessage());
        throw $e;
    }
}

/**
 * #19 — validate that no layout capacity exceeds the venue maximum. Returns
 * [layoutType => message] (empty when all fine, or when no positive max is set).
 * Existing rows may pre-date this rule — the caller only blocks NEW saves.
 */
function venue_layout_capacity_errors(array $layout, ?int $capMax): array
{
    $errors = [];
    if ($capMax === null || $capMax <= 0) { return $errors; }
    foreach (array_keys(venue_layout_types()) as $type) {
        $raw = trim((string)($layout[$type] ?? ''));
        if ($raw !== '' && ctype_digit($raw) && (int)$raw > $capMax) {
            $errors[$type] = $type . ' capacity cannot exceed the venue maximum of ' . $capMax . ' guests.';
        }
    }
    return $errors;
}

/** Rich-text fields that must pass through html_sanitize() before save. */
function venue_richtext_fields(): array
{
    return ['description', 'best_for', 'highlights', 'facilities',
            'food_beverage', 'av_support', 'restrictions', 'packages', 'special_offer'];
}

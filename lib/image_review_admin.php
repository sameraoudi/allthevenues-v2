<?php
declare(strict_types=1);

/**
 * #9c — image-rights "needs review" report data layer. Read-only, prepared.
 * Surfaces every image whose permission_status is NOT cleared, across both
 * venue images and provider covers, so the backlog can be worked to zero.
 * "Cleared" is defined once in venue_images_cleared_statuses().
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venue_images_admin.php';   // permission options + cleared statuses

/** The non-cleared status universe (everything that still needs review). */
function _image_review_noncleared(): array
{
    return array_values(array_diff(
        array_keys(venue_images_permission_options()),
        venue_images_cleared_statuses()
    ));
}

/**
 * KPI counts: total needing review + a per-status breakdown, combining venue
 * images and provider covers. Covers only count when a cover image exists.
 */
function image_review_counts(PDO $pdo): array
{
    $nc = _image_review_noncleared();
    $ph = implode(',', array_fill(0, count($nc), '?'));

    $by = [];
    foreach ($nc as $s) { $by[$s] = 0; }

    $q = $pdo->prepare(
        "SELECT permission_status s, COUNT(*) c FROM venue_images
         WHERE status = 'active' AND permission_status IN ($ph) GROUP BY permission_status"
    );
    $q->execute($nc);
    foreach ($q->fetchAll() as $r) { $by[(string)$r['s']] = ($by[(string)$r['s']] ?? 0) + (int)$r['c']; }

    $q2 = $pdo->prepare(
        "SELECT cover_permission_status s, COUNT(*) c FROM partners
         WHERE cover_image_path IS NOT NULL AND cover_permission_status IN ($ph)
         GROUP BY cover_permission_status"
    );
    $q2->execute($nc);
    foreach ($q2->fetchAll() as $r) { $by[(string)$r['s']] = ($by[(string)$r['s']] ?? 0) + (int)$r['c']; }

    return ['total' => array_sum($by), 'by_status' => $by];
}

/**
 * A page of items needing review (venue images + provider covers), unified.
 * $filters['status'] narrows to one non-cleared status. Returns
 * ['rows'=>array, 'total'=>int]. Each row: kind, entity_id, entity_name,
 * entity_slug, thumb, full, status, source.
 */
function image_review_list(PDO $pdo, array $filters, int $limit, int $offset): array
{
    $nc = _image_review_noncleared();
    $ph = implode(',', array_fill(0, count($nc), '?'));

    $status    = trim((string)($filters['status'] ?? ''));
    $useStatus = ($status !== '' && in_array($status, $nc, true));

    $vWhere = "vi.status = 'active' AND vi.permission_status IN ($ph)";
    $cWhere = "p.cover_image_path IS NOT NULL AND p.cover_permission_status IN ($ph)";
    $vParams = $nc;
    $cParams = $nc;
    if ($useStatus) {
        $vWhere .= ' AND vi.permission_status = ?';        $vParams[] = $status;
        $cWhere .= ' AND p.cover_permission_status = ?';   $cParams[] = $status;
    }

    $union =
        "SELECT 'venue' AS kind, vi.venue_id AS entity_id, v.name AS entity_name, v.slug AS entity_slug,
                vi.thumb_path AS thumb, vi.file_path AS full, vi.permission_status AS status, vi.image_source AS source
         FROM venue_images vi JOIN venues v ON v.id = vi.venue_id
         WHERE $vWhere
         UNION ALL
         SELECT 'cover' AS kind, p.id AS entity_id, p.org_name AS entity_name, p.slug AS entity_slug,
                p.cover_thumb_path AS thumb, p.cover_image_path AS full, p.cover_permission_status AS status, p.cover_image_source AS source
         FROM partners p
         WHERE $cWhere";

    $params = array_merge($vParams, $cParams);

    $ct = $pdo->prepare("SELECT COUNT(*) FROM ($union) t");
    $ct->execute($params);
    $total = (int)$ct->fetchColumn();

    // limit/offset are validated ints (never user strings) — safe to inline,
    // which sidesteps the LIMIT-placeholder quirk on MySQL.
    $limit  = max(1, $limit);
    $offset = max(0, $offset);
    $st = $pdo->prepare(
        "SELECT * FROM ($union) t ORDER BY status ASC, kind ASC, entity_name ASC LIMIT $limit OFFSET $offset"
    );
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'total' => $total];
}

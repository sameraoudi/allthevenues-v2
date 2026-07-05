<?php
declare(strict_types=1);

/**
 * Admin reporting aggregations (#5). Every function aggregates over the SAME
 * enquiry filter engine as the inbox (enquiry_admin_where) so the numbers
 * reconcile, then appends ' AND e.is_historical = 0' UNLESS $includeHistorical.
 *
 * Prepared statements throughout; the params from enquiry_admin_where are bound
 * via execute(). JOIN aliases here (evx/evk/vt/lr) never collide with the
 * subquery aliases used inside enquiry_admin_where (ev/evp/vp). Gulf-time
 * aligned via the pinned PDO session tz (config/db.php).
 */

require_once __DIR__ . '/enquiry_admin.php';

/** WHERE fragment + params + historical clause for a report query. */
function _report_where(array $filters, bool $includeHistorical): array
{
    [$where, $params] = enquiry_admin_where($filters);
    if (!$includeHistorical) {
        $where .= ' AND e.is_historical = 0';
    }
    return [$where, $params];
}

/** One-row KPI summary. Percentages/conversion are computed in the caller. */
function report_kpis(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT
                COUNT(*) AS total,
                SUM(e.status = 'new')            AS new_count,
                SUM(e.status = 'forwarded')      AS forwarded,
                SUM(e.status = 'won')            AS won,
                SUM(e.status = 'lost')           AS lost,
                SUM(e.status IN ('won','lost'))  AS decided,
                SUM(EXISTS (SELECT 1 FROM enquiry_venues evk WHERE evk.enquiry_id = e.id)) AS with_venue
            FROM enquiries e WHERE 1=1" . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch() ?: [];
    // Normalise NULLs (empty set) to ints.
    foreach (['total','new_count','forwarded','won','lost','decided','with_venue'] as $k) {
        $row[$k] = (int)($row[$k] ?? 0);
    }
    return $row;
}

/** Live vs historical counts IN the filter range, IGNORING the toggle. */
function report_live_vs_historical(PDO $pdo, array $filters): array
{
    [$where, $params] = enquiry_admin_where($filters);
    $q = static function (string $extra) use ($pdo, $where, $params): int {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enquiries e WHERE 1=1" . $where . $extra);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    };
    return [
        'live'       => $q(' AND e.is_historical = 0'),
        'historical' => $q(' AND e.is_historical = 1'),
    ];
}

/** Monthly counts (Gulf-time year/month). @return array<int,array{y:int,m:int,c:int}> */
function report_over_time(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT YEAR(e.created_at) AS y, MONTH(e.created_at) AS m, COUNT(*) AS c
            FROM enquiries e WHERE 1=1" . $where . "
            GROUP BY y, m ORDER BY y, m";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** By status, most common first. @return array<int,array{status:string,c:int}> */
function report_by_status(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT e.status AS status, COUNT(*) AS c
            FROM enquiries e WHERE 1=1" . $where . "
            GROUP BY e.status ORDER BY c DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Top event types. @return array<int,array{name:?string,c:int}> */
function report_by_event_type(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT et.name AS name, COUNT(*) AS c
            FROM enquiries e LEFT JOIN event_types et ON et.id = e.event_type_id
            WHERE 1=1" . $where . "
            GROUP BY et.id ORDER BY c DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** By emirate, most common first. @return array<int,array{name:?string,c:int}> */
function report_by_emirate(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT em.name AS name, COUNT(*) AS c
            FROM enquiries e LEFT JOIN emirates em ON em.id = e.emirate_id
            WHERE 1=1" . $where . "
            GROUP BY em.id ORDER BY c DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Top venues by linked enquiries. @return array<int,array{name:string,c:int}> */
function report_by_venue(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT v.name AS name, COUNT(*) AS c
            FROM enquiries e
            JOIN enquiry_venues evx ON evx.enquiry_id = e.id
            JOIN venues v ON v.id = evx.venue_id
            WHERE 1=1" . $where . "
            GROUP BY v.id ORDER BY c DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Top providers by enquiries touching their venues (via venues.partner_id). */
function report_by_provider_touching(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT p.org_name AS name, COUNT(DISTINCT e.id) AS c
            FROM enquiries e
            JOIN enquiry_venues evx ON evx.enquiry_id = e.id
            JOIN venues vt ON vt.id = evx.venue_id
            JOIN partners p ON p.id = vt.partner_id
            WHERE 1=1" . $where . "
            GROUP BY p.id ORDER BY c DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Top providers by leads actually forwarded (via lead_routing). */
function report_by_provider_forwarded(PDO $pdo, array $filters, bool $includeHistorical): array
{
    [$where, $params] = _report_where($filters, $includeHistorical);
    $sql = "SELECT p.org_name AS name, COUNT(*) AS c
            FROM lead_routing lr
            JOIN enquiries e ON e.id = lr.enquiry_id
            JOIN partners p ON p.id = lr.partner_id
            WHERE 1=1" . $where . "
            GROUP BY p.id ORDER BY c DESC LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Approved providers for the report filter dropdown. */
function report_provider_options(PDO $pdo): array
{
    return $pdo->query("SELECT id, org_name FROM partners WHERE status='approved' ORDER BY org_name")->fetchAll();
}

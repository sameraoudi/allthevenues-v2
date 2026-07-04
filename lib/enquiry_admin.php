<?php
declare(strict_types=1);

/**
 * Admin lead-inbox data access. Admin-gated callers only. All queries use
 * prepared statements; filters are bound.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venues.php';

/** Enquiry status enum → label + CSS modifier. */
function enquiry_statuses(): array
{
    return [
        'new'       => ['New',        'new'],
        'reviewed'  => ['Reviewed',   'reviewed'],
        'forwarded' => ['Forwarded',  'forwarded'],
        'accepted'  => ['Accepted',   'accepted'],
        'contacted' => ['Contacted',  'contacted'],
        'won'       => ['Won',        'won'],
        'lost'      => ['Lost',       'lost'],
        'closed'    => ['Closed',     'closed'],
        'spam'      => ['Spam',       'spam'],
    ];
}

function enquiry_status_label(string $s): string
{
    return enquiry_statuses()[$s][0] ?? ucfirst($s);
}

/** Mode badge from stored mode + whether venues are linked. */
function enquiry_mode_badge(string $mode, int $venueCount): array
{
    if ($mode === 'partner_signup') {
        return ['Partner signup', 'partner-signup'];
    }
    if ($venueCount > 0 || $mode === 'venue') {
        return ['Venue enquiry', 'venue'];
    }
    if ($mode === 'partner') {
        return ['Partner interest', 'partner'];
    }
    return ['Assisted', 'assisted'];
}

/** Normalise admin list filters from $_GET. */
function enquiry_admin_filters(array $in): array
{
    $out = [];
    $status = trim((string)($in['status'] ?? ''));
    if (isset(enquiry_statuses()[$status])) {
        $out['status'] = $status;
    }
    if (($et = (int)($in['event_type'] ?? 0)) > 0) {
        $out['event_type'] = $et;
    }
    if (($em = (int)($in['emirate'] ?? 0)) > 0) {
        $out['emirate'] = $em;
    }
    $mode = trim((string)($in['mode'] ?? ''));
    if (in_array($mode, ['venue', 'assisted', 'partner', 'general', 'partner_signup'], true)) {
        $out['mode'] = $mode;
    }
    foreach (['date_from', 'date_to'] as $k) {
        $v = trim((string)($in[$k] ?? ''));
        if ($v !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            $out[$k] = $v;
        }
    }
    $q = trim((string)($in['q'] ?? ''));
    if ($q !== '') {
        $out['q'] = mb_substr($q, 0, 100);
    }
    return $out;
}

/** Build WHERE fragment + bound params for the filter set. */
function enquiry_admin_where(array $f): array
{
    $sql = '';
    $p   = [];
    if (isset($f['status']))     { $sql .= ' AND e.status = :status';           $p[':status'] = $f['status']; }
    if (isset($f['event_type'])) { $sql .= ' AND e.event_type_id = :et';         $p[':et'] = $f['event_type']; }
    if (isset($f['emirate']))    { $sql .= ' AND e.emirate_id = :em';            $p[':em'] = $f['emirate']; }
    if (isset($f['mode'])) {
        if ($f['mode'] === 'venue') {
            $sql .= ' AND (e.mode = :mode OR EXISTS (SELECT 1 FROM enquiry_venues ev WHERE ev.enquiry_id = e.id))';
        } else {
            $sql .= ' AND e.mode = :mode AND NOT EXISTS (SELECT 1 FROM enquiry_venues ev WHERE ev.enquiry_id = e.id)';
        }
        $p[':mode'] = $f['mode'];
    }
    if (isset($f['date_from'])) { $sql .= ' AND e.created_at >= :dfrom';                 $p[':dfrom'] = $f['date_from'] . ' 00:00:00'; }
    if (isset($f['date_to']))   { $sql .= ' AND e.created_at <= :dto';                   $p[':dto']   = $f['date_to'] . ' 23:59:59'; }
    if (isset($f['q'])) {
        // Distinct placeholders — native prepares don't allow reusing one name.
        $like = '%' . $f['q'] . '%';
        $sql .= ' AND (e.name LIKE :q1 OR e.email LIKE :q2 OR e.reference LIKE :q3)';
        $p[':q1'] = $like;
        $p[':q2'] = $like;
        $p[':q3'] = $like;
    }
    return [$sql, $p];
}

/** SELECT clause shared by list + export. */
function _enquiry_admin_select(): string
{
    return "SELECT e.id, e.reference, e.name, e.email, e.phone, e.event_date,
                   e.guest_count, e.budget_range, e.mode, e.status, e.created_at,
                   et.name AS event_type_name,
                   em.name AS emirate_name,
                   pp.org_name AS partner_name,
                   (SELECT COUNT(*) FROM enquiry_venues ev WHERE ev.enquiry_id = e.id) AS venue_count,
                   (SELECT GROUP_CONCAT(v.name ORDER BY v.name SEPARATOR ', ')
                      FROM enquiry_venues ev JOIN venues v ON v.id = ev.venue_id
                      WHERE ev.enquiry_id = e.id) AS venue_names
            FROM enquiries e
            LEFT JOIN event_types et ON et.id = e.event_type_id
            LEFT JOIN emirates    em ON em.id = e.emirate_id
            LEFT JOIN partners    pp ON pp.id = e.partner_id";
}

/** Paginated list. @return array{rows:array,total:int} */
function enquiry_admin_list(PDO $pdo, array $filters, int $page, int $perPage): array
{
    [$where, $params] = enquiry_admin_where($filters);

    $cnt = $pdo->prepare('SELECT COUNT(*) FROM enquiries e WHERE 1=1' . $where);
    $cnt->execute($params);
    $total = (int)$cnt->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);
    $sql = _enquiry_admin_select() . ' WHERE 1=1' . $where
         . ' ORDER BY e.created_at DESC, e.id DESC LIMIT :lim OFFSET :off';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** All matching rows for CSV (capped). */
function enquiry_admin_export(PDO $pdo, array $filters, int $cap = 5000): array
{
    [$where, $params] = enquiry_admin_where($filters);
    $sql = _enquiry_admin_select() . ' WHERE 1=1' . $where
         . ' ORDER BY e.created_at DESC, e.id DESC LIMIT :lim';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $cap, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Total + new counts (respecting filters for the header; new is global). */
function enquiry_admin_counts(PDO $pdo): array
{
    return [
        'total' => (int)$pdo->query('SELECT COUNT(*) FROM enquiries')->fetchColumn(),
        'new'   => (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn(),
    ];
}

/** Full enquiry detail + linked venues + routing history. */
function enquiry_admin_get(PDO $pdo, int $id): ?array
{
    $sql = "SELECT e.*, et.name AS event_type_name, em.name AS emirate_name,
                   pp.org_name AS partner_name, pp.slug AS partner_slug
            FROM enquiries e
            LEFT JOIN event_types et ON et.id = e.event_type_id
            LEFT JOIN emirates    em ON em.id = e.emirate_id
            LEFT JOIN partners    pp ON pp.id = e.partner_id
            WHERE e.id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $enq = $stmt->fetch();
    if (!$enq) {
        return null;
    }

    $vs = $pdo->prepare(
        "SELECT v.id, v.name, v.slug, v.status, e.name AS emirate_name,
                v.partner_id, p.org_name AS partner_name, p.email AS partner_email,
                p.status AS partner_status
         FROM enquiry_venues ev
         JOIN venues v ON v.id = ev.venue_id
         LEFT JOIN emirates e ON e.id = v.emirate_id
         LEFT JOIN partners p ON p.id = v.partner_id
         WHERE ev.enquiry_id = :id ORDER BY v.name"
    );
    $vs->execute([':id' => $id]);
    $enq['venues'] = $vs->fetchAll();

    $rt = $pdo->prepare(
        "SELECT lr.*, p.org_name AS partner_name
         FROM lead_routing lr
         LEFT JOIN partners p ON p.id = lr.partner_id
         WHERE lr.enquiry_id = :id ORDER BY lr.id DESC"
    );
    $rt->execute([':id' => $id]);
    $enq['routing'] = $rt->fetchAll();

    return $enq;
}

/** Approved partners for the assign dropdown. */
function partners_for_assign(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, org_name, email FROM partners
         WHERE status = 'approved' ORDER BY org_name"
    )->fetchAll();
}

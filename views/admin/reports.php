<?php
declare(strict_types=1);

/**
 * Admin reporting controller (#5) — /admin/reports. Already gated admin+editor
 * by dispatch. Aggregates over the shared enquiry filter engine; historical
 * enquiries default OFF (toggle via ?historical=1). Per-section CSV export via
 * ?export=<section> honours the same filters + toggle.
 * Expects $pdo and $sub ('reports...') in scope.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/venues.php';
require_once __DIR__ . '/../../lib/enquiry.php';
require_once __DIR__ . '/../../lib/enquiry_admin.php';
require_once __DIR__ . '/../../lib/report_admin.php';

$me = auth_current_user();

$filters           = enquiry_admin_filters($_GET);
$includeHistorical = (($_GET['historical'] ?? '') === '1');

/* ============================ CSV EXPORT =============================== */
$export = trim((string)($_GET['export'] ?? ''));
if ($export !== '') {
    $stream = static function (string $name, array $header, array $rows): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reports-' . $name . '-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $header, ',', '"', '');
        foreach ($rows as $r) { fputcsv($out, $r, ',', '"', ''); }
        fclose($out);
    };

    switch ($export) {
        case 'summary':
            $k = report_kpis($pdo, $filters, $includeHistorical);
            $lh = report_live_vs_historical($pdo, $filters);
            $stream('summary', ['Metric', 'Value'], [
                ['Enquiries in range', $k['total']],
                ['New / unhandled', $k['new_count']],
                ['Forwarded', $k['forwarded']],
                ['Won', $k['won']],
                ['Lost', $k['lost']],
                ['Decided (won+lost)', $k['decided']],
                ['With a venue linked', $k['with_venue']],
                ['Live', $lh['live']],
                ['Historical', $lh['historical']],
            ]);
            return;
        case 'over_time':
            $rows = array_map(static fn($r) => [(int)$r['y'], (int)$r['m'], (int)$r['c']], report_over_time($pdo, $filters, $includeHistorical));
            $stream('over-time', ['Year', 'Month', 'Enquiries'], $rows);
            return;
        case 'status':
            $rows = array_map(static fn($r) => [$r['status'], (int)$r['c']], report_by_status($pdo, $filters, $includeHistorical));
            $stream('by-status', ['Status', 'Enquiries'], $rows);
            return;
        case 'event_type':
            $rows = array_map(static fn($r) => [$r['name'] ?? '(unspecified)', (int)$r['c']], report_by_event_type($pdo, $filters, $includeHistorical));
            $stream('by-event-type', ['Event type', 'Enquiries'], $rows);
            return;
        case 'emirate':
            $rows = array_map(static fn($r) => [$r['name'] ?? '(unspecified)', (int)$r['c']], report_by_emirate($pdo, $filters, $includeHistorical));
            $stream('by-emirate', ['Emirate', 'Enquiries'], $rows);
            return;
        case 'venue':
            $rows = array_map(static fn($r) => [$r['name'], (int)$r['c']], report_by_venue($pdo, $filters, $includeHistorical));
            $stream('top-venues', ['Venue', 'Linked enquiries'], $rows);
            return;
        case 'provider':
            $rows = [];
            foreach (report_by_provider_touching($pdo, $filters, $includeHistorical) as $r) {
                $rows[] = ['Touching their venues', $r['name'], (int)$r['c']];
            }
            foreach (report_by_provider_forwarded($pdo, $filters, $includeHistorical) as $r) {
                $rows[] = ['Leads forwarded', $r['name'], (int)$r['c']];
            }
            $stream('by-provider', ['Lens', 'Provider', 'Count'], $rows);
            return;
        default:
            http_response_code(404);
            $admin_active = 'reports'; $page_title = 'Not found — Admin';
            $admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
            $admin_content_view = __DIR__ . '/placeholder-content.php';
            require __DIR__ . '/layout.php';
            return;
    }
}

/* ============================ PAGE ==================================== */
$kpis        = report_kpis($pdo, $filters, $includeHistorical);
$liveHist    = report_live_vs_historical($pdo, $filters);
$overTime    = report_over_time($pdo, $filters, $includeHistorical);
$byStatus    = report_by_status($pdo, $filters, $includeHistorical);
$byEventType = report_by_event_type($pdo, $filters, $includeHistorical);
$byEmirate   = report_by_emirate($pdo, $filters, $includeHistorical);
$byVenue     = report_by_venue($pdo, $filters, $includeHistorical);
$provTouch   = report_by_provider_touching($pdo, $filters, $includeHistorical);
$provFwd     = report_by_provider_forwarded($pdo, $filters, $includeHistorical);

$eventTypes  = venue_event_types($pdo);
$emirates    = venue_emirates($pdo);
$providers   = report_provider_options($pdo);

$admin_active       = 'reports';
$page_title         = 'Reports — Admin';
$admin_page_title   = 'Reports';
$admin_content_view = __DIR__ . '/reports-content.php';
require __DIR__ . '/layout.php';

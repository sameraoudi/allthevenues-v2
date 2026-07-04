<?php
declare(strict_types=1);

/**
 * Admin lead inbox controller. Already gated by dispatch (auth_require_admin).
 * Handles: list, CSV export, detail, and CSRF-protected actions (status,
 * note, assign-to-partner). Expects $pdo and $sub ('enquiries...') in scope.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/venues.php';
require_once __DIR__ . '/../../lib/enquiry.php';
require_once __DIR__ . '/../../lib/enquiry_admin.php';
require_once __DIR__ . '/../../lib/mail.php';

$me   = auth_current_user();
$rest = trim(substr((string)$sub, strlen('enquiries')), '/');   // '' | '5' | '5/status'

/* ============================ LIST + CSV ================================== */
if ($rest === '') {
    $filters = enquiry_admin_filters($_GET);

    // --- CSV export of the filtered set ---
    if (($_GET['export'] ?? '') === 'csv') {
        $rows = enquiry_admin_export($pdo, $filters);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="enquiries-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Reference', 'Name', 'Email', 'Phone', 'Event type', 'Guests',
                       'Event date', 'Budget', 'Venue(s)', 'Mode', 'Status', 'Created'], ',', '"', '');
        foreach ($rows as $r) {
            [$modeLabel] = enquiry_mode_badge((string)$r['mode'], (int)$r['venue_count']);
            fputcsv($out, [
                $r['reference'],
                $r['name'],
                $r['email'],
                $r['phone'],
                $r['event_type_name'],
                $r['guest_count'] ? (venue_guest_bands()[$r['guest_count']][0] ?? $r['guest_count']) : '',
                $r['event_date'],
                $r['budget_range'],
                $r['venue_names'] ?: '',
                $modeLabel,
                enquiry_status_label((string)$r['status']),
                $r['created_at'],
            ], ',', '"', '');
        }
        fclose($out);
        return;
    }

    $perPage = 25;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $result  = enquiry_admin_list($pdo, $filters, $page, $perPage);
    $rows    = $result['rows'];
    $total   = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));
    $counts  = enquiry_admin_counts($pdo);

    $eventTypes = venue_event_types($pdo);
    $emirates   = venue_emirates($pdo);

    $admin_active       = 'enquiries';
    $page_title         = 'Enquiries — Admin';
    $admin_page_title   = 'Enquiries';
    $admin_content_view = __DIR__ . '/enquiries-list.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ DETAIL ===================================== */
if (preg_match('#^(\d+)$#', $rest, $m)) {
    $id  = (int)$m[1];
    $enq = enquiry_admin_get($pdo, $id);
    if ($enq === null) {
        http_response_code(404);
        $admin_active = 'enquiries'; $page_title = 'Not found — Admin';
        $admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
        $admin_content_view = __DIR__ . '/placeholder-content.php';
        require __DIR__ . '/layout.php';
        return;
    }
    $partners = partners_for_assign($pdo);
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    $admin_active       = 'enquiries';
    $page_title         = $enq['reference'] . ' — Admin';
    $admin_page_title   = 'Enquiry ' . $enq['reference'];
    $admin_content_view = __DIR__ . '/enquiry-detail.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ ACTIONS (POST) ============================= */
if (preg_match('#^(\d+)/(status|note|assign|delete)$#', $rest, $m)) {
    $id     = (int)$m[1];
    $action = $m[2];
    $detailUrl = 'admin/enquiries/' . $id;

    // Deleting is destructive + admin-only (the inbox itself allows admin+editor).
    if ($action === 'delete') {
        auth_require_role(['admin']);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Action could not be processed. Please try again.'];
        redirect($detailUrl);
    }

    $enq = enquiry_admin_get($pdo, $id);
    if ($enq === null) {
        http_response_code(404);
        redirect('admin/enquiries');
    }
    $uid = (int)($me['id'] ?? 0) ?: null;

    if ($action === 'status') {
        $new = (string)($_POST['status'] ?? '');
        if (isset(enquiry_statuses()[$new]) && $new !== $enq['status']) {
            $upd = $pdo->prepare('UPDATE enquiries SET status = :s WHERE id = :id');
            $upd->execute([':s' => $new, ':id' => $id]);
            audit_log($pdo, $uid, 'enquiry.status', 'enquiry', $id,
                ['status' => $enq['status']], ['status' => $new]);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Status updated to ' . enquiry_status_label($new) . '.'];
        } else {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'No status change.'];
        }
        redirect($detailUrl);
    }

    if ($action === 'note') {
        $note = clean_text($_POST['note'] ?? '', 2000);
        if ($note !== '') {
            $stamp   = '[' . date('Y-m-d H:i') . ' — ' . ($me['name'] ?: 'Admin') . '] ' . $note;
            $current = trim((string)$enq['notes']);
            $merged  = $current === '' ? $stamp : $current . "\n\n" . $stamp;
            $upd = $pdo->prepare('UPDATE enquiries SET notes = :n WHERE id = :id');
            $upd->execute([':n' => mb_substr($merged, 0, 8000), ':id' => $id]);
            audit_log($pdo, $uid, 'enquiry.note', 'enquiry', $id, null, ['note' => $stamp]);
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Note added.'];
        } else {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Empty note.'];
        }
        redirect($detailUrl);
    }

    if ($action === 'assign') {
        $partnerId = (int)($_POST['partner_id'] ?? 0);
        $ps = $pdo->prepare("SELECT id, org_name, email FROM partners WHERE id = :id AND status='approved' LIMIT 1");
        $ps->execute([':id' => $partnerId]);
        $partner = $ps->fetch();

        $routedEmail = trim((string)($_POST['routed_to_email'] ?? ''));
        if ($partner && $routedEmail === '') {
            $routedEmail = (string)$partner['email'];
        }

        if (!$partner || !filter_var($routedEmail, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Choose a partner and a valid recipient email.'];
            redirect($detailUrl);
        }

        // Record the routing first (never lose the assignment if mail fails).
        $venueId = (count($enq['venues']) === 1) ? (int)$enq['venues'][0]['id'] : null;
        $ins = $pdo->prepare(
            'INSERT INTO lead_routing (enquiry_id, venue_id, partner_id, routed_to_email, status, routed_at)
             VALUES (:e, :v, :p, :email, :status, NOW())'
        );
        $ins->execute([
            ':e' => $id, ':v' => $venueId, ':p' => $partnerId,
            ':email' => $routedEmail, ':status' => 'sent',
        ]);
        $pdo->prepare('UPDATE enquiries SET status = :s WHERE id = :id')
            ->execute([':s' => 'forwarded', ':id' => $id]);
        audit_log($pdo, $uid, 'enquiry.forward', 'enquiry', $id,
            ['status' => $enq['status']],
            ['status' => 'forwarded', 'partner_id' => $partnerId, 'routed_to_email' => $routedEmail]);

        // Best-effort partner email.
        if (($_POST['send_email'] ?? '') === '1') {
            $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
            $venueNames = implode(', ', array_map(static fn($v) => (string)$v['name'], $enq['venues']));
            $body = '<div style="font-family:Arial,sans-serif;color:#0E1B2A;">'
                . '<h2>New lead from All The Venues — ' . $esc($enq['reference']) . '</h2>'
                . '<p><strong>Contact:</strong> ' . $esc($enq['name']) . ' &lt;' . $esc($enq['email']) . '&gt;'
                . ($enq['phone'] ? ' · ' . $esc($enq['phone']) : '') . '</p>'
                . ($venueNames ? '<p><strong>Venue(s):</strong> ' . $esc($venueNames) . '</p>' : '')
                . ($enq['event_type_name'] ? '<p><strong>Event:</strong> ' . $esc($enq['event_type_name']) . '</p>' : '')
                . ($enq['event_date'] ? '<p><strong>Date:</strong> ' . $esc($enq['event_date']) . '</p>' : '')
                . ($enq['guest_count'] ? '<p><strong>Guests:</strong> ' . $esc(venue_guest_bands()[$enq['guest_count']][0] ?? $enq['guest_count']) . '</p>' : '')
                . ($enq['notes'] ? '<p><strong>Notes:</strong><br>' . nl2br($esc($enq['notes'])) . '</p>' : '')
                . '</div>';
            if (!send_mail($routedEmail, 'New lead ' . $enq['reference'] . ' — All The Venues', $body)) {
                error_log('enquiry ' . $enq['reference'] . ': partner forward email to ' . $routedEmail . ' failed');
            }
        }

        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Forwarded to ' . $partner['org_name'] . '.'];
        redirect($detailUrl);
    }

    if ($action === 'delete') {
        // Audit BEFORE the row is gone (capture identifying fields).
        audit_log($pdo, $uid, 'delete', 'enquiry', $id,
            ['reference' => $enq['reference'], 'email' => $enq['email'], 'mode' => $enq['mode']], null);

        if (enquiry_delete($pdo, $id)) {
            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Enquiry deleted.'];
            redirect('admin/enquiries');
        }
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Could not delete the enquiry. Please try again.'];
        redirect($detailUrl);
    }
}

/* ============================ 404 ======================================== */
http_response_code(404);
$admin_active = 'enquiries'; $page_title = 'Not found — Admin';
$admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
$admin_content_view = __DIR__ . '/placeholder-content.php';
require __DIR__ . '/layout.php';

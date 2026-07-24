<?php
declare(strict_types=1);

/**
 * Admin → partner email: compose / preview / send + log, a JSON template
 * endpoint, and a read-only view of a stored email. Required from partners.php,
 * so $pdo, $me and $rest ('{id}/email…') are already in scope and the route is
 * gated admin+editor (providers.manage) by the admin dispatch. Re-asserted here.
 */

require_once __DIR__ . '/../../lib/partner_email.php';
require_once __DIR__ . '/../../lib/mail.php';

auth_require_role(['admin', 'editor']);   // defence in depth

$notFound = static function (): void {
    http_response_code(404);
    global $admin_active, $page_title, $admin_page_title, $admin_notfound, $sectionTitle, $admin_content_view;
    $admin_active = 'partners'; $page_title = 'Not found — Admin';
    $admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
    $admin_content_view = __DIR__ . '/placeholder-content.php';
    require __DIR__ . '/layout.php';
};

if (!preg_match('#^(\d+)/email(?:/(.*))?$#', $rest, $m)) { $notFound(); return; }
$pid  = (int)$m[1];
$tail = trim((string)($m[2] ?? ''), '/');   // '' | 'template' | '{eid}/view'

$partner = partner_admin_get($pdo, $pid);
if ($partner === null) { $notFound(); return; }

$orgName      = (string)($partner['org_name'] ?? 'this provider');
$partnerEmail = trim((string)($partner['email'] ?? ''));

/* ---- JSON template endpoint (same-origin; fetched by app.js) -------------- */
if ($tail === 'template') {
    header('Content-Type: application/json; charset=utf-8');
    $key  = (string)($_GET['key'] ?? '');
    $tpls = partner_email_templates();
    if (!isset($tpls[$key])) {
        http_response_code(404);
        echo json_encode(['error' => 'unknown template']);
        return;
    }
    $vars = partner_email_vars($pdo, $partner);
    echo json_encode([
        'subject' => partner_email_substitute($tpls[$key]['subject'], $vars),
        'body'    => partner_email_substitute($tpls[$key]['body'], $vars),
    ]);
    return;
}

/* ---- Read-only view of a stored email ------------------------------------ */
if (preg_match('#^(\d+)/view$#', $tail, $vm)) {
    $storedEmail = partner_email_get($pdo, (int)$vm[1], $pid);
    if ($storedEmail === null) { $notFound(); return; }
    $admin_active       = 'partners';
    $page_title         = 'Email — Admin';
    $admin_page_title   = 'Email to ' . $orgName;
    $admin_content_view = __DIR__ . '/partner-email-view.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ---- Compose (GET form / POST preview|send) ------------------------------ */
if ($tail !== '') { $notFound(); return; }

$vars = partner_email_vars($pdo, $partner);
$tpls = partner_email_templates();

// No email on record → block compose (notice + link to Edit).
$noEmail = ($partnerEmail === '' || !filter_var($partnerEmail, FILTER_VALIDATE_EMAIL));

$errors      = [];
$previewHtml = null;
$form = [
    'template_key' => 'intro',
    'to'           => $partnerEmail,
    'cc'           => '',
    'bcc'          => '',
    'subject'      => partner_email_substitute($tpls['intro']['subject'], $vars),
    'body'         => partner_email_substitute($tpls['intro']['body'], $vars),
];

if (!$noEmail && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $tk     = (string)($_POST['template_key'] ?? '');
    $form['template_key'] = isset($tpls[$tk]) ? $tk : '';
    $form['to']      = trim((string)($_POST['to'] ?? ''));
    $form['cc']      = trim((string)($_POST['cc'] ?? ''));
    $form['bcc']     = trim((string)($_POST['bcc'] ?? ''));
    $form['subject'] = trim((string)($_POST['subject'] ?? ''));
    $form['body']    = (string)($_POST['body'] ?? '');

    // Each cc/bcc token must be a valid email (blank list is fine).
    $badAddr = static function (string $raw): bool {
        foreach (preg_split('/[,;]+/', $raw) ?: [] as $a) {
            $a = trim($a);
            if ($a !== '' && !filter_var($a, FILTER_VALIDATE_EMAIL)) { return true; }
        }
        return false;
    };

    if (!csrf_validate()) {
        $errors['_form'] = 'Your session expired. Please review and send again.';
    } elseif ($action === 'preview') {
        $previewHtml = partner_email_preview_html(partner_email_substitute($form['body'], $vars));
    } elseif ($action === 'send') {
        if ($form['subject'] === '')                                          { $errors['subject'] = 'Subject is required.'; }
        if (trim($form['body']) === '')                                       { $errors['body']    = 'Message body is required.'; }
        if ($form['to'] === '' || !filter_var($form['to'], FILTER_VALIDATE_EMAIL)) { $errors['to'] = 'Enter a valid recipient email.'; }
        if ($form['cc'] !== '' && $badAddr($form['cc']))                      { $errors['cc']  = 'One or more CC addresses are invalid.'; }
        if ($form['bcc'] !== '' && $badAddr($form['bcc']))                    { $errors['bcc'] = 'One or more BCC addresses are invalid.'; }

        if (!$errors) {
            // Resolve any {{var}} still in the edited body, then render + send.
            $bodyText = partner_email_substitute($form['body'], $vars);
            $bodyHtml = partner_email_render($bodyText);
            $meta = null;
            $ok = send_mail($form['to'], $form['subject'], $bodyHtml, $bodyText, $form['cc'], $form['bcc'], $meta);

            $emailId = partner_email_log_insert($pdo, [
                'partner_id'      => $pid,
                'template_key'    => $form['template_key'],
                'recipient_email' => $form['to'],
                'cc'              => $form['cc'],
                'bcc'             => $form['bcc'],
                'subject'         => $form['subject'],
                'body_html'       => $bodyHtml,
                'body_text'       => $bodyText,
                'sent_by'         => (int)($me['id'] ?? 0),
                'status'          => $ok ? 'sent' : 'failed',
                'error_message'   => $ok ? '' : (string)($meta['error'] ?? ''),
                'message_id'      => (string)($meta['message_id'] ?? ''),
                'sent_at'         => date('Y-m-d H:i:s'),
            ]);
            audit_log($pdo, (int)($me['id'] ?? 0) ?: null, 'email', 'partner', $pid, null,
                ['email_id' => $emailId, 'to' => $form['to'], 'subject' => $form['subject'], 'status' => $ok ? 'sent' : 'failed']);

            $_SESSION['admin_flash'] = $ok
                ? ['type' => 'success', 'msg' => 'Email sent to ' . $form['to'] . ' on ' . date('j M Y') . '.']
                : ['type' => 'error',   'msg' => 'Email could not be sent to ' . $form['to'] . '. It has been logged as failed.'];
            redirect('admin/partners/edit?id=' . $pid);
        }
    }
}

$admin_active       = 'partners';
$page_title         = 'Email ' . $orgName . ' — Admin';
$admin_page_title   = 'Email ' . $orgName;
$admin_content_view = __DIR__ . '/partner-email-compose.php';
require __DIR__ . '/layout.php';

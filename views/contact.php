<?php
declare(strict_types=1);

/**
 * Contact handler: /contact
 *
 * GET  — render the general contact form.
 * POST — CSRF + honeypot + rate-limit + Turnstile + validation, then save as a
 *        'general' enquiry (admin inbox), attempt admin + user email, PRG.
 *
 * Mirrors views/become-partner.php's security stack. Honeypot: hidden field
 * `contact_fax` (reject when non-empty).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/enquiry.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/ratelimit.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/email_template.php';

$pdo = db_pdo();

/** Reason allowlist (value === label; the select + validation source of truth). */
$reasons = ['General enquiry', 'Venue enquiry', 'Provider interest', 'Listing update', 'Support', 'Other'];

/* ---- Success page (PRG target) ------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['submitted'])
    && !empty($_SESSION['contact_flash'])) {
    $flash = $_SESSION['contact_flash'];
    unset($_SESSION['contact_flash']);
    $reference    = (string)$flash['reference'];
    $page_title   = 'Message received — All The Venues';
    $content_view = __DIR__ . '/content/contact-success.php';
    require __DIR__ . '/layout.php';
    exit;
}

$errors    = [];
$formError = null;
$old       = [];

/* ---- POST --------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = $_POST;

    // 1) CSRF
    if (!csrf_validate()) {
        $formError = 'Your session expired. Please review your details and submit again.';
    }
    // 2) Honeypot — silently reject.
    elseif (trim((string)($_POST['contact_fax'] ?? '')) !== '') {
        error_log('contact honeypot triggered ip=' . client_ip());
        $formError = 'We could not process your request. Please try again.';
    }
    // 3) Rate limiting (per IP + per email)
    elseif (!ratelimit_hit('contact_ip', client_ip(), 20, 3600)
         || !ratelimit_hit('contact_email', (string)($_POST['email'] ?? 'none'), 6, 3600)) {
        $formError = 'Too many requests from this connection. Please try again later.';
    }
    // 4) Turnstile
    elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
        $formError = 'Bot check failed. Please try again.';
    }
    else {
        // 5) Validation
        $clean = [];

        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!in_array($reason, $reasons, true)) {
            $errors['reason'] = 'Please choose a reason for contacting us.';
            $reason = '';
        }
        $clean['reason_label'] = $reason;

        $clean['name'] = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 255);
        if ($clean['name'] === '') { $errors['name'] = 'Please enter your name.'; }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        $clean['email'] = mb_substr($email, 0, 255);

        $clean['phone'] = mb_substr(trim(strip_tags((string)($_POST['phone'] ?? ''))), 0, 50);

        $clean['message'] = mb_substr(trim(strip_tags((string)($_POST['message'] ?? ''))), 0, 5000);
        if ($clean['message'] === '') { $errors['message'] = 'Please enter a message.'; }

        $consent = ($_POST['consent'] ?? '') === '1' || ($_POST['consent'] ?? '') === 'on';
        if (!$consent) { $errors['consent'] = 'Please agree to be contacted and to the terms.'; }
        $clean['consent_to_share'] = $consent ? 1 : 0;

        $clean['source_page'] = mb_substr((string)($_POST['source_page'] ?? '/contact'), 0, 255);

        if (!$errors) {
            try {
                $saved = contact_insert($pdo, $clean);

                // Emails — best-effort, never hard-fail the submit.
                try {
                    $lines = [
                        'Reason'  => $clean['reason_label'],
                        'Name'    => $clean['name'],
                        'Email'   => $clean['email'],
                        'Phone'   => $clean['phone'] ?: '—',
                        'Message' => $clean['message'],
                    ];
                    $adminPairs = [];
                    $adminText  = ['New contact message ' . $saved['reference'], ''];
                    foreach ($lines as $k => $v) {
                        $adminPairs[] = [$k, nl2br(e((string)$v))];
                        if (trim((string)$v) !== '') { $adminText[] = $k . ': ' . trim((string)$v); }
                    }
                    $adminContent = email_intro_row('New contact message', ['A new contact message was submitted via All The Venues.'])
                        . '<tr><td style="padding:10px 28px 4px;">' . email_ref_box($saved['reference']) . '</td></tr>'
                        . email_section_row('Message details', email_rows($adminPairs), '18px 28px 26px');
                    $adminBody = email_layout('New contact message', $adminContent,
                        'New contact message ' . $saved['reference'], 'Internal notification — All The Venues admin.');
                    $adminTextBody = implode("\n", $adminText);

                    $subject = 'New contact message [' . $clean['reason_label'] . '] ' . $saved['reference'];
                    $recips  = defined('MAIL_ADMIN_RECIPIENTS') ? (string)MAIL_ADMIN_RECIPIENTS : '';
                    foreach (array_filter(array_map('trim', explode(',', $recips))) as $to) {
                        if (!send_mail($to, $subject, $adminBody, $adminTextBody)) {
                            error_log('contact ' . $saved['reference'] . ': admin email to ' . $to . ' failed');
                        }
                    }

                    $conf = email_confirmation([
                        'title'     => 'We received your message',
                        'intro'     => [
                            'Hi ' . e((string)$clean['name']) . ',',
                            'Thanks for getting in touch with All The Venues. We&rsquo;ve received your message and our team will get back to you shortly.',
                        ],
                        'reference' => $saved['reference'],
                    ]);
                    if ($clean['email'] !== '' && !send_mail($clean['email'], 'We received your message ' . $saved['reference'] . ' — All The Venues', $conf['html'], $conf['text'])) {
                        error_log('contact ' . $saved['reference'] . ': user email failed');
                    }
                } catch (Throwable $mailEx) {
                    error_log('contact ' . $saved['reference'] . ' mail error: ' . $mailEx->getMessage());
                }

                $_SESSION['contact_flash'] = ['reference' => $saved['reference']];
                redirect('contact?submitted=1');
            } catch (Throwable $e) {
                error_log('contact insert failed: ' . $e->getMessage());
                $formError = 'Something went wrong sending your message. Please try again.';
            }
        }
    }
}

/* ---- Render form -------------------------------------------------------- */
$page_title      = 'Contact — All The Venues';
$meta_description = 'Contact All The Venues — general enquiries, provider interest, listing updates and support.';
$content_view    = __DIR__ . '/content/contact.php';
require __DIR__ . '/layout.php';

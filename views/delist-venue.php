<?php
declare(strict_types=1);

/**
 * Delist-2 Part C — public "Delist a venue" handler: /delist-venue
 *
 * For operators WITHOUT a portal account to request a venue be taken down.
 * GET  — render the form.
 * POST — CSRF + honeypot + rate-limit + Turnstile + validation, then save as a
 *        'contact' enquiry marked source_page='/delist-venue' (its own inbox
 *        badge/filter), attempt admin + user email, PRG. An admin verifies and
 *        delists via the venue editor. Mirrors views/contact.php's security stack.
 * Honeypot: hidden field `delist_fax` (reject when non-empty).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/enquiry.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/ratelimit.php';
require_once __DIR__ . '/../lib/mail.php';

$pdo = db_pdo();

/** Reason allowlist (value === label; the select + validation source of truth). */
$reasons = [
    'No longer operating this venue',
    'Temporarily closed / renovation',
    'This venue should not be listed',
    'Incorrect or outdated information',
    'Other',
];

/* ---- Success page (PRG target) ------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['submitted'])
    && !empty($_SESSION['delist_flash'])) {
    $flash = $_SESSION['delist_flash'];
    unset($_SESSION['delist_flash']);
    $reference    = (string)$flash['reference'];
    $page_title   = 'Delist request received — All The Venues';
    $content_view = __DIR__ . '/content/delist-venue-success.php';
    require __DIR__ . '/layout.php';
    exit;
}

$errors    = [];
$formError = null;
$old       = [];

/* ---- POST --------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = $_POST;

    if (!csrf_validate()) {
        $formError = 'Your session expired. Please review your details and submit again.';
    } elseif (trim((string)($_POST['delist_fax'] ?? '')) !== '') {
        error_log('delist-venue honeypot triggered ip=' . client_ip());
        $formError = 'We could not process your request. Please try again.';
    } elseif (!ratelimit_hit('delist_ip', client_ip(), 15, 3600)
           || !ratelimit_hit('delist_email', (string)($_POST['email'] ?? 'none'), 5, 3600)) {
        $formError = 'Too many requests from this connection. Please try again later.';
    } elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
        $formError = 'Bot check failed. Please try again.';
    } else {
        $clean = [];

        $clean['venue_name'] = mb_substr(trim(strip_tags((string)($_POST['venue_name'] ?? ''))), 0, 255);
        if ($clean['venue_name'] === '') { $errors['venue_name'] = 'Please enter the venue name.'; }

        $clean['venue_url'] = mb_substr(trim(strip_tags((string)($_POST['venue_url'] ?? ''))), 0, 500);

        $clean['name'] = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 255);
        if ($clean['name'] === '') { $errors['name'] = 'Please enter your name.'; }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors['email'] = 'Please enter a valid work email address.';
        }
        $clean['email'] = mb_substr($email, 0, 255);

        $clean['phone'] = mb_substr(trim(strip_tags((string)($_POST['phone'] ?? ''))), 0, 50);
        $clean['role']  = mb_substr(trim(strip_tags((string)($_POST['role'] ?? ''))), 0, 120);

        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!in_array($reason, $reasons, true)) {
            $errors['reason'] = 'Please choose a reason.';
            $reason = '';
        }
        $clean['reason'] = $reason;

        $clean['proof']   = mb_substr(trim(strip_tags((string)($_POST['proof'] ?? ''))), 0, 500);
        $clean['message'] = mb_substr(trim(strip_tags((string)($_POST['message'] ?? ''))), 0, 5000);

        $consent = ($_POST['consent'] ?? '') === '1' || ($_POST['consent'] ?? '') === 'on';
        if (!$consent) { $errors['consent'] = 'Please confirm you are authorised and agree to be contacted.'; }
        $clean['consent_to_share'] = $consent ? 1 : 0;

        if (!$errors) {
            try {
                $saved = delist_request_insert($pdo, $clean);

                // Emails — best-effort, never hard-fail the submit.
                try {
                    $lines = [
                        'Venue'        => $clean['venue_name'],
                        'Public URL'   => $clean['venue_url'] ?: '—',
                        'Requester'    => $clean['name'],
                        'Work email'   => $clean['email'],
                        'Phone'        => $clean['phone'] ?: '—',
                        'Role'         => $clean['role'] ?: '—',
                        'Reason'       => $clean['reason'],
                        'Proof'        => $clean['proof'] ?: '—',
                        'Message'      => $clean['message'] ?: '—',
                    ];
                    $adminBody = '<h2>Delist request ' . e($saved['reference']) . '</h2><table cellpadding="4">';
                    foreach ($lines as $k => $v) {
                        $adminBody .= '<tr><td valign="top"><strong>' . e($k) . '</strong></td><td>' . nl2br(e((string)$v)) . '</td></tr>';
                    }
                    $adminBody .= '</table><p>Verify authority, then delist via the venue editor.</p>';

                    $subject = 'Delist request [' . $clean['venue_name'] . '] ' . $saved['reference'];
                    $recips  = defined('MAIL_ADMIN_RECIPIENTS') ? (string)MAIL_ADMIN_RECIPIENTS : '';
                    foreach (array_filter(array_map('trim', explode(',', $recips))) as $to) {
                        if (!send_mail($to, $subject, $adminBody)) {
                            error_log('delist ' . $saved['reference'] . ': admin email to ' . $to . ' failed');
                        }
                    }

                    $userBody = '<p>Thanks for contacting All The Venues.</p>'
                        . '<p>We\'ve received your request to delist <strong>' . e($clean['venue_name'])
                        . '</strong> (ref <strong>' . e($saved['reference']) . '</strong>). Our team will verify and '
                        . 'follow up. If you manage this venue on All The Venues, you can also delist it instantly '
                        . 'from your provider portal.</p>';
                    if ($clean['email'] !== '' && !send_mail($clean['email'], 'We received your delist request ' . $saved['reference'] . ' — All The Venues', $userBody)) {
                        error_log('delist ' . $saved['reference'] . ': user email failed');
                    }
                } catch (Throwable $mailEx) {
                    error_log('delist ' . $saved['reference'] . ' mail error: ' . $mailEx->getMessage());
                }

                $_SESSION['delist_flash'] = ['reference' => $saved['reference']];
                redirect('delist-venue?submitted=1');
            } catch (Throwable $e) {
                error_log('delist insert failed: ' . $e->getMessage());
                $formError = 'Something went wrong sending your request. Please try again.';
            }
        }
    }
}

/* ---- Render form -------------------------------------------------------- */
$page_title      = 'Delist a venue — All The Venues';
$meta_description = 'Request that a venue be removed from All The Venues. For venue operators without a provider account.';
$content_view    = __DIR__ . '/content/delist-venue.php';
require __DIR__ . '/layout.php';

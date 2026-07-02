<?php
declare(strict_types=1);

/**
 * Enquiry handler: /enquire
 *
 * GET  — render the 3-step form, prefilled from the mode/venue context.
 * POST — CSRF + honeypot + rate-limit + Turnstile + validation, then save
 *        (enquiries + enquiry_venues), attempt emails, and PRG-redirect to
 *        the success page. Session is already started by index.php.
 *
 * Modes: single (?venue=id), multi (?venues=1,2,3), assisted (?mode=assisted),
 * partner (?mode=partner), general (no params).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/venues.php';
require_once __DIR__ . '/../lib/enquiry.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/ratelimit.php';
require_once __DIR__ . '/../lib/mail.php';

$pdo = db_pdo();

/* ---- Success page (PRG target) ------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['submitted'])
    && !empty($_SESSION['enquiry_flash'])) {
    $flash = $_SESSION['enquiry_flash'];
    unset($_SESSION['enquiry_flash']);
    $reference = (string)$flash['reference'];
    $page_title   = 'Enquiry received — All The Venues';
    $content_view = __DIR__ . '/content/enquire-success.php';
    require __DIR__ . '/layout.php';
    exit;
}

/* ---- Shared form data --------------------------------------------------- */
$context    = enquiry_context($pdo, $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);
$eventTypes = venue_event_types($pdo);
$emirates   = venue_emirates($pdo);

$errors    = [];
$formError = null;                 // generic top-level error (security paths)
$old       = [];                   // re-fill values on validation error

/* ---- POST --------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = $_POST;

    // 1) CSRF
    if (!csrf_validate()) {
        $formError = 'Your session expired. Please review your details and submit again.';
    }
    // 2) Honeypot (bots fill hidden fields). Silently reject.
    elseif (trim((string)($_POST['website'] ?? '')) !== '') {
        error_log('enquiry honeypot triggered ip=' . client_ip());
        $formError = 'We could not process your enquiry. Please try again.';
    }
    // 3) Rate limiting (per IP + per email)
    elseif (!ratelimit_hit('enquiry_ip', client_ip(), 20, 3600)
         || !ratelimit_hit('enquiry_email', (string)($_POST['email'] ?? 'none'), 6, 3600)) {
        $formError = 'Too many enquiries from this connection. Please try again later.';
    }
    // 4) Turnstile
    elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
        $formError = 'Bot check failed. Please try again.';
    }
    else {
        // 5) Validation
        $result = enquiry_validate($pdo, $_POST);
        $errors = $result['errors'];
        $clean  = $result['clean'];

        if (!$errors) {
            $sourcePage = mb_substr((string)($_POST['source_page'] ?? ($_SERVER['HTTP_REFERER'] ?? '/enquire')), 0, 255);
            try {
                $saved = enquiry_insert($pdo, $clean, $context['venue_ids'], $sourcePage);

                // Save succeeded — attempt emails, never hard-fail the submit.
                try {
                    $rows   = enquiry_summary_rows($pdo, $clean, $context['venues']);
                    $bodies = enquiry_emails($saved['reference'], $clean, $rows);
                    $subject = 'Your enquiry ' . $saved['reference'] . ' — All The Venues';
                    if (!send_mail($clean['email'], $subject, $bodies['user'])) {
                        error_log('enquiry ' . $saved['reference'] . ': user email failed');
                    }
                    $recips = defined('MAIL_ADMIN_RECIPIENTS') ? (string)MAIL_ADMIN_RECIPIENTS : '';
                    foreach (array_filter(array_map('trim', explode(',', $recips))) as $to) {
                        if (!send_mail($to, 'New enquiry ' . $saved['reference'], $bodies['admin'])) {
                            error_log('enquiry ' . $saved['reference'] . ': admin email to ' . $to . ' failed');
                        }
                    }
                } catch (Throwable $mailEx) {
                    error_log('enquiry ' . $saved['reference'] . ' mail error: ' . $mailEx->getMessage());
                }

                // PRG: flash the reference, redirect to the success page.
                $_SESSION['enquiry_flash'] = ['reference' => $saved['reference']];
                redirect('enquire?submitted=1');
            } catch (Throwable $e) {
                error_log('enquiry insert failed: ' . $e->getMessage());
                $formError = 'Something went wrong saving your enquiry. Please try again.';
            }
        }
    }
}

/* ---- Render form -------------------------------------------------------- */
$page_title   = 'Enquire — All The Venues';
$content_view = __DIR__ . '/content/enquire.php';
require __DIR__ . '/layout.php';

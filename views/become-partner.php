<?php
declare(strict_types=1);

/**
 * Become a Venue Partner handler: /become-a-venue-partner
 *
 * GET  — render the hero + value cards + provider signup form.
 * POST — CSRF + honeypot + rate-limit + Turnstile + validation, then save as a
 *        partner_signup enquiry (structured columns), attempt emails, PRG.
 *
 * Mirrors views/enquire.php's security stack. Honeypot field is named
 * `contact_fax` (this form has a REAL `website` field).
 */

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/enquiry.php';
require_once __DIR__ . '/../lib/turnstile.php';
require_once __DIR__ . '/../lib/ratelimit.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/email_template.php';
require_once __DIR__ . '/../lib/venues.php';

$pdo = db_pdo();

/** Allowed provider types (the select + validation source of truth). */
$providerTypes = ['Hotel', 'Resort', 'Restaurant', 'Unique venue', 'Yacht provider', 'Other'];

/** Primary-location labels: active emirates + Al Ain (a city, not an emirate). */
$emirates = venue_emirates($pdo);
$locationLabels = array_map(static fn($e) => (string)$e['name'], $emirates);
if (!in_array('Al Ain', $locationLabels, true)) { $locationLabels[] = 'Al Ain'; }

/* ---- Success page (PRG target) ------------------------------------------ */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && isset($_GET['submitted'])
    && !empty($_SESSION['partner_flash'])) {
    $flash = $_SESSION['partner_flash'];
    unset($_SESSION['partner_flash']);
    $reference    = (string)$flash['reference'];
    $page_title   = 'Partner request received — All The Venues';
    $content_view = __DIR__ . '/content/become-partner-success.php';
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
    // 2) Honeypot — a DIFFERENT name to the real website field. Silently reject.
    elseif (trim((string)($_POST['contact_fax'] ?? '')) !== '') {
        error_log('partner signup honeypot triggered ip=' . client_ip());
        $formError = 'We could not process your request. Please try again.';
    }
    // 3) Rate limiting (per IP + per email)
    elseif (!ratelimit_hit('partner_ip', client_ip(), 20, 3600)
         || !ratelimit_hit('partner_email', (string)($_POST['email'] ?? 'none'), 6, 3600)) {
        $formError = 'Too many requests from this connection. Please try again later.';
    }
    // 4) Turnstile
    elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
        $formError = 'Bot check failed. Please try again.';
    }
    else {
        // 5) Validation
        $plain = static fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);
        $clean = [];

        $clean['company'] = $plain('company', 255);
        if ($clean['company'] === '') { $errors['company'] = 'Please enter your organization or provider name.'; }

        $clean['name'] = $plain('name', 255);
        if ($clean['name'] === '') { $errors['name'] = 'Please enter a contact person.'; }

        $email = trim((string)($_POST['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        $clean['email'] = mb_substr($email, 0, 255);

        $clean['phone'] = $plain('phone', 50);
        if ($clean['phone'] === '') { $errors['phone'] = 'Please enter a phone or WhatsApp number.'; }

        $ptype = trim((string)($_POST['provider_type'] ?? ''));
        if (!in_array($ptype, $providerTypes, true)) {
            $errors['provider_type'] = 'Please choose a provider type.';
        }
        $clean['provider_type'] = $ptype;

        // website — REAL field (optional; stored as-is).
        $clean['website'] = mb_substr(trim((string)($_POST['website'] ?? '')), 0, 255);

        // city_pref — optional; must be one of the offered labels.
        $loc = trim((string)($_POST['city_pref'] ?? ''));
        $clean['city_pref'] = ($loc !== '' && in_array($loc, $locationLabels, true)) ? mb_substr($loc, 0, 150) : null;

        // venues_managed — optional int.
        $vm = trim((string)($_POST['venues_managed'] ?? ''));
        $clean['venues_managed'] = $vm === '' ? null : max(0, (int)$vm);

        $clean['notes'] = mb_substr(trim(strip_tags((string)($_POST['notes'] ?? ''))), 0, 5000) ?: null;

        $consent = ($_POST['consent'] ?? '') === '1' || ($_POST['consent'] ?? '') === 'on';
        if (!$consent) { $errors['consent'] = 'Please agree to be contacted and to the terms.'; }
        $clean['consent_to_share'] = $consent ? 1 : 0;

        $clean['source_page'] = mb_substr((string)($_POST['source_page'] ?? '/become-a-venue-partner'), 0, 255);

        if (!$errors) {
            try {
                $saved = partner_signup_insert($pdo, $clean);

                // Emails — best-effort, never hard-fail the submit.
                try {
                    $lines = [
                        'Organization' => $clean['company'],
                        'Contact'      => $clean['name'],
                        'Email'        => $clean['email'],
                        'Phone'        => $clean['phone'],
                        'Provider type'=> $clean['provider_type'],
                        'Website'      => $clean['website'] ?: '—',
                        'Location'     => $clean['city_pref'] ?: '—',
                        'Venues managed' => $clean['venues_managed'] !== null ? (string)$clean['venues_managed'] : '—',
                        'Message'      => $clean['notes'] ?: '—',
                    ];
                    $adminPairs = [];
                    $adminText  = ['New venue partner request ' . $saved['reference'], ''];
                    foreach ($lines as $k => $v) {
                        $adminPairs[] = [$k, e((string)$v)];
                        if (trim((string)$v) !== '') { $adminText[] = $k . ': ' . trim((string)$v); }
                    }
                    $adminContent = email_intro_row('New venue partner request', ['A new venue partner request was submitted via All The Venues.'])
                        . '<tr><td style="padding:10px 28px 4px;">' . email_ref_box($saved['reference']) . '</td></tr>'
                        . email_section_row('Request details', email_rows($adminPairs), '18px 28px 26px');
                    $adminBody = email_layout('New partner request', $adminContent,
                        'New venue partner request ' . $saved['reference'], 'Internal notification — All The Venues admin.');
                    $adminTextBody = implode("\n", $adminText);

                    $recips = defined('MAIL_ADMIN_RECIPIENTS') ? (string)MAIL_ADMIN_RECIPIENTS : '';
                    foreach (array_filter(array_map('trim', explode(',', $recips))) as $to) {
                        if (!send_mail($to, 'New venue partner request ' . $saved['reference'], $adminBody, $adminTextBody)) {
                            error_log('partner ' . $saved['reference'] . ': admin email to ' . $to . ' failed');
                        }
                    }

                    $conf = email_confirmation([
                        'title'     => 'Thank you for your interest',
                        'intro'     => [
                            'Hi ' . e((string)$clean['name']) . ',',
                            'Thank you for your interest in partnering with All The Venues. We&rsquo;ve received your request and our team will review it and be in touch shortly.',
                        ],
                        'reference' => $saved['reference'],
                    ]);
                    if ($clean['email'] !== '' && !send_mail($clean['email'], 'Your partner request ' . $saved['reference'] . ' — All The Venues', $conf['html'], $conf['text'])) {
                        error_log('partner ' . $saved['reference'] . ': user email failed');
                    }
                } catch (Throwable $mailEx) {
                    error_log('partner ' . $saved['reference'] . ' mail error: ' . $mailEx->getMessage());
                }

                $_SESSION['partner_flash'] = ['reference' => $saved['reference']];
                redirect('become-a-venue-partner?submitted=1');
            } catch (Throwable $e) {
                error_log('partner signup insert failed: ' . $e->getMessage());
                $formError = 'Something went wrong saving your request. Please try again.';
            }
        }
    }
}

/* ---- Render form -------------------------------------------------------- */
$page_title      = 'Become a Venue Partner — All The Venues';
$meta_description = 'List your venues on All The Venues and receive qualified, structured event enquiries — tell us about your spaces and our team will follow up.';
$content_view    = __DIR__ . '/content/become-partner.php';
require __DIR__ . '/layout.php';

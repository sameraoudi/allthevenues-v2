<?php
declare(strict_types=1);

/**
 * Admin → partner email: template registry, {{variable}} substitution, and
 * plain-text → branded-HTML rendering (via lib/email_template.php). Used by the
 * admin compose page (/admin/partners/{id}/email) and logged to partner_emails
 * (migration 024). MVP = 4 core templates; the table + this registry are shaped
 * so the remaining templates + venue/lead context slot in later.
 */

require_once __DIR__ . '/helpers.php';         // base_url(), e()
require_once __DIR__ . '/email_template.php';   // email_layout()

/**
 * The 4 core templates, keyed by template_key. Each: ['label','subject','body'].
 * `body` is PLAIN TEXT with {{variables}}, blank-line paragraphs, and "- " bullet
 * lines — rendered to branded HTML at send (partner_email_render). Copy is locked.
 */
function partner_email_templates(): array
{
    return [
        'intro' => [
            'label'   => 'Intro email',
            'subject' => 'Welcome to the new All The Venues partner portal',
            'body'    =>
"Hello {{partner_name}},

We're pleased to introduce the updated All The Venues platform.

All The Venues is now live as a UAE venue discovery and lead-generation platform, helping event planners find suitable venues and submit structured enquiries through the website.

As a venue partner, you can now request access to manage your venue listings directly through the All The Venues Partner Portal. Through the portal, you can:
- View the venues linked to your account.
- Keep venue details up to date.
- Upload approved venue photos.
- Submit new venues for review.
- Request changes to published listings.
- Claim venues you operate.
- Request delisting if a venue should no longer appear publicly.

To protect the quality of the directory, changes are reviewed by All The Venues before they appear on the public website.

If you would like portal access for your team, please reply to this email and we'll help set it up.

Kind regards,
All The Venues",
        ],

        'photo_permission' => [
            'label'   => 'Photo permission email',
            'subject' => 'Image permission for your All The Venues listing',
            'body'    =>
"Hello {{partner_name}},

We are reviewing the photos used on your venue listing on All The Venues.

To make sure all images are approved for use, please confirm whether All The Venues has permission to display the selected venue images on allthevenues.com. The images may be used on:
- Your venue listing.
- Venue cards.
- Search and discovery pages.
- Event type pages.
- Location pages.
- Related landing pages on All The Venues.

Please confirm that you own the images or have the necessary rights and permissions to allow All The Venues to display, crop, resize, optimise, and use them for the purposes above.

If you prefer, you may also send us updated official venue images that you would like us to use.

Kind regards,
All The Venues",
        ],

        'partnership' => [
            'label'   => 'Partnership email',
            'subject' => 'Partnering with All The Venues',
            'body'    =>
"Hello {{partner_name}},

We would be pleased to explore a partnership with you on All The Venues.

All The Venues is a UAE venue discovery and lead-generation platform designed to help event planners find suitable venues and submit structured enquiries. As a venue partner, your venues can benefit from:
- A dedicated presence on All The Venues.
- Structured event enquiries routed from the platform.
- The ability to manage and update venue information through the Partner Portal.
- Photo and listing review support.
- Options for enhanced visibility, including featured placements where available.

Our aim is to make venue discovery easier for event planners while helping partners receive clearer, better-structured enquiries.

If you are interested, please reply to this email and we'll be happy to discuss the next steps.

Kind regards,
All The Venues",
        ],

        'general' => [
            'label'   => 'General email',
            'subject' => 'Message from All The Venues',
            'body'    =>
"Hello {{partner_name}},

[Write your message here.]

Kind regards,
All The Venues",
        ],
    ];
}

/**
 * Substitution map for {{variables}}, resolved for THIS partner.
 * @param array $partner a partners row (needs id + org_name)
 */
function partner_email_vars(PDO $pdo, array $partner): array
{
    $orgName = trim((string)($partner['org_name'] ?? ''));

    $venues = [];
    if ((int)($partner['id'] ?? 0) > 0) {
        $stmt = $pdo->prepare('SELECT name FROM venues WHERE partner_id = :pid ORDER BY name ASC');
        $stmt->execute([':pid' => (int)$partner['id']]);
        $venues = array_map(static fn($r) => (string)$r['name'], $stmt->fetchAll());
    }

    $adminName = '';
    if (function_exists('auth_user')) {
        $u = auth_user();
        $adminName = trim((string)($u['name'] ?? ''));
    }

    return [
        'partner_name'  => $orgName !== '' ? $orgName : 'there',
        'provider_name' => $orgName !== '' ? $orgName : 'there',
        'portal_link'   => base_url('portal/login'),
        'site_url'      => base_url('/'),
        'admin_name'    => $adminName !== '' ? $adminName : 'All The Venues',
        'venue_list'    => implode(', ', $venues),
    ];
}

/**
 * Resolve {{var}} tokens against $vars; any UNKNOWN token is stripped (so no
 * raw {{placeholder}} can ever reach a partner). Applied on template load and
 * again defensively at send.
 */
function partner_email_substitute(string $text, array $vars): string
{
    return (string)preg_replace_callback(
        '/\{\{\s*([a-z_]+)\s*\}\}/i',
        static function (array $m) use ($vars): string {
            $key = strtolower($m[1]);
            return array_key_exists($key, $vars) ? (string)$vars[$key] : '';
        },
        $text
    );
}

/**
 * Convert a plain-text body to the branded ATV email HTML:
 *   - blank-line-separated blocks → paragraphs (single newlines within → <br>)
 *   - consecutive "- " / "* " lines → a <ul><li> list
 * Everything is escaped (e()). Wrapped by email_layout() with the PARTNER footer
 * note. body_text stays the plain text (the caller keeps it separately).
 */
function partner_email_render(string $plainBody): string
{
    $lines = preg_split('/\r\n|\r|\n/', $plainBody) ?: [];
    $html  = '';
    $para  = [];   // buffered paragraph lines
    $list  = [];   // buffered list items

    $flushPara = static function () use (&$para, &$html): void {
        if ($para) {
            $html .= '<p style="font-size:15px;line-height:1.6;color:#40525f;margin:0 0 14px;">'
                   . implode('<br>', array_map('e', $para)) . '</p>';
            $para = [];
        }
    };
    $flushList = static function () use (&$list, &$html): void {
        if ($list) {
            $items = '';
            foreach ($list as $it) {
                $items .= '<li style="margin:0 0 6px;">' . e($it) . '</li>';
            }
            $html .= '<ul style="margin:0 0 16px;padding-left:20px;font-size:14px;line-height:1.7;color:#1c2b38;">'
                   . $items . '</ul>';
            $list = [];
        }
    };

    foreach ($lines as $ln) {
        $t = rtrim($ln);
        if (trim($t) === '') { $flushPara(); $flushList(); continue; }
        if (preg_match('/^\s*[-*]\s+(.*)$/', $t, $m)) {
            $flushPara();
            $list[] = trim($m[1]);
        } else {
            $flushList();
            $para[] = trim($t);
        }
    }
    $flushPara();
    $flushList();

    $content = '<tr><td style="padding:26px 28px 26px;">' . ($html !== '' ? $html : '&nbsp;') . '</td></tr>';

    $footerNote = 'All The Venues — UAE venue discovery and event enquiry platform. '
        . 'Operated by Bianca Event Styling. allthevenues.com. '
        . 'You are receiving this email because your venue or organisation is listed on All The Venues, '
        . 'or because you have communicated with us about venue listings or partnership access.';

    return email_layout('', $content, '', $footerNote);
}

/**
 * CSP-safe branded PREVIEW of a plain-text body for the admin page (brand.css
 * .pe-mail* classes, NO inline styles — the admin CSP is style-src 'self' and
 * frame-src excludes 'self', so the inline-styled send HTML can't be shown
 * inline or iframed). Same block parsing as partner_email_render; used for the
 * compose preview pane and the stored-email View. The SENT email still uses
 * partner_email_render() (inline styles, for email clients).
 */
function partner_email_preview_html(string $plainBody): string
{
    $lines = preg_split('/\r\n|\r|\n/', $plainBody) ?: [];
    $html  = '';
    $para  = [];
    $list  = [];
    $flushPara = static function () use (&$para, &$html): void {
        if ($para) { $html .= '<p>' . implode('<br>', array_map('e', $para)) . '</p>'; $para = []; }
    };
    $flushList = static function () use (&$list, &$html): void {
        if ($list) {
            $items = '';
            foreach ($list as $it) { $items .= '<li>' . e($it) . '</li>'; }
            $html .= '<ul>' . $items . '</ul>';
            $list = [];
        }
    };
    foreach ($lines as $ln) {
        $t = rtrim($ln);
        if (trim($t) === '') { $flushPara(); $flushList(); continue; }
        if (preg_match('/^\s*[-*]\s+(.*)$/', $t, $m)) { $flushPara(); $list[] = trim($m[1]); }
        else { $flushList(); $para[] = trim($t); }
    }
    $flushPara();
    $flushList();

    $foot = 'All The Venues — UAE venue discovery and event enquiry platform. '
        . 'Operated by Bianca Event Styling. allthevenues.com. '
        . 'You are receiving this email because your venue or organisation is listed on All The Venues, '
        . 'or because you have communicated with us about venue listings or partnership access.';

    return '<div class="pe-mail">'
        . '<div class="pe-mail__head">All The <span>Venues</span></div>'
        . '<div class="pe-mail__body">' . ($html !== '' ? $html : '&nbsp;') . '</div>'
        . '<div class="pe-mail__foot">' . e($foot) . '</div></div>';
}

/* ==========================================================================
 * partner_emails log (migration 024) — data access.
 * ======================================================================== */

/** Human label for a stored template_key ('Custom' when null/unknown). */
function partner_email_template_label(?string $key): string
{
    if ($key === null || $key === '') { return 'Custom'; }
    $t = partner_email_templates();
    return $t[$key]['label'] ?? 'Custom';
}

/** Insert a partner_emails row; returns the new id. */
function partner_email_log_insert(PDO $pdo, array $d): int
{
    $blankNull = static fn($v) => (is_string($v) && trim($v) !== '') ? $v : null;
    $stmt = $pdo->prepare(
        'INSERT INTO partner_emails
            (partner_id, template_key, recipient_email, cc, bcc, subject, body_html, body_text,
             sent_by, status, error_message, message_id, related_venue_id, related_enquiry_id, sent_at)
         VALUES
            (:partner_id, :template_key, :recipient_email, :cc, :bcc, :subject, :body_html, :body_text,
             :sent_by, :status, :error_message, :message_id, :related_venue_id, :related_enquiry_id, :sent_at)'
    );
    $stmt->execute([
        ':partner_id'         => (int)$d['partner_id'],
        ':template_key'       => $blankNull((string)($d['template_key'] ?? '')),
        ':recipient_email'    => (string)($d['recipient_email'] ?? ''),
        ':cc'                 => $blankNull((string)($d['cc'] ?? '')),
        ':bcc'                => $blankNull((string)($d['bcc'] ?? '')),
        ':subject'            => (string)($d['subject'] ?? ''),
        ':body_html'          => (string)($d['body_html'] ?? ''),
        ':body_text'          => (string)($d['body_text'] ?? ''),
        ':sent_by'            => ((int)($d['sent_by'] ?? 0)) ?: null,
        ':status'             => in_array($d['status'] ?? 'sent', ['draft', 'sent', 'failed'], true) ? $d['status'] : 'sent',
        ':error_message'      => $blankNull((string)($d['error_message'] ?? '')),
        ':message_id'         => $blankNull((string)($d['message_id'] ?? '')),
        ':related_venue_id'   => ((int)($d['related_venue_id'] ?? 0)) ?: null,
        ':related_enquiry_id' => ((int)($d['related_enquiry_id'] ?? 0)) ?: null,
        ':sent_at'            => $d['sent_at'] ?? null,
    ]);
    return (int)$pdo->lastInsertId();
}

/** Email history for a partner (newest first), each with the sender's name. */
function partner_email_history(PDO $pdo, int $partnerId): array
{
    $stmt = $pdo->prepare(
        'SELECT pe.*, u.name AS sent_by_name
           FROM partner_emails pe
           LEFT JOIN users u ON u.id = pe.sent_by
          WHERE pe.partner_id = :pid
          ORDER BY pe.sent_at DESC, pe.id DESC'
    );
    $stmt->execute([':pid' => $partnerId]);
    return $stmt->fetchAll();
}

/** One stored email, scoped to its partner (null if not found / wrong partner). */
function partner_email_get(PDO $pdo, int $id, int $partnerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT pe.*, u.name AS sent_by_name
           FROM partner_emails pe
           LEFT JOIN users u ON u.id = pe.sent_by
          WHERE pe.id = :id AND pe.partner_id = :pid LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':pid' => $partnerId]);
    return $stmt->fetch() ?: null;
}

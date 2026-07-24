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
require_once __DIR__ . '/users_admin.php';      // user_active_staff() for reply-to options

/** Default reply-to for admin → partner emails (a real, monitored inbox). */
const PARTNER_EMAIL_REPLY_TO_DEFAULT = 'samer@allthevenues.com';

/**
 * Reply-to options for the compose form: the default address FIRST (preselected),
 * then every active admin/editor staff member with an email. Each: ['email','label'].
 * Deduplicated by email (if the default is itself a staff account it appears once).
 */
function partner_email_reply_to_options(PDO $pdo): array
{
    $default = PARTNER_EMAIL_REPLY_TO_DEFAULT;
    $staff   = function_exists('user_active_staff') ? user_active_staff($pdo) : [];

    $defaultOpt = null;
    $rest = [];
    foreach ($staff as $u) {
        $email = trim((string)($u['email'] ?? ''));
        if ($email === '') { continue; }
        $label = (trim((string)($u['name'] ?? '')) !== '' ? trim((string)$u['name']) : $email) . ' — ' . (string)($u['role'] ?? '');
        $opt = ['email' => $email, 'label' => $label];
        if (strcasecmp($email, $default) === 0) { $defaultOpt = $opt; }
        else { $rest[] = $opt; }
    }
    if ($defaultOpt === null) {
        $defaultOpt = ['email' => $default, 'label' => $default . ' (default)'];
    }

    $out  = array_merge([$defaultOpt], $rest);
    $seen = [];
    $final = [];
    foreach ($out as $o) {
        $k = strtolower($o['email']);
        if (isset($seen[$k])) { continue; }
        $seen[$k] = true;
        $final[] = $o;
    }
    return $final;
}

/** Allowed reply-to emails (lowercased) — the server-side allowlist for send. */
function partner_email_reply_to_allowed(PDO $pdo): array
{
    return array_map(static fn($o) => strtolower($o['email']), partner_email_reply_to_options($pdo));
}

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
            'subject' => 'Welcome to our new All The Venues partner portal',
            'body'    =>
"Hello {{partner_name}},

We're excited to share that our new All The Venues platform is now live.

We've updated All The Venues to make venue discovery easier for event planners across the UAE and to help our venue partners receive clearer, more structured enquiries.

As part of this update, you can now manage your venues through our Partner Portal. Through the portal, you can:
- View the venues linked to your account.
- Keep your venue details up to date.
- Upload approved venue photos.
- Submit new venues for review.
- Request changes to published listings.
- Claim venues you operate.
- Request delisting if a venue should no longer appear publicly.

Our goal is to give you more control over how your venues appear on All The Venues, while keeping the quality and consistency of the platform high. Any changes that affect the public listing are reviewed by our team before they go live.

If you would like access to the Partner Portal, simply reply to this email and we'll help set it up for you.

We're looking forward to working more closely with you through the new platform.

Kind regards,
All The Venues",
        ],

        'photo_permission' => [
            'label'   => 'Photo permission email',
            'subject' => 'Confirming image use for your All The Venues listing',
            'body'    =>
"Hello {{partner_name}},

We're updating venue listings on our All The Venues platform and want to make sure your venue images are approved and up to date.

Could you please confirm that we may use the selected images for your venue on allthevenues.com?

The images may appear on your venue listing, venue cards, search pages, event type pages, location pages, and related All The Venues landing pages.

Please confirm that you own the images or have the necessary permission for us to display, crop, resize, optimise, and use them on our website to promote your venue.

If you prefer, you're welcome to send us newer official images that you would like us to use instead.

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

/**
 * For a list of partners, which of the key templates have been SENT and when:
 * partner_id => [template_key => last_sent_at]. One query (no N+1). Pass a list
 * of ids to scope it (empty result for []), or null for all partners.
 */
function partner_email_sent_map(PDO $pdo, ?array $partnerIds = null): array
{
    $sql = "SELECT partner_id, template_key, MAX(sent_at) AS last_sent
              FROM partner_emails
             WHERE status = 'sent'
               AND template_key IN ('intro','photo_permission','partnership')";
    $params = [];
    if ($partnerIds !== null) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $partnerIds), static fn($i) => $i > 0)));
        if (!$ids) { return []; }
        $sql   .= ' AND partner_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $params = $ids;
    }
    $sql .= ' GROUP BY partner_id, template_key';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $map = [];
    foreach ($stmt->fetchAll() as $r) {
        $map[(int)$r['partner_id']][(string)$r['template_key']] = (string)$r['last_sent'];
    }
    return $map;
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

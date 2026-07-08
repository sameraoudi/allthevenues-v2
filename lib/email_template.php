<?php
declare(strict_types=1);

/**
 * Branded transactional-email template (email-client-safe): table layout + INLINE
 * styles + web-safe fonts (Georgia headings / Arial body) + absolute URLs
 * (BASE_URL, via base_url()) + a styled-text wordmark (no header image) + NO
 * external CSS/JS. Built to docs/atv-lead-email-preview.html.
 *
 * PART A — reusable shell + components (email_layout / email_section / email_rows
 * / email_button / email_ref_box). PART B — email_lead_forward() builds the lead
 * email from enquiry data (html + plain-text AltBody). Other transactional emails
 * adopt these as a fast-follow (not here).
 */

require_once __DIR__ . '/helpers.php';   // e(), base_url()

/* ==========================================================================
 * PART A — reusable branded shell + inline-styled components.
 * ======================================================================== */

/** Full branded HTML shell: navy header (wordmark + right tag) + card + footer. */
function email_layout(string $title, string $contentHtml, string $preheader = ''): string
{
    $t    = e($title);
    $year = date('Y');
    $pre  = $preheader !== ''
        ? '<span style="display:none;font-size:1px;color:#e9ebe6;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . e($preheader) . '</span>'
        : '';

    return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $t . '</title></head>'
        . '<body style="margin:0;padding:0;background:#e9ebe6;">' . $pre
        . '<div style="max-width:640px;margin:0 auto;padding:20px 12px;font-family:Arial,Helvetica,sans-serif;color:#1c2b38;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e4e8ec;border-radius:14px;overflow:hidden;">'
        // header band
        . '<tr><td style="background:#0E1B2A;padding:22px 28px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        . '<td style="font-family:Georgia,\'Times New Roman\',serif;font-size:22px;color:#ffffff;">All The <span style="color:#6C93B7;">Venues</span></td>'
        . '<td align="right" style="font-size:12px;color:#9fb0bd;letter-spacing:.5px;text-transform:uppercase;">' . $t . '</td>'
        . '</tr></table></td></tr>'
        // content rows (each caller-provided <tr>…</tr>)
        . $contentHtml
        . '</table>'
        // footer
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:18px 28px;text-align:center;">'
        . '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:16px;color:#0E1B2A;">All The Venues</div>'
        . '<p style="font-size:12px;color:#6b7b88;margin:6px 0 8px;">Curated UAE venues &middot; one structured enquiry</p>'
        . '<p style="font-size:12px;color:#6b7b88;margin:0;">'
        . '<a href="' . e(base_url('/')) . '" style="color:#6b7b88;">allthevenues.com</a> &nbsp;&middot;&nbsp; '
        . '<a href="' . e(base_url('contact')) . '" style="color:#6b7b88;">Contact</a></p>'
        . '<p style="font-size:11px;color:#9aa6af;margin:10px 0 0;">You received this because a venue you manage received an enquiry via All The Venues. &copy; ' . $year . ' All The Venues.</p>'
        . '</td></tr></table></div></body></html>';
}

/** A sand-underlined section header + its inner body HTML (returns inner content). */
function email_section(string $label, string $innerHtml): string
{
    return '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:#6b7b88;font-weight:bold;border-bottom:2px solid #D9C7A8;padding-bottom:6px;margin-bottom:8px;">'
        . e($label) . '</div>' . $innerHtml;
}

/**
 * Label/value rows as a <table>. $pairs is a list of [label, valueHtml]; a row is
 * SKIPPED when its value is empty (after stripping tags/whitespace). Returns '' if
 * every row is empty (caller can then omit the whole section).
 */
function email_rows(array $pairs): string
{
    $rows = '';
    foreach ($pairs as $pair) {
        $label = (string)($pair[0] ?? '');
        $value = (string)($pair[1] ?? '');
        if (trim(strip_tags($value)) === '') { continue; }
        $rows .= '<tr><td style="padding:6px 0;color:#6b7b88;width:170px;vertical-align:top;">' . e($label) . '</td>'
              . '<td style="padding:6px 0;vertical-align:top;">' . $value . '</td></tr>';
    }
    return $rows === '' ? '' : '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#1c2b38;">' . $rows . '</table>';
}

/** A bulletproof button as a table CELL (place cells in a row; spacer <td> between). */
function email_button(string $label, string $url, bool $primary = true): string
{
    $href = e($url);
    if ($primary) {
        return '<td style="border-radius:8px;background:#426F94;">'
            . '<a href="' . $href . '" style="display:inline-block;padding:12px 22px;font-size:14px;font-weight:bold;color:#ffffff;text-decoration:none;">' . $label . '</a></td>';
    }
    return '<td style="border-radius:8px;border:1px solid #cdd5dc;">'
        . '<a href="' . $href . '" style="display:inline-block;padding:12px 22px;font-size:14px;font-weight:bold;color:#426F94;text-decoration:none;">' . $label . '</a></td>';
}

/** The highlighted lead-reference box. */
function email_ref_box(string $reference): string
{
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4f8;border:1px solid #d6e2ea;border-radius:10px;">'
        . '<tr><td style="padding:12px 16px;font-size:14px;color:#2f5064;">Lead reference &nbsp;'
        . '<strong style="font-size:16px;color:#0E1B2A;letter-spacing:.5px;">' . e($reference) . '</strong></td></tr></table>';
}

/* ==========================================================================
 * PART B — lead-forward email builder (html + plain-text AltBody).
 * ======================================================================== */

/**
 * Build the branded lead-forward email. $lead expects display-ready values
 * (labels/dates already resolved by the caller): reference, venue, name, email,
 * phone, company, event_type, event_date, date_flexibility, guests, budget,
 * location, indoor_outdoor, venue_preference, fb_requirements, av_requirements,
 * notes. Every field is optional — empty rows AND empty sections are omitted.
 * @return array{html:string, text:string}
 */
function email_lead_forward(array $lead): array
{
    $g   = static fn(string $k): string => trim((string)($lead[$k] ?? ''));
    $ref = $g('reference');
    $venue = $g('venue');
    $clientEmail = $g('email');
    $phone = $g('phone');

    $c = '';

    // Intro
    $c .= '<tr><td style="padding:26px 28px 6px;">'
        . '<h1 style="font-family:Georgia,\'Times New Roman\',serif;font-size:24px;font-weight:normal;color:#0E1B2A;margin:0 0 10px;">A new event enquiry</h1>'
        . '<p style="font-size:15px;line-height:1.6;color:#40525f;margin:0;">A new enquiry has been submitted through All The Venues'
        . ($venue !== '' ? ' for <strong style="color:#1c2b38;">' . e($venue) . '</strong>' : '')
        . '. The client&rsquo;s details are below — please contact them directly and quote the lead reference when you follow up.</p></td></tr>';

    // Lead reference box
    if ($ref !== '') {
        $c .= '<tr><td style="padding:16px 28px 4px;">' . email_ref_box($ref) . '</td></tr>';
    }

    // Event details
    $dateVal = '';
    if ($g('event_date') !== '') {
        $dateVal = e($g('event_date'))
            . ($g('date_flexibility') !== '' ? ' <span style="color:#6b7b88;">&middot; ' . e($g('date_flexibility')) . '</span>' : '');
    }
    $eventRows = email_rows([
        ['Event type', $g('event_type') !== '' ? '<strong>' . e($g('event_type')) . '</strong>' : ''],
        ['Date', $dateVal],
        ['Guests', e($g('guests'))],
        ['Budget', e($g('budget'))],
        ['Location', e($g('location'))],
        ['Indoor / outdoor', e($g('indoor_outdoor'))],
    ]);
    if ($eventRows !== '') {
        $c .= '<tr><td style="padding:20px 28px 0;">' . email_section('Event details', $eventRows) . '</td></tr>';
    }

    // Client details
    $clientRows = email_rows([
        ['Name', $g('name') !== '' ? '<strong>' . e($g('name')) . '</strong>' : ''],
        ['Company', e($g('company'))],
        ['Email', $clientEmail !== '' ? '<a href="mailto:' . e($clientEmail) . '" style="color:#426F94;">' . e($clientEmail) . '</a>' : ''],
        ['Phone', $phone !== '' ? '<a href="tel:' . e($phone) . '" style="color:#426F94;">' . e($phone) . '</a>' : ''],
    ]);
    if ($clientRows !== '') {
        $c .= '<tr><td style="padding:18px 28px 0;">' . email_section('Client details', $clientRows) . '</td></tr>';
    }

    // Requirements (prose)
    $reqProse = '';
    foreach ([['Venue preference', 'venue_preference'], ['Food & beverage', 'fb_requirements'], ['AV & technical', 'av_requirements']] as [$lbl, $key]) {
        $val = $g($key);
        if ($val !== '') {
            $reqProse .= '<p style="font-size:14px;line-height:1.6;color:#1c2b38;margin:6px 0;"><strong>' . e($lbl) . ':</strong> ' . nl2br(e($val)) . '</p>';
        }
    }
    if ($reqProse !== '') {
        $c .= '<tr><td style="padding:18px 28px 0;">' . email_section('Requirements', $reqProse) . '</td></tr>';
    }

    // Additional notes (prose)
    if ($g('notes') !== '') {
        $notesHtml = '<p style="font-size:14px;line-height:1.6;color:#1c2b38;margin:6px 0;">' . nl2br(e($g('notes'))) . '</p>';
        $c .= '<tr><td style="padding:18px 28px 0;">' . email_section('Additional notes', $notesHtml) . '</td></tr>';
    }

    // CTAs — Email the Client (always, if an email exists) + Call the Client (only with a phone)
    $ctaCells = '';
    if ($clientEmail !== '') {
        $subject = 'Your All The Venues enquiry — ' . $ref;
        $ctaCells .= email_button('Email the Client &rarr;', 'mailto:' . $clientEmail . '?subject=' . rawurlencode($subject), true);
    }
    if ($phone !== '') {
        if ($ctaCells !== '') { $ctaCells .= '<td style="width:10px;"></td>'; }
        $ctaCells .= email_button('Call the Client', 'tel:' . $phone, false);
    }
    if ($ctaCells !== '') {
        $c .= '<tr><td style="padding:22px 28px 6px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>' . $ctaCells . '</tr></table>'
            . ($ref !== '' ? '<p style="font-size:12.5px;color:#6b7b88;margin:12px 0 0;">Please include the lead reference <strong>' . e($ref) . '</strong> when you follow up.</p>' : '')
            . '</td></tr>';
    }

    // Divider + Partner Portal promo card
    $c .= '<tr><td style="padding:20px 28px 0;"><div style="border-top:1px solid #e4e8ec;"></div></td></tr>';
    $c .= '<tr><td style="padding:18px 28px 26px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f8fb;border:1px solid #d6e2ea;border-radius:12px;"><tr><td style="padding:18px 20px;">'
        . '<div style="font-family:Georgia,\'Times New Roman\',serif;font-size:18px;color:#0E1B2A;margin-bottom:6px;">Manage your venue on All The Venues</div>'
        . '<p style="font-size:13.5px;line-height:1.6;color:#40525f;margin:0 0 14px;">Venue partners can now use the <strong>Partner Portal</strong> to manage their listings, upload approved photos, submit updates and event types, claim venues, and request delisting when a venue should no longer appear publicly — all reviewed by our team to keep the directory trusted.</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr>'
        . '<td style="border-radius:8px;background:#0E1B2A;"><a href="' . e(base_url('contact')) . '" style="display:inline-block;padding:11px 20px;font-size:13.5px;font-weight:bold;color:#ffffff;text-decoration:none;">Request Portal Access</a></td>'
        . '<td style="width:10px;"></td>'
        . '<td><a href="' . e(base_url('portal/login')) . '" style="display:inline-block;padding:11px 4px;font-size:13.5px;font-weight:bold;color:#426F94;text-decoration:none;">Already a partner? Sign in &rarr;</a></td>'
        . '</tr></table></td></tr></table></td></tr>';

    $preheader = 'A new enquiry' . ($venue !== '' ? ' for ' . $venue : '') . ($ref !== '' ? ' — ' . $ref : '');
    $html = email_layout('New enquiry', $c, $preheader);

    return ['html' => $html, 'text' => _email_lead_forward_text($lead)];
}

/** Plain-text AltBody for the lead-forward email — same facts, no markup, empties omitted. */
function _email_lead_forward_text(array $lead): string
{
    $g = static fn(string $k): string => trim((string)($lead[$k] ?? ''));
    $L = [];
    $L[] = 'A new event enquiry via All The Venues';
    if ($g('venue') !== '')     { $L[] = 'Venue: ' . $g('venue'); }
    if ($g('reference') !== '') { $L[] = 'Lead reference: ' . $g('reference'); }
    $L[] = '';

    $section = static function (string $title, array $items) use (&$L): void {
        $items = array_filter($items, static fn($x) => trim((string)$x) !== '');
        if (!$items) { return; }
        $L[] = strtoupper($title);
        foreach ($items as $it) { $L[] = '  - ' . $it; }
        $L[] = '';
    };

    $dateLine = $g('event_date') !== ''
        ? $g('event_date') . ($g('date_flexibility') !== '' ? ' (' . $g('date_flexibility') . ')' : '')
        : '';
    $section('Event details', [
        $g('event_type') !== ''     ? 'Event type: ' . $g('event_type') : '',
        $dateLine !== ''            ? 'Date: ' . $dateLine : '',
        $g('guests') !== ''         ? 'Guests: ' . $g('guests') : '',
        $g('budget') !== ''         ? 'Budget: ' . $g('budget') : '',
        $g('location') !== ''       ? 'Location: ' . $g('location') : '',
        $g('indoor_outdoor') !== '' ? 'Indoor / outdoor: ' . $g('indoor_outdoor') : '',
    ]);
    $section('Client details', [
        $g('name') !== ''    ? 'Name: ' . $g('name') : '',
        $g('company') !== '' ? 'Company: ' . $g('company') : '',
        $g('email') !== ''   ? 'Email: ' . $g('email') : '',
        $g('phone') !== ''   ? 'Phone: ' . $g('phone') : '',
    ]);
    $section('Requirements', [
        $g('venue_preference') !== '' ? 'Venue preference: ' . $g('venue_preference') : '',
        $g('fb_requirements') !== ''  ? 'Food & beverage: ' . $g('fb_requirements') : '',
        $g('av_requirements') !== ''  ? 'AV & technical: ' . $g('av_requirements') : '',
    ]);
    if ($g('notes') !== '') {
        $L[] = 'ADDITIONAL NOTES';
        $L[] = '  ' . $g('notes');
        $L[] = '';
    }
    if ($g('reference') !== '') {
        $L[] = 'Please include the lead reference ' . $g('reference') . ' when you follow up.';
    }
    if ($g('email') !== '') { $L[] = 'Email the client: ' . $g('email'); }
    if ($g('phone') !== '') { $L[] = 'Call the client: ' . $g('phone'); }
    $L[] = '';
    $L[] = 'Manage your venue: ' . base_url('contact') . '   ·   Sign in: ' . base_url('portal/login');
    $L[] = '— All The Venues';

    return implode("\n", $L);
}

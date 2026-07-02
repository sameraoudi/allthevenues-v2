<?php
declare(strict_types=1);

/**
 * Enquiry domain logic: validation, persistence, email bodies.
 * All DB access via prepared statements. Free text is stored as plain text
 * (tags stripped) — enquiries are internal admin-facing, never rendered as
 * HTML to other visitors.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/venues.php';

/** date_flexibility options (value => label). */
function enquiry_date_flexibility(): array
{
    return [
        'exact'    => 'Exact date',
        'flexible' => 'Flexible (± a few weeks)',
        'month'    => 'A particular month',
        'not_sure' => 'Not sure yet',
    ];
}

/** Trim, strip tags, collapse control chars, cap length. */
function clean_text(?string $s, int $max): string
{
    $s = strip_tags((string)$s);
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', '', $s) ?? '';
    $s = trim($s);
    if (mb_strlen($s) > $max) {
        $s = mb_substr($s, 0, $max);
    }
    return $s;
}

/** Generate a unique human-facing reference (ATV-YYMMDD-XXXX). */
function enquiry_generate_reference(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM enquiries WHERE reference = :r LIMIT 1');
    for ($i = 0; $i < 8; $i++) {
        $ref = 'ATV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $check->execute([':r' => $ref]);
        if ($check->fetchColumn() === false) {
            return $ref;
        }
    }
    // Extremely unlikely; add more entropy.
    return 'ATV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Resolve the venue context for a request (from GET on show, or POST hidden
 * ids on submit). Returns published venues only.
 *
 * @return array{mode:string, venue_ids:int[], venues:array}
 */
function enquiry_context(PDO $pdo, array $req): array
{
    $mode = 'general';
    $ids  = [];

    if (isset($req['venue'])) {
        $mode = 'single';
        $ids[] = (int)$req['venue'];
    } elseif (isset($req['venues']) && $req['venues'] !== '') {
        $mode = 'multi';
        foreach (explode(',', (string)$req['venues']) as $part) {
            $ids[] = (int)trim($part);
        }
    } elseif (isset($req['venue_ids']) && is_array($req['venue_ids'])) {
        // POST hidden field
        foreach ($req['venue_ids'] as $part) {
            $ids[] = (int)$part;
        }
        $mode = count($ids) > 1 ? 'multi' : ($ids ? 'single' : 'general');
    }

    $m = trim((string)($req['mode'] ?? ''));
    if ($m === 'assisted' || $m === 'partner') {
        $mode = $m;
        $ids  = [];   // no specific venue
    }

    // Keep only distinct, published venue ids (validated).
    $ids = array_values(array_unique(array_filter($ids, static fn($i) => $i > 0)));
    $venues = [];
    if ($ids) {
        $place = implode(',', array_fill(0, count($ids), '?'));
        $stmt  = $pdo->prepare(
            "SELECT id, name, slug FROM venues WHERE status='published' AND id IN ($place)"
        );
        $stmt->execute($ids);
        $venues = $stmt->fetchAll();
    }
    $validIds = array_map(static fn($v) => (int)$v['id'], $venues);

    return ['mode' => $mode, 'venue_ids' => $validIds, 'venues' => $venues];
}

/**
 * Validate + normalise submitted enquiry input.
 * @return array{errors: array<string,string>, clean: array}
 */
function enquiry_validate(PDO $pdo, array $in): array
{
    $errors = [];
    $clean  = [];

    // --- event_type (required) ---
    $etId = (int)($in['event_type'] ?? 0);
    $etOk = false;
    if ($etId > 0) {
        $s = $pdo->prepare('SELECT 1 FROM event_types WHERE id = :id AND active = 1');
        $s->execute([':id' => $etId]);
        $etOk = $s->fetchColumn() !== false;
    }
    if (!$etOk) {
        $errors['event_type'] = 'Please choose the type of event.';
        $clean['event_type_id'] = null;
    } else {
        $clean['event_type_id'] = $etId;
    }

    // --- name / email / phone / consent (required) ---
    $name = clean_text($in['name'] ?? '', 255);
    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter your name.';
    }
    $clean['name'] = $name;

    $email = trim((string)($in['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $clean['email'] = mb_substr($email, 0, 255);

    $phone = clean_text($in['phone'] ?? '', 50);
    if (mb_strlen($phone) < 5 || !preg_match('/^[0-9+()\-\s]{5,50}$/', $phone)) {
        $errors['phone'] = 'Please enter a valid phone number.';
    }
    $clean['phone'] = $phone;

    $clean['company'] = clean_text($in['company'] ?? '', 255);

    $consent = ($in['consent_to_share'] ?? '') === '1' || ($in['consent_to_share'] ?? '') === 'on';
    if (!$consent) {
        $errors['consent_to_share'] = 'Please agree to share your details with relevant venue partners.';
    }
    $clean['consent_to_share'] = $consent ? 1 : 0;

    // --- event_date (optional) ---
    $clean['event_date'] = null;
    $rawDate = trim((string)($in['event_date'] ?? ''));
    if ($rawDate !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $rawDate);
        $valid = $d && $d->format('Y-m-d') === $rawDate;
        if (!$valid) {
            $errors['event_date'] = 'Please enter a valid date.';
        } elseif ($rawDate < date('Y-m-d')) {
            $errors['event_date'] = 'Please choose a date in the future.';
        } else {
            $clean['event_date'] = $rawDate;
        }
    }

    // --- date_flexibility (optional, whitelisted) ---
    $flex = trim((string)($in['date_flexibility'] ?? ''));
    $clean['date_flexibility'] = isset(enquiry_date_flexibility()[$flex]) ? $flex : null;

    // --- emirate (optional) ---
    $clean['emirate_id'] = null;
    $emId = (int)($in['emirate'] ?? 0);
    if ($emId > 0) {
        $s = $pdo->prepare('SELECT 1 FROM emirates WHERE id = :id AND active = 1');
        $s->execute([':id' => $emId]);
        if ($s->fetchColumn() !== false) {
            $clean['emirate_id'] = $emId;
        }
    }

    // --- guest_count (optional, band key) ---
    $g = trim((string)($in['guest_count'] ?? ''));
    $clean['guest_count'] = isset(venue_guest_bands()[$g]) ? $g : null;

    // --- budget_range (optional, pricing level) ---
    $b = trim((string)($in['budget_range'] ?? ''));
    $clean['budget_range'] = in_array($b, venue_pricing_levels(), true) ? $b : null;

    // --- indoor_outdoor (optional, enum) ---
    $io = trim((string)($in['indoor_outdoor'] ?? ''));
    $clean['indoor_outdoor'] = isset(venue_indoor_outdoor_options()[$io]) ? $io : null;

    // --- free text (optional) ---
    $clean['venue_preference'] = clean_text($in['venue_preference'] ?? '', 255);
    $clean['fb_requirements']  = clean_text($in['fb_requirements'] ?? '', 2000);
    $clean['av_requirements']  = clean_text($in['av_requirements'] ?? '', 2000);
    $clean['notes']            = clean_text($in['notes'] ?? '', 4000);

    return ['errors' => $errors, 'clean' => $clean];
}

/**
 * Persist an enquiry + its venue links in a transaction.
 * @return array{id:int, reference:string}
 */
function enquiry_insert(PDO $pdo, array $clean, array $venueIds, string $sourcePage): array
{
    $pdo->beginTransaction();
    try {
        $reference = enquiry_generate_reference($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO enquiries
                (reference, name, email, phone, company, event_type_id, event_date,
                 date_flexibility, emirate_id, guest_count, budget_range,
                 venue_preference, indoor_outdoor, fb_requirements, av_requirements,
                 notes, consent_to_share, source_page, status)
             VALUES
                (:reference, :name, :email, :phone, :company, :event_type_id, :event_date,
                 :date_flexibility, :emirate_id, :guest_count, :budget_range,
                 :venue_preference, :indoor_outdoor, :fb_requirements, :av_requirements,
                 :notes, :consent_to_share, :source_page, :status)'
        );
        $stmt->execute([
            ':reference'        => $reference,
            ':name'             => $clean['name'] ?: null,
            ':email'            => $clean['email'] ?: null,
            ':phone'            => $clean['phone'] ?: null,
            ':company'          => $clean['company'] ?: null,
            ':event_type_id'    => $clean['event_type_id'],
            ':event_date'       => $clean['event_date'],
            ':date_flexibility' => $clean['date_flexibility'],
            ':emirate_id'       => $clean['emirate_id'],
            ':guest_count'      => $clean['guest_count'],
            ':budget_range'     => $clean['budget_range'],
            ':venue_preference' => $clean['venue_preference'] ?: null,
            ':indoor_outdoor'   => $clean['indoor_outdoor'],
            ':fb_requirements'  => $clean['fb_requirements'] ?: null,
            ':av_requirements'  => $clean['av_requirements'] ?: null,
            ':notes'            => $clean['notes'] ?: null,
            ':consent_to_share' => $clean['consent_to_share'],
            ':source_page'      => mb_substr($sourcePage, 0, 255),
            ':status'           => 'new',
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($venueIds) {
            $link = $pdo->prepare(
                'INSERT INTO enquiry_venues (enquiry_id, venue_id) VALUES (:e, :v)'
            );
            foreach (array_unique($venueIds) as $vid) {
                $link->execute([':e' => $id, ':v' => (int)$vid]);
            }
        }

        $pdo->commit();
        return ['id' => $id, 'reference' => $reference];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Human-readable summary rows for emails (label => value), from clean input.
 */
function enquiry_summary_rows(PDO $pdo, array $clean, array $venues): array
{
    $rows = [];

    if ($clean['event_type_id']) {
        $s = $pdo->prepare('SELECT name FROM event_types WHERE id = :id');
        $s->execute([':id' => $clean['event_type_id']]);
        $rows['Event type'] = (string)$s->fetchColumn();
    }
    if ($clean['event_date']) {
        $rows['Event date'] = date('j M Y', strtotime($clean['event_date']));
    }
    if ($clean['date_flexibility']) {
        $rows['Date flexibility'] = enquiry_date_flexibility()[$clean['date_flexibility']] ?? $clean['date_flexibility'];
    }
    if ($clean['emirate_id']) {
        $s = $pdo->prepare('SELECT name FROM emirates WHERE id = :id');
        $s->execute([':id' => $clean['emirate_id']]);
        $rows['Location'] = (string)$s->fetchColumn();
    }
    if ($clean['guest_count']) {
        $rows['Guest count'] = venue_guest_bands()[$clean['guest_count']][0] ?? $clean['guest_count'];
    }
    if ($clean['budget_range']) {
        $rows['Budget'] = $clean['budget_range'];
    }
    if ($clean['indoor_outdoor']) {
        $rows['Setting'] = venue_indoor_outdoor_options()[$clean['indoor_outdoor']] ?? $clean['indoor_outdoor'];
    }
    if ($clean['venue_preference']) {
        $rows['Venue preference'] = $clean['venue_preference'];
    }
    if ($clean['fb_requirements']) {
        $rows['Food & beverage'] = $clean['fb_requirements'];
    }
    if ($clean['av_requirements']) {
        $rows['AV & technical'] = $clean['av_requirements'];
    }
    if ($clean['notes']) {
        $rows['Notes'] = $clean['notes'];
    }
    if ($venues) {
        $rows['Venue(s)'] = implode(', ', array_map(static fn($v) => (string)$v['name'], $venues));
    }
    return $rows;
}

/** Build the user confirmation + admin notification email bodies (HTML). */
function enquiry_emails(string $reference, array $clean, array $summaryRows): array
{
    $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $rowsHtml = '';
    foreach ($summaryRows as $label => $value) {
        $rowsHtml .= '<tr>'
            . '<td style="padding:6px 12px 6px 0;color:#5b6b7a;vertical-align:top;">' . $esc($label) . '</td>'
            . '<td style="padding:6px 0;color:#0E1B2A;">' . nl2br($esc($value)) . '</td>'
            . '</tr>';
    }

    $userHtml = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:auto;color:#0E1B2A;">'
        . '<h2 style="font-family:Georgia,serif;color:#0E1B2A;">Thank you for your enquiry</h2>'
        . '<p>Hi ' . $esc($clean['name']) . ',</p>'
        . '<p>We\'ve received your enquiry and our team will be in touch shortly. '
        . 'Your reference is <strong>' . $esc($reference) . '</strong>.</p>'
        . '<h3 style="font-family:Georgia,serif;">Your enquiry</h3>'
        . '<table style="border-collapse:collapse;font-size:14px;">' . $rowsHtml . '</table>'
        . '<p style="color:#5b6b7a;font-size:13px;margin-top:20px;">Secure and confidential. '
        . 'Your details are only shared with relevant venue partners.</p>'
        . '<p style="color:#5b6b7a;font-size:13px;">— All The Venues</p>'
        . '</div>';

    $adminHtml = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;color:#0E1B2A;">'
        . '<h2>New enquiry — ' . $esc($reference) . '</h2>'
        . '<table style="border-collapse:collapse;font-size:14px;">'
        . '<tr><td style="padding:6px 12px 6px 0;color:#5b6b7a;">Name</td><td style="padding:6px 0;">' . $esc($clean['name']) . '</td></tr>'
        . '<tr><td style="padding:6px 12px 6px 0;color:#5b6b7a;">Email</td><td style="padding:6px 0;">' . $esc($clean['email']) . '</td></tr>'
        . '<tr><td style="padding:6px 12px 6px 0;color:#5b6b7a;">Phone</td><td style="padding:6px 0;">' . $esc($clean['phone']) . '</td></tr>'
        . ($clean['company'] ? '<tr><td style="padding:6px 12px 6px 0;color:#5b6b7a;">Company</td><td style="padding:6px 0;">' . $esc($clean['company']) . '</td></tr>' : '')
        . $rowsHtml
        . '</table></div>';

    return ['user' => $userHtml, 'admin' => $adminHtml];
}

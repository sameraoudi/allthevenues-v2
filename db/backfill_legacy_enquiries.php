<?php
declare(strict_types=1);

// CLI only — never over HTTP. (Also denied by db/.htaccess + excluded from
// deploy via .cpanel.yml; this is defence in depth.)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * U3c-2 — Backfill legacy `inquiry` rows into the new `enquiries` table as
 * HISTORICAL leads.
 *
 * Reads the legacy DB (sameraou_atv, localhost-only on prod), maps each legacy
 * inquiry to a new enquiry, preserves the original submission date, best-effort
 * links a venue (legacy name → migration slug → new venue), scores obvious spam
 * to status='spam' (real leads → 'closed'), and marks every imported row
 * is_historical=1 with legacy_id=<inquiry.id>.
 *
 * SAFE BY DEFAULT: dry-run unless --commit (or --run) is passed. Idempotent —
 * a row whose legacy_id already exists is skipped (uq_enquiries_legacy is the
 * DB backstop). Re-runnable.
 *
 *   php db/backfill_legacy_enquiries.php              # dry-run (no writes)
 *   php db/backfill_legacy_enquiries.php --commit     # writes
 *   php db/backfill_legacy_enquiries.php --self-test   # fixture logic-check, no DB
 *
 * PREREQUISITE: db/015_enquiry_historical.sql applied (is_historical + legacy_id
 * + uq_enquiries_legacy). Legacy creds via ATV_LEGACY_DB_* env (see _migrate_lib).
 * Target MySQL 5.7. Prepared statements throughout.
 */

require_once __DIR__ . '/_migrate_lib.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/venues.php';   // venue_guest_bands()
require_once __DIR__ . '/../lib/enquiry.php';

/* ===========================================================================
 * PURE MAPPING / SCORING HELPERS (no DB — unit-testable via --self-test)
 * ========================================================================= */

/** Legacy `event-type` string (normalised) → event_types.slug. */
function bf_event_slug_map(): array
{
    return [
        'wedding'          => 'wedding',
        'engagement'       => 'engagement',
        'private party'    => 'private-party',
        'birthday'         => 'birthday',
        'corporate event'  => 'corporate-event',
        'business event'   => 'corporate-event',
        'conference'       => 'conference',
        'meeting'          => 'meeting',
        'training'         => 'training',
        'product launch'   => 'product-launch',
        'gala dinner'      => 'gala-dinner',
        'exhibition'       => 'exhibition',
        'yacht event'      => 'yacht-event',
        'outdoor event'    => 'outdoor-event',
        'networking'       => 'networking-event',
        'networking event' => 'networking-event',
        'other'            => 'other',
    ];
}

/** Map a raw legacy event-type to a slug, or null when unmapped. */
function bf_map_event_slug(?string $raw): ?string
{
    $key = strtolower(trim(preg_replace('/\s+/', ' ', (string)$raw)));
    if ($key === '') { return null; }
    return bf_event_slug_map()[$key] ?? null;
}

/** Nearest guest-band key: highest band whose min <= guests. 0/invalid → null. */
function bf_guest_band(?int $guests): ?string
{
    if ($guests === null || $guests < 1) { return null; }
    $best = null; $bestMin = -1;
    foreach (venue_guest_bands() as $key => [$label, $min, $max]) {
        if ($guests >= $min && $min > $bestMin) { $best = $key; $bestMin = $min; }
    }
    return $best;
}

/** True when a datetime string is real (not empty / '0000-…' / unparseable). */
function bf_is_valid_dt(?string $s): bool
{
    $s = trim((string)$s);
    if ($s === '' || strncmp($s, '0000', 4) === 0) { return false; }
    $ts = strtotime($s);
    return $ts !== false && (int)date('Y', $ts) >= 1970;
}

/** Legacy event-date → 'Y-m-d' when valid and year >= 2000, else null. */
function bf_event_date(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '' || strncmp($s, '0000', 4) === 0) { return null; }
    $ts = strtotime($s);
    if ($ts === false || (int)date('Y', $ts) < 2000) { return null; }
    return date('Y-m-d', $ts);
}

/** Message contains a URL / bare domain? */
function bf_message_has_url(?string $msg): bool
{
    $m = (string)$msg;
    return (bool)preg_match('~(https?://|www\.)~i', $m)
        || (bool)preg_match('~\b[a-z0-9-]+\.(com|net|org|ru|info|biz|xyz|top|fun|click|online|site|shop|link|live|vip)\b~i', $m);
}

/** Auto-generated-looking username: all-lowercase alnum, contains a digit, no spaces. */
function bf_looks_autogen_username(?string $name): bool
{
    $n = trim((string)$name);
    return $n !== '' && (bool)preg_match('/^[a-z0-9]+$/', $n) && (bool)preg_match('/[0-9]/', $n);
}

/** Email domain is a suspicious TLD or a known-disposable host. */
function bf_suspicious_email(?string $email): bool
{
    $at = strrpos((string)$email, '@');
    if ($at === false) { return false; }
    $domain = strtolower(substr((string)$email, $at + 1));
    if ($domain === '') { return false; }
    foreach (['.fun', '.top', '.xyz', '.click'] as $tld) {
        if (str_ends_with($domain, $tld)) { return true; }
    }
    $disposable = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'trashmail.com',
                   'tempmail.com', 'yopmail.com', 'sharklasers.com', 'getnada.com', 'maildrop.cc'];
    return in_array($domain, $disposable, true);
}

/**
 * Spam score for a legacy inquiry. >= 2 → spam.
 * @param array{message:?string,email:?string,guests:int,eventYear:?int,inquiryYear:?int,username:?string} $r
 */
function bf_spam_score(array $r): int
{
    $score = 0;
    if (bf_message_has_url($r['message'] ?? '')) { $score += 2; }
    if (!filter_var(trim((string)($r['email'] ?? '')), FILTER_VALIDATE_EMAIL)) { $score += 2; }
    if (bf_suspicious_email($r['email'] ?? '')) { $score += 1; }
    if ((int)($r['guests'] ?? 0) > 5000) { $score += 1; }
    $ey = $r['eventYear'] ?? null; $iy = $r['inquiryYear'] ?? null;
    if ($ey !== null && $ey < 2005 && $iy !== null && $iy >= 2020) { $score += 1; }
    if (bf_looks_autogen_username($r['username'] ?? '')) { $score += 1; }
    return $score;
}

/** Assemble notes: message + traceability/context lines. */
function bf_build_notes(string $message, int $legacyId, int $guests, ?string $mappedSlug, string $rawType, bool $dateFellBack): string
{
    $out = trim($message);
    $out .= ($out === '' ? '' : "\n\n") . '[Imported from legacy #' . $legacyId . ']';
    if ($guests > 0)                             { $out .= "\nGuests (stated): " . $guests; }
    if ($mappedSlug === null && trim($rawType) !== '') { $out .= "\nEvent type (stated): " . trim($rawType); }
    if ($dateFellBack)                           { $out .= "\nOriginal submission date missing (defaulted)."; }
    return mb_substr($out, 0, 60000);
}

/* ===========================================================================
 * --self-test : run a small fixture through the pure logic (no DB).
 * ========================================================================= */

if (in_array('--self-test', $argv, true)) {
    echo "== SELF-TEST (pure logic, no DB) ==\n\n";
    $fixture = [
        // 1) real, mapped type, venue, sensible dates
        ['id'=>1001,'username'=>'Sara Malik','useremail'=>'sara@example.com','usermobile'=>'+971 50 111 2233',
         'venueid'=>42,'event-type'=>'Wedding','guests'=>'150','event-date'=>'2019-11-20',
         'message'=>'Looking for a ballroom for 150 guests.','inquiry_date'=>'2019-06-01 10:00:00'],
        // 2) unmapped type "Social Gathering", no venue
        ['id'=>1002,'username'=>'Ahmed N','useremail'=>'ahmed@example.com','usermobile'=>'0555',
         'venueid'=>0,'event-type'=>'Social Gathering','guests'=>'40','event-date'=>'2020-02-10',
         'message'=>'Small get-together.','inquiry_date'=>'2020-01-15 09:30:00'],
        // 3) spammy (url + autogen name + disposable), like legacy #14068
        ['id'=>14068,'username'=>'anya179mt','useremail'=>'anya179@mailinator.com','usermobile'=>'x',
         'venueid'=>0,'event-type'=>'Other','guests'=>'99999','event-date'=>'2001-01-01',
         'message'=>'Best SEO services http://spam.top buy now www.cheap.xyz','inquiry_date'=>'2022-03-03 03:03:03'],
        // 4) bogus zero-dates
        ['id'=>1004,'username'=>'Lina','useremail'=>'not-an-email','usermobile'=>'',
         'venueid'=>0,'event-type'=>'Corporate Event','guests'=>'0','event-date'=>'0000-00-00',
         'message'=>'Need a venue.','inquiry_date'=>'0000-00-00 00:00:00'],
    ];
    foreach ($fixture as $r) {
        $id      = (int)$r['id'];
        $guests  = (int)$r['guests'];
        $rawType = (string)$r['event-type'];
        $slug    = bf_map_event_slug($rawType);
        $band    = bf_guest_band($guests);
        $evDate  = bf_event_date((string)$r['event-date']);
        $dtOk    = bf_is_valid_dt((string)$r['inquiry_date']);
        $createdAt = $dtOk ? date('Y-m-d H:i:s', (int)strtotime((string)$r['inquiry_date'])) : '2015-01-01 00:00:00';
        $eventYear = ($evDate !== null) ? (int)substr($evDate, 0, 4) : null;
        $inqYear   = $dtOk ? (int)date('Y', (int)strtotime((string)$r['inquiry_date'])) : null;
        $score = bf_spam_score([
            'message'=>$r['message'],'email'=>$r['useremail'],'guests'=>$guests,
            'eventYear'=>$eventYear,'inquiryYear'=>$inqYear,'username'=>$r['username'],
        ]);
        $status = $score >= 2 ? 'spam' : 'closed';
        $mode   = ((int)$r['venueid'] > 0) ? 'venue (if slug resolves)' : 'general';
        $notes  = bf_build_notes((string)$r['message'], $id, $guests, $slug, $rawType, !$dtOk);
        echo "legacy #$id  reference=LEG-$id\n";
        echo "  name={$r['username']}  email={$r['useremail']}  phone={$r['usermobile']}\n";
        echo "  event_type: '$rawType' -> " . ($slug ?? 'NULL (raw kept in notes)') . "\n";
        echo "  guests: $guests -> band " . ($band ?? 'NULL') . "\n";
        echo "  event_date: '{$r['event-date']}' -> " . ($evDate ?? 'NULL') . "\n";
        echo "  created_at: '{$r['inquiry_date']}' -> $createdAt" . ($dtOk ? '' : '  (fallback)') . "\n";
        echo "  venueid={$r['venueid']} -> mode=$mode\n";
        echo "  spam_score=$score -> status=$status  (is_historical=1)\n";
        echo "  notes:\n    " . str_replace("\n", "\n    ", $notes) . "\n\n";
    }
    echo "Self-test complete. No DB touched.\n";
    exit(0);
}

/* ===========================================================================
 * MAIN — read legacy, import into new. Dry-run unless --commit / --run.
 * ========================================================================= */

$commit = in_array('--commit', $argv, true) || in_array('--run', $argv, true);
$dryRun = !$commit;

$t0 = microtime(true);
echo "== Backfill legacy enquiries (U3c-2) ==\n";
echo $dryRun ? "MODE: DRY-RUN (no writes; pass --commit to write)\n\n" : "MODE: COMMIT (writing)\n\n";

try {
    $src = ml_pdo_connect(ml_legacy_config());
    $dst = ml_pdo_connect(ml_new_config());
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

// Guard: target columns must exist (015 applied).
try {
    $dst->query('SELECT is_historical, legacy_id FROM enquiries LIMIT 0');
} catch (Throwable $e) {
    fwrite(STDERR, "enquiries.is_historical / legacy_id missing — apply db/015_enquiry_historical.sql first.\n");
    exit(1);
}

// event_types slug → id.
$slugToEventId = [];
foreach ($dst->query('SELECT id, slug FROM event_types') as $r) {
    $slugToEventId[(string)$r['slug']] = (int)$r['id'];
}

// legacy venue id → new venue id (replays the migration slug derivation).
$venueIdMap = ml_build_venue_id_map($src, $dst);
// new venue id → emirate_id (for context on linked rows).
$venueEmirate = [];
foreach ($dst->query('SELECT id, emirate_id FROM venues') as $r) {
    $venueEmirate[(int)$r['id']] = $r['emirate_id'] !== null ? (int)$r['emirate_id'] : null;
}
// legacy venue id → name (for logging).
$legacyVenueName = [];
foreach ($src->query('SELECT id, name FROM venues') as $r) {
    $legacyVenueName[(int)$r['id']] = (string)$r['name'];
}

// Already-imported legacy ids (idempotency; in-memory + unique-index backstop).
$existing = [];
foreach ($dst->query('SELECT legacy_id FROM enquiries WHERE legacy_id IS NOT NULL') as $r) {
    $existing[(int)$r['legacy_id']] = true;
}

$ins = $dst->prepare(
    'INSERT INTO enquiries
        (reference, name, email, phone, event_type_id, event_date, emirate_id,
         guest_count, budget_range, notes, consent_to_share, source_page, mode,
         status, is_historical, legacy_id, created_at)
     VALUES
        (:reference, :name, :email, :phone, :event_type_id, :event_date, :emirate_id,
         :guest_count, :budget_range, :notes, :consent, :source_page, :mode,
         :status, 1, :legacy_id, :created_at)'
);
$insVenue = $dst->prepare('INSERT INTO enquiry_venues (enquiry_id, venue_id) VALUES (:e, :v)');

$stats = [
    'legacy_total'    => 0,
    'imported'        => 0,
    'skipped_existing'=> 0,
    'flagged_spam'    => 0,
    'venue_linked'    => 0,
    'venue_unlinked'  => 0,
    'event_type_mapped'=> 0,
    'errors'          => 0,
];

$q = $src->query('SELECT id, registered, userid, venueid, username, useremail, usermobile,
                         `event-type` AS event_type, guests, `event-date` AS event_date,
                         message, status, inquiry_date
                  FROM inquiry ORDER BY id ASC');

foreach ($q as $row) {
    $stats['legacy_total']++;
    $id = (int)$row['id'];

    if (isset($existing[$id])) { $stats['skipped_existing']++; continue; }

    $name   = mb_substr(trim((string)$row['username']), 0, 255);
    $email  = mb_substr(trim((string)$row['useremail']), 0, 255);
    $phone  = mb_substr(trim((string)$row['usermobile']), 0, 50);
    $guests = (int)$row['guests'];
    $rawType= (string)$row['event_type'];

    $slug = bf_map_event_slug($rawType);
    $eventTypeId = ($slug !== null) ? ($slugToEventId[$slug] ?? null) : null;
    if ($eventTypeId !== null) { $stats['event_type_mapped']++; }

    $band = bf_guest_band($guests);

    $dtOk      = bf_is_valid_dt((string)$row['inquiry_date']);
    $createdAt = $dtOk ? date('Y-m-d H:i:s', (int)strtotime((string)$row['inquiry_date'])) : '2015-01-01 00:00:00';
    $eventDate = bf_event_date((string)$row['event_date']);

    $eventYear = ($eventDate !== null) ? (int)substr($eventDate, 0, 4) : null;
    $inqYear   = $dtOk ? (int)date('Y', (int)strtotime((string)$row['inquiry_date'])) : null;

    $score  = bf_spam_score([
        'message'=>$row['message'],'email'=>$email,'guests'=>$guests,
        'eventYear'=>$eventYear,'inquiryYear'=>$inqYear,'username'=>$name,
    ]);
    $status = $score >= 2 ? 'spam' : 'closed';
    if ($status === 'spam') { $stats['flagged_spam']++; }

    // Venue link (best-effort).
    $venueId   = (int)$row['venueid'];
    $newVenue  = ($venueId > 0 && isset($venueIdMap[$venueId])) ? $venueIdMap[$venueId] : null;
    $mode      = 'general';
    $emirateId = null;
    if ($newVenue !== null) {
        $mode      = 'venue';
        $emirateId = $venueEmirate[$newVenue] ?? null;
        $stats['venue_linked']++;
    } else {
        $stats['venue_unlinked']++;
    }

    $notes = bf_build_notes((string)$row['message'], $id, $guests, $slug, $rawType, !$dtOk);

    if ($dryRun) {
        $stats['imported']++;   // "would import"
        if ($stats['imported'] <= 8) {
            $vn = $newVenue !== null ? ($legacyVenueName[$venueId] ?? '?') . " -> venue#$newVenue" : '—';
            echo sprintf("WOULD import LEG-%d  %s <%s>  type=%s  band=%s  date=%s  mode=%s  status=%s  venue=%s\n",
                $id, $name ?: '(no name)', $email ?: '(no email)',
                $slug ?? 'null', $band ?? 'null', $eventDate ?? 'null', $mode, $status, $vn);
        }
        continue;
    }

    // COMMIT: one transaction per row.
    try {
        $dst->beginTransaction();
        $ins->execute([
            ':reference'     => 'LEG-' . $id,
            ':name'          => $name !== '' ? $name : null,
            ':email'         => $email !== '' ? $email : null,
            ':phone'         => $phone !== '' ? $phone : null,
            ':event_type_id' => $eventTypeId,
            ':event_date'    => $eventDate,
            ':emirate_id'    => $emirateId,
            ':guest_count'   => $band,
            ':budget_range'  => null,
            ':notes'         => $notes !== '' ? $notes : null,
            ':consent'       => 0,
            ':source_page'   => 'legacy:inquiry',
            ':mode'          => $mode,
            ':status'        => $status,
            ':legacy_id'     => $id,
            ':created_at'    => $createdAt,
        ]);
        $newId = (int)$dst->lastInsertId();
        if ($newVenue !== null) {
            $insVenue->execute([':e' => $newId, ':v' => $newVenue]);
        }
        $dst->commit();
        $existing[$id] = true;
        $stats['imported']++;
    } catch (Throwable $e) {
        if ($dst->inTransaction()) { $dst->rollBack(); }
        $stats['errors']++;
        error_log('backfill legacy inquiry #' . $id . ' failed: ' . $e->getMessage());
        fwrite(STDERR, "ERROR importing legacy #$id: {$e->getMessage()}\n");
    }
}

/* ---- Summary ------------------------------------------------------------- */
$verb = $dryRun ? 'would import' : 'imported';
echo "\n== Summary ==\n";
echo "  legacy rows total : {$stats['legacy_total']}\n";
echo "  $verb              : {$stats['imported']}\n";
echo "  skipped (existing) : {$stats['skipped_existing']}\n";
echo "  flagged spam       : {$stats['flagged_spam']}\n";
echo "  venue-linked       : {$stats['venue_linked']}\n";
echo "  venue-unlinked     : {$stats['venue_unlinked']}\n";
echo "  event_type mapped  : {$stats['event_type_mapped']}\n";
echo "  errors             : {$stats['errors']}\n";
printf("  elapsed            : %.2fs\n", microtime(true) - $t0);
if ($dryRun) {
    echo "\nDRY-RUN — nothing was written. Re-run with --commit to import.\n";
}

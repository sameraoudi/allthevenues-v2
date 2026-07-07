<?php
declare(strict_types=1);

/**
 * #3 U-P5b — Admin review of provider EDIT change requests. List/get/count +
 * the three decisions (approve & apply / request-changes / reject). Only
 * type='edit' requests are actionable here; new_venue/image/claim land in
 * U-P6/7/8. Every write is prepared, audited, and (for a decision) emails the
 * submitting provider. Approve is all-or-nothing inside a transaction and
 * re-validates every field at approval time (a slug can go stale between submit
 * and review). An approved slug change captures a #10 301 redirect.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/venues.php';          // venue_types_all(), venue_emirates(), venue_slug_available()?(admin)
require_once __DIR__ . '/venue_admin.php';      // venue_slug_available(), venue_admin_get()
require_once __DIR__ . '/venue_images_admin.php'; // venue_images_count() (#3 U-P6b completeness)
require_once __DIR__ . '/slug_redirect.php';    // slug_redirect_capture() (#10)
require_once __DIR__ . '/audit.php';            // audit_log()
require_once __DIR__ . '/mail.php';             // send_mail()

/**
 * Per requestable field: display label, descriptive badges, risk weight, and
 * (for FK fields) a resolver id → human label. This is the single source of
 * truth the queue + detail + validators all read from.
 */
function cr_field_meta(): array
{
    return [
        'name' => [
            'label'  => 'Name',
            'badges' => ['Identity', 'Restricted'],
            'risk'   => 'high',
        ],
        'slug' => [
            'label'  => 'Slug (URL)',
            'badges' => ['Identity', 'SEO-impacting'],
            'risk'   => 'high',
            'help'   => 'Changing the slug updates the public URL. The old URL redirects automatically (301), '
                      . 'but approve only when the venue name or identity has genuinely changed.',
        ],
        'venue_type_id' => [
            'label'  => 'Classification',
            'badges' => ['Classification'],
            'risk'   => 'medium',
            'fk'     => 'venue_type',
        ],
        'emirate_id' => [
            'label'  => 'Primary emirate',
            'badges' => ['Identity'],
            'risk'   => 'high',
            'fk'     => 'emirate',
        ],
    ];
}

/** Highest risk among the changed fields: 'high' > 'medium' > 'low'. */
function cr_request_risk(array $changedFieldKeys): string
{
    $meta = cr_field_meta();
    $risk = 'low';
    foreach ($changedFieldKeys as $k) {
        $r = $meta[$k]['risk'] ?? 'low';
        if ($r === 'high') { return 'high'; }
        if ($r === 'medium') { $risk = 'medium'; }
    }
    return $risk;
}

/** Resolve venue_type / emirate ids to names once, for display. */
function _cr_fk_maps(PDO $pdo): array
{
    $types = $emirates = [];
    foreach (venue_types_all($pdo) as $t) { $types[(int)$t['id']] = (string)$t['name']; }
    foreach (venue_emirates($pdo) as $e)  { $emirates[(int)$e['id']] = (string)$e['name']; }
    return ['venue_type' => $types, 'emirate' => $emirates];
}

/** Human display for a field value (resolves FK ids; formats slug/empty). */
function cr_display_value(string $field, $value, array $fkMaps): string
{
    if ($value === null || $value === '') { return '—'; }
    $meta = cr_field_meta()[$field] ?? [];
    if (($meta['fk'] ?? '') === 'venue_type') { return $fkMaps['venue_type'][(int)$value] ?? ('#' . (int)$value); }
    if (($meta['fk'] ?? '') === 'emirate')    { return $fkMaps['emirate'][(int)$value] ?? ('#' . (int)$value); }
    if ($field === 'slug') { return '/venues/' . (string)$value; }
    return (string)$value;
}

/** Display label + lead-status CSS modifier for a request status. */
function cr_status_meta(string $status): array
{
    return [
        'pending'       => ['Pending',          'pending'],
        'approved'      => ['Approved',         'approved'],
        'rejected'      => ['Rejected',         'rejected'],
        'needs_changes' => ['Changes requested', 'needs_changes'],
        'withdrawn'     => ['Withdrawn',        'archived'],
    ][$status] ?? [ucfirst($status), 'pending'];
}

/** Human label for a request type. */
function cr_type_label(string $type): string
{
    return [
        'edit'      => 'Edit',
        'new_venue' => 'New venue',
        'image'     => 'Image',
        'claim'     => 'Ownership claim',
    ][$type] ?? ucfirst($type);
}

/** Display label for a risk level. */
function cr_risk_label(string $risk): string
{
    return ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'][$risk] ?? ucfirst($risk);
}

/** Pending-request count for the admin nav badge. */
function cr_admin_pending_count(PDO $pdo): int
{
    try {
        return (int)$pdo->query("SELECT COUNT(*) FROM venue_change_requests WHERE status='pending'")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/** Decode proposed_changes_json defensively → ['field'=>['old'=>..,'new'=>..], …]. */
function cr_decode_changes($json): array
{
    $d = json_decode((string)$json, true);
    return is_array($d) ? $d : [];
}

/**
 * Review queue. $filters: ['status'=>?, 'type'=>?]. Defaults to status='pending'.
 * Joins venue name + provider org_name; annotates each row with change count +
 * risk (computed from the requested fields).
 */
function cr_admin_list(PDO $pdo, array $filters): array
{
    $where  = [];
    $params = [];

    $status = trim((string)($filters['status'] ?? 'pending'));
    if ($status !== '' && $status !== 'all') { $where[] = 'cr.status = :status'; $params[':status'] = $status; }

    $type = trim((string)($filters['type'] ?? ''));
    if ($type !== '') { $where[] = 'cr.type = :type'; $params[':type'] = $type; }

    $sql = "SELECT cr.id, cr.venue_id, cr.partner_id, cr.submitted_by, cr.type,
                   cr.proposed_changes_json, cr.status, cr.created_at, cr.reviewed_at,
                   v.name AS venue_name, p.org_name AS provider_name
            FROM venue_change_requests cr
            LEFT JOIN venues   v ON v.id = cr.venue_id
            LEFT JOIN partners p ON p.id = cr.partner_id";
    if ($where) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY cr.created_at DESC, cr.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $changes        = cr_decode_changes($r['proposed_changes_json']);
        $r['changes']   = $changes;
        $r['change_count'] = count($changes);
        // new_venue / claim have no changed-field list — inherently high-risk.
        $r['risk']      = ($r['type'] === 'edit') ? cr_request_risk(array_keys($changes))
                        : (in_array($r['type'], ['new_venue', 'claim'], true) ? 'high' : 'low');
    }
    unset($r);
    return $rows;
}

/**
 * One request with full context for the detail screen: venue + provider +
 * submitting user email, decoded changes each annotated with meta + resolved
 * old/new display values. Null if not found.
 */
function cr_admin_get(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT cr.*, v.name AS venue_name, v.slug AS venue_slug, v.partner_id AS venue_partner_id,
                p.org_name AS provider_name, p.email AS provider_email,
                u.email AS submitter_email, u.name AS submitter_name
         FROM venue_change_requests cr
         LEFT JOIN venues   v ON v.id = cr.venue_id
         LEFT JOIN partners p ON p.id = cr.partner_id
         LEFT JOIN users    u ON u.id = cr.submitted_by
         WHERE cr.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $id]);
    $req = $stmt->fetch();
    if ($req === false) { return null; }

    $fkMaps  = _cr_fk_maps($pdo);
    $meta    = cr_field_meta();
    $changes = cr_decode_changes($req['proposed_changes_json']);

    $rows = [];
    foreach ($changes as $field => $pair) {
        if (!isset($meta[$field])) { continue; }   // ignore anything not a known requestable field
        $rows[] = [
            'field'   => $field,
            'meta'    => $meta[$field],
            'old'     => $pair['old'] ?? null,
            'new'     => $pair['new'] ?? null,
            'old_disp' => cr_display_value($field, $pair['old'] ?? null, $fkMaps),
            'new_disp' => cr_display_value($field, $pair['new'] ?? null, $fkMaps),
        ];
    }
    $req['changes']      = $changes;
    $req['change_rows']  = $rows;
    $req['risk']         = ($req['type'] === 'edit') ? cr_request_risk(array_keys($changes))
                         : (in_array($req['type'], ['new_venue', 'claim'], true) ? 'high' : 'low');
    $req['recipient']    = trim((string)($req['submitter_email'] ?? '')) !== ''
        ? (string)$req['submitter_email'] : (string)($req['provider_email'] ?? '');
    return $req;
}

/**
 * Re-validate every proposed edit field at decision time. Returns [] if all
 * valid, else ['field'=>'message', …]. Mirrors the admin venue edit rules.
 */
function _cr_validate_edit(PDO $pdo, array $changes, int $venueId): array
{
    $errors = [];
    foreach ($changes as $field => $pair) {
        $new = $pair['new'] ?? null;
        switch ($field) {
            case 'name':
                if (trim((string)$new) === '') { $errors['name'] = 'The proposed name is empty.'; }
                break;
            case 'slug':
                $slug = strtolower(trim((string)$new));
                if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 191) {
                    $errors['slug'] = 'The proposed slug is not a valid format.';
                } elseif (!venue_slug_available($pdo, $slug, $venueId)) {
                    $errors['slug'] = 'The proposed slug is now in use by another venue.';
                }
                break;
            case 'venue_type_id':
                if ($new !== null && $new !== '') {
                    $s = $pdo->prepare('SELECT 1 FROM venue_types WHERE id = :id');
                    $s->execute([':id' => (int)$new]);
                    if ($s->fetchColumn() === false) { $errors['venue_type_id'] = 'The proposed classification no longer exists.'; }
                }
                break;
            case 'emirate_id':
                if ($new !== null && $new !== '') {
                    $s = $pdo->prepare('SELECT 1 FROM emirates WHERE id = :id');
                    $s->execute([':id' => (int)$new]);
                    if ($s->fetchColumn() === false) { $errors['emirate_id'] = 'The proposed emirate no longer exists.'; }
                }
                break;
            // Unknown fields are ignored (never applied).
        }
    }
    return $errors;
}

/**
 * Approve & apply the whole request (all-or-nothing). Re-validates first; on any
 * invalid field returns {ok:false,error} without touching the venue. On success:
 * applies only the requested columns (owner-scoped WHERE), captures a slug 301 if
 * the slug changed, marks the request approved, audits, and emails the provider.
 * @return array{ok:bool,error?:string,warning?:string}
 */
function cr_approve(PDO $pdo, array $req, int $adminUserId, string $note): array
{
    if (($req['type'] ?? '') !== 'edit' || ($req['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'error' => 'This request can no longer be approved.'];
    }
    $rid     = (int)$req['id'];
    $venueId = (int)$req['venue_id'];
    $pid     = (int)$req['partner_id'];
    $changes = cr_decode_changes($req['proposed_changes_json']);
    $meta    = cr_field_meta();

    // Only known requestable fields are ever applied.
    $apply = [];
    foreach ($changes as $field => $pair) {
        if (isset($meta[$field])) { $apply[$field] = $pair; }
    }
    if (!$apply) { return ['ok' => false, 'error' => 'This request has no applicable changes.']; }

    $errors = _cr_validate_edit($pdo, $apply, $venueId);
    if ($errors) { return ['ok' => false, 'error' => implode(' ', $errors)]; }

    // Current values (for slug-redirect + audit "old").
    $cur = $pdo->prepare('SELECT name, slug, venue_type_id, emirate_id FROM venues WHERE id = :id LIMIT 1');
    $cur->execute([':id' => $venueId]);
    $before = $cur->fetch();
    if ($before === false) { return ['ok' => false, 'error' => 'The venue no longer exists.']; }

    $oldValues = $appliedValues = [];
    $sets = []; $bind = [];
    foreach ($apply as $field => $pair) {
        $new = $pair['new'] ?? null;
        if (in_array($field, ['venue_type_id', 'emirate_id'], true)) {
            $new = ($new === null || $new === '') ? null : (int)$new;
        } elseif ($field === 'slug') {
            $new = strtolower(trim((string)$new));
        } else {
            $new = trim((string)$new);
        }
        $sets[] = "$field = :$field";
        $bind[":$field"]        = $new;
        $oldValues[$field]      = $before[$field] ?? null;
        $appliedValues[$field]  = $new;
    }

    try {
        $pdo->beginTransaction();

        $bind[':id']  = $venueId;
        $bind[':pid'] = $pid;
        $upd = $pdo->prepare('UPDATE venues SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND partner_id = :pid');
        $upd->execute($bind);

        // #10 — capture a 301 from the old pretty slug to the new one.
        if (isset($appliedValues['slug']) && (string)$before['slug'] !== (string)$appliedValues['slug']) {
            slug_redirect_capture($pdo, 'venue', (string)$before['slug'], (string)$appliedValues['slug'], $venueId);
        }

        $mark = $pdo->prepare(
            "UPDATE venue_change_requests
             SET status='approved', reviewed_by=:by, reviewed_at=NOW(), review_note=:note, updated_at=NOW()
             WHERE id=:rid AND status='pending'"
        );
        $mark->execute([':by' => $adminUserId, ':note' => ($note !== '' ? $note : null), ':rid' => $rid]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('cr_approve failed (request=' . $rid . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong applying the changes. Please try again.'];
    }

    // Best-effort provider email (never rolls back an applied approval).
    $mailOk = cr_notify_provider($req, 'approved', $note);
    audit_log($pdo, $adminUserId, 'approve', 'change_request', $rid,
        ['venue_id' => $venueId, 'partner_id' => $pid, 'values' => $oldValues],
        ['applied' => $appliedValues, 'email_sent' => $mailOk]);

    $out = ['ok' => true];
    if (!$mailOk) { $out['warning'] = 'Saved, but the provider notification email failed to send.'; }
    return $out;
}

/** Reject the request (note REQUIRED — caller enforces). Venue untouched. */
function cr_reject(PDO $pdo, array $req, int $adminUserId, string $note): array
{
    return _cr_decline($pdo, $req, $adminUserId, $note, 'rejected', 'reject');
}

/** Ask the provider to revise (note REQUIRED). Venue untouched; they can resubmit. */
function cr_needs_changes(PDO $pdo, array $req, int $adminUserId, string $note): array
{
    return _cr_decline($pdo, $req, $adminUserId, $note, 'needs_changes', 'needs_changes');
}

/** Shared non-applying decision (reject / needs_changes). */
function _cr_decline(PDO $pdo, array $req, int $adminUserId, string $note, string $status, string $auditAction): array
{
    if (($req['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'error' => 'This request can no longer be updated.'];
    }
    $rid = (int)$req['id'];
    try {
        $stmt = $pdo->prepare(
            "UPDATE venue_change_requests
             SET status=:st, reviewed_by=:by, reviewed_at=NOW(), review_note=:note, updated_at=NOW()
             WHERE id=:rid AND status='pending'"
        );
        $stmt->execute([':st' => $status, ':by' => $adminUserId, ':note' => $note, ':rid' => $rid]);
    } catch (Throwable $e) {
        error_log('cr_' . $auditAction . ' failed (request=' . $rid . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong updating the request. Please try again.'];
    }

    $decision = ($status === 'rejected') ? 'rejected' : 'changes-requested';
    $mailOk = cr_notify_provider($req, $decision, $note);
    audit_log($pdo, $adminUserId, $auditAction, 'change_request', $rid, null,
        ['review_note' => $note, 'email_sent' => $mailOk]);

    $out = ['ok' => true];
    if (!$mailOk) { $out['warning'] = 'Saved, but the provider notification email failed to send.'; }
    return $out;
}

/* ==========================================================================
 * #3 U-P6b — NEW-VENUE submissions (structured review + completeness gate).
 * ======================================================================== */

/**
 * Load the pending venue behind a new_venue request + display context:
 * resolved type/emirate names, its event-type names, and image count.
 * Returns ['venue'=>array|null, 'type_name'=>?, 'emirate_name'=>?,
 *          'event_types'=>string[], 'image_count'=>int].
 */
function cr_load_new_venue(PDO $pdo, array $req): array
{
    $venueId = (int)($req['venue_id'] ?? 0);
    $venue   = $venueId > 0 ? venue_admin_get($pdo, $venueId) : null;

    $typeName = $emirateName = null;
    $eventTypes = [];
    $imageCount = 0;

    if ($venue !== null) {
        $fk = _cr_fk_maps($pdo);
        $typeName    = $venue['venue_type_id'] ? ($fk['venue_type'][(int)$venue['venue_type_id']] ?? null) : null;
        $emirateName = $venue['emirate_id'] ? ($fk['emirate'][(int)$venue['emirate_id']] ?? null) : null;

        $s = $pdo->prepare(
            'SELECT et.name FROM venue_event_types vet
             JOIN event_types et ON et.id = vet.event_type_id
             WHERE vet.venue_id = :vid ORDER BY et.sort_order, et.name'
        );
        $s->execute([':vid' => $venueId]);
        $eventTypes = array_map(static fn($r) => (string)$r['name'], $s->fetchAll());

        // #9c — the publish gate requires a CLEARED image, not just any image.
        // 'image_count' is therefore the count of images with a cleared
        // permission_status (approved_by_provider / owned_by_atv / licensed_stock).
        $cleared = venue_images_cleared_statuses();
        $ph      = implode(',', array_fill(0, count($cleared), '?'));
        $cs = $pdo->prepare(
            "SELECT COUNT(*) FROM venue_images
             WHERE venue_id = ? AND status = 'active' AND permission_status IN ($ph)"
        );
        $cs->execute(array_merge([$venueId], $cleared));
        $imageCount = (int)$cs->fetchColumn();
    }

    return [
        'venue'        => $venue,
        'type_name'    => $typeName,
        'emirate_name' => $emirateName,
        'event_types'  => $eventTypes,
        'image_count'  => $imageCount,   // #9c: count of CLEARED images (gate input)
    ];
}

/**
 * Completeness of a pending venue against the locked required-to-publish set.
 * Returns ['score'=>int %, 'missing'=>string[], 'can_publish'=>bool].
 * $clearedImageCount = images whose permission_status is cleared (#9c gate).
 */
function cr_newvenue_completeness(array $venue, int $eventTypeCount, int $clearedImageCount): array
{
    $nonEmpty = static fn($v): bool => $v !== null && trim((string)$v) !== '';
    $posInt   = static fn($v): bool => (int)$v > 0;

    // Each entry: [label, present?]. Order = display order.
    $checks = [
        ['Name',                        $nonEmpty($venue['name'] ?? null)],
        ['Slug',                        $nonEmpty($venue['slug'] ?? null)],
        ['Provider',                    $posInt($venue['partner_id'] ?? 0)],
        ['Primary emirate',             $posInt($venue['emirate_id'] ?? 0)],
        ['Area or address',             $nonEmpty($venue['area'] ?? null) || $nonEmpty($venue['address'] ?? null)],
        ['Venue type',                  $posInt($venue['venue_type_id'] ?? 0)],
        ['At least one event type',     $eventTypeCount >= 1],
        ['Capacity',                    ((int)($venue['capacity_min'] ?? 0) > 0) || ((int)($venue['capacity_max'] ?? 0) > 0)],
        ['Description',                 $nonEmpty($venue['description'] ?? null)],
        // #9c: the photo requirement is a cleared image (rights confirmed), not
        // just any upload — $clearedImageCount comes from cr_load_new_venue.
        ['≥1 image with confirmed rights', $clearedImageCount >= 1],
    ];

    $total   = count($checks);
    $present = 0;
    $missing = [];
    foreach ($checks as [$label, $ok]) {
        if ($ok) { $present++; } else { $missing[] = $label; }
    }

    return [
        'score'       => (int)round($present / $total * 100),
        'missing'     => $missing,
        'can_publish' => $missing === [],
        'checks'      => $checks,   // for the checklist UI
    ];
}

/**
 * Decide a new_venue submission. $decision ∈ approve_publish | approve_draft |
 * request_changes | reject. Only for type='new_venue' with status pending/
 * needs_changes. approve_publish re-checks completeness server-side. Applies the
 * venue status change + request status in one transaction, audits (incl. the
 * missing-fields snapshot at decision time), and emails the provider.
 * @return array{ok:bool,error?:string,warning?:string}
 */
function cr_newvenue_decide(PDO $pdo, array $req, int $adminUserId, string $decision, string $note): array
{
    if (($req['type'] ?? '') !== 'new_venue'
        || !in_array((string)($req['status'] ?? ''), ['pending', 'needs_changes'], true)) {
        return ['ok' => false, 'error' => 'This submission can no longer be reviewed.'];
    }
    $map = [
        'approve_publish' => ['published', 'approved',      'nv_published'],
        'approve_draft'   => ['draft',     'approved',      'nv_draft'],
        'request_changes' => ['needs_changes', 'needs_changes', 'nv_changes'],
        'reject'          => ['archived',  'rejected',      'nv_rejected'],
    ];
    if (!isset($map[$decision])) {
        return ['ok' => false, 'error' => 'Unknown decision.'];
    }
    [$venueStatus, $reqStatus, $mailDecision] = $map[$decision];

    $rid     = (int)$req['id'];
    $venueId = (int)$req['venue_id'];
    $pid     = (int)$req['partner_id'];

    // Snapshot completeness at decision time (for the publish guard + audit).
    $ctx  = cr_load_new_venue($pdo, $req);
    $comp = $ctx['venue'] !== null
        ? cr_newvenue_completeness($ctx['venue'], count($ctx['event_types']), $ctx['image_count'])
        : ['can_publish' => false, 'missing' => ['Venue not found']];

    if ($decision === 'approve_publish' && !$comp['can_publish']) {
        return ['ok' => false, 'error' => 'Cannot publish — missing: ' . implode(', ', $comp['missing']) . '.'];
    }

    $beforeStatus = (string)($ctx['venue']['status'] ?? '');

    try {
        $pdo->beginTransaction();

        if ($venueStatus !== null) {
            $uv = $pdo->prepare('UPDATE venues SET status = :st WHERE id = :vid AND partner_id = :pid');
            $uv->execute([':st' => $venueStatus, ':vid' => $venueId, ':pid' => $pid]);
        }

        $ur = $pdo->prepare(
            "UPDATE venue_change_requests
             SET status=:st, reviewed_by=:by, reviewed_at=NOW(), review_note=:note, updated_at=NOW()
             WHERE id=:rid AND status IN ('pending','needs_changes')"
        );
        $ur->execute([':st' => $reqStatus, ':by' => $adminUserId,
                      ':note' => ($note !== '' ? $note : null), ':rid' => $rid]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('cr_newvenue_decide failed (request=' . $rid . ', decision=' . $decision . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong recording the decision. Please try again.'];
    }

    $mailOk = cr_notify_provider($req, $mailDecision, $note);
    audit_log($pdo, $adminUserId, $decision, 'change_request', $rid,
        ['venue_id' => $venueId, 'partner_id' => $pid, 'venue_status' => $beforeStatus],
        ['venue_status' => ($venueStatus ?? $beforeStatus), 'missing_fields' => $comp['missing'],
         'note' => $note, 'email_sent' => $mailOk]);

    $out = ['ok' => true];
    if (!$mailOk) { $out['warning'] = 'Saved, but the provider notification email failed to send.'; }
    return $out;
}

/* ==========================================================================
 * #3 U-P8b — CLAIM review (approve/reassign · request-proof · reject).
 * ======================================================================== */

/** Host of a URL/email-domain, lowercased, without a leading www. */
function _cr_norm_host(string $s): string
{
    $s = trim(strtolower($s));
    if ($s === '') { return ''; }
    if (strpos($s, '@') !== false) { $s = substr($s, strpos($s, '@') + 1); }   // email → domain
    elseif (preg_match('~^https?://~', $s)) { $s = (string)(parse_url($s, PHP_URL_HOST) ?: ''); }
    $s = preg_replace('~^www\.~', '', $s);
    return (string)$s;
}

/**
 * Full claim context for the review screen. Decodes proposed_changes_json.claim +
 * .review, resolves target venue + current assignment + claimant + requester, and
 * computes contested + a work-email/website domain check.
 */
function cr_load_claim(PDO $pdo, array $req): array
{
    $data   = cr_decode_changes($req['proposed_changes_json']);
    $claim  = (is_array($data) && isset($data['claim']) && is_array($data['claim'])) ? $data['claim'] : [];
    $review = (is_array($data) && isset($data['review']) && is_array($data['review'])) ? $data['review'] : [];

    $venueId  = (int)($req['venue_id'] ?? 0);
    $claimant = (int)($req['partner_id'] ?? 0);

    $vs = $pdo->prepare(
        'SELECT v.id, v.name, v.slug, v.status, v.website, v.partner_id,
                v.management_source, v.provider_assigned_at,
                cur.org_name AS current_owner_name, cur.email AS current_owner_email
         FROM venues v LEFT JOIN partners cur ON cur.id = v.partner_id
         WHERE v.id = :vid LIMIT 1'
    );
    $vs->execute([':vid' => $venueId]);
    $venue = $vs->fetch() ?: null;

    $currentOwnerId = $venue !== null ? (int)($venue['partner_id'] ?? 0) : 0;
    $contested      = ($currentOwnerId > 0 && $currentOwnerId !== $claimant);

    // Domain check: work_email domain vs venue website host.
    $emailHost = _cr_norm_host((string)($claim['work_email'] ?? ''));
    $siteHost  = _cr_norm_host((string)($venue['website'] ?? ''));
    if ($emailHost === '' || $siteHost === '') { $domainCheck = 'unknown'; }
    else { $domainCheck = ($emailHost === $siteHost) ? 'match' : 'no_match'; }

    return [
        'claim'          => $claim,
        'review'         => $review,
        'venue'          => $venue,
        'claimant_name'  => (string)($req['provider_name'] ?? ''),
        'requester_name' => (string)($req['submitter_name'] ?? ''),
        'requester_email' => (string)($req['submitter_email'] ?? ''),
        'contested'      => $contested,
        'current_owner_name'  => $venue['current_owner_name'] ?? null,
        'current_owner_email' => $venue['current_owner_email'] ?? null,
        'management_source'   => $venue['management_source'] ?? null,
        'assigned_at'         => $venue['provider_assigned_at'] ?? null,
        'domain_check'   => $domainCheck,
        'email_host'     => $emailHost,
        'site_host'      => $siteHost,
    ];
}

/** Note required on reject + request-proof. */
function cr_claim_reject_note_required(): bool { return true; }

/** Allowed evidence enums (value => label) for the review record. */
function cr_claim_evidence_statuses(): array
{
    return ['not_verified' => 'Not verified', 'verified' => 'Verified',
            'insufficient' => 'Insufficient', 'conflicting' => 'Conflicting'];
}
function cr_claim_evidence_types(): array
{
    return ['website_link' => 'Website link', 'email_domain' => 'Email domain',
            'management_agreement' => 'Management agreement', 'brand_owned_page' => 'Brand-owned page',
            'manual_confirmation' => 'Manual confirmation'];
}

/**
 * Decide a claim. $decision ∈ approve | request_proof | reject. $in carries the
 * evidence review + (approve) verified_confirm + notify, and the review note.
 * Approve reassigns the venue to the claimant (management_source='provider_claimed')
 * inside a transaction, gated server-side on a verification confirm when contested.
 * @return array{ok:bool,error?:string,warning?:string}
 */
function cr_claim_decide(PDO $pdo, array $req, int $adminUserId, string $decision, array $in): array
{
    if (($req['type'] ?? '') !== 'claim' || (string)($req['status'] ?? '') !== 'pending') {
        return ['ok' => false, 'error' => 'This claim can no longer be reviewed.'];
    }
    if (!in_array($decision, ['approve', 'request_proof', 'reject'], true)) {
        return ['ok' => false, 'error' => 'Unknown decision.'];
    }
    $note = trim((string)($in['note'] ?? ''));
    if (in_array($decision, ['request_proof', 'reject'], true) && $note === '') {
        return ['ok' => false, 'error' => 'A note to the provider is required for this decision.'];
    }

    $ctx      = cr_load_claim($pdo, $req);
    $rid      = (int)$req['id'];
    $venueId  = (int)$req['venue_id'];
    $claimant = (int)$req['partner_id'];

    // Evidence review record (admin-only; stored under the "review" json key).
    $evStatus = isset(cr_claim_evidence_statuses()[$in['evidence_status'] ?? '']) ? (string)$in['evidence_status'] : 'not_verified';
    $evType   = isset(cr_claim_evidence_types()[$in['evidence_type'] ?? '']) ? (string)$in['evidence_type'] : 'manual_confirmation';
    $review = [
        'evidence_status' => $evStatus,
        'evidence_type'   => $evType,
        'internal_note'   => mb_substr(trim(strip_tags((string)($in['internal_note'] ?? ''))), 0, 2000),
        'decision'        => $decision,
        'decided_by'      => $adminUserId,
        'decided_at'      => date('Y-m-d H:i:s'),
    ];
    $data = cr_decode_changes($req['proposed_changes_json']);
    if (!is_array($data)) { $data = []; }
    $data['review'] = $review;
    $mergedJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    /* ---------------- APPROVE (reassign) ---------------- */
    if ($decision === 'approve') {
        if ($ctx['venue'] === null) {
            return ['ok' => false, 'error' => 'The venue no longer exists.'];
        }
        // Server-side conflict gate.
        if ($ctx['contested'] && (string)($in['verified_confirm'] ?? '') !== '1') {
            return ['ok' => false, 'error' => 'Tick the verification confirmation before approving a contested claim.'];
        }
        $previousOwner = (int)($ctx['venue']['partner_id'] ?? 0) ?: null;
        if ($previousOwner === $claimant) {
            return ['ok' => false, 'error' => 'This venue is already managed by the claimant.'];
        }

        $notify = in_array((string)($in['notify'] ?? 'before'), ['before', 'after', 'none'], true) ? (string)$in['notify'] : 'before';

        try {
            $pdo->beginTransaction();
            $uv = $pdo->prepare(
                "UPDATE venues SET partner_id = :cl, management_source = 'provider_claimed',
                        provider_assigned_at = NOW(), provider_assigned_by = :admin
                 WHERE id = :vid"
            );
            $uv->execute([':cl' => $claimant, ':admin' => $adminUserId, ':vid' => $venueId]);

            $ur = $pdo->prepare(
                "UPDATE venue_change_requests
                 SET status='approved', reviewed_by=:by, reviewed_at=NOW(), review_note=:note,
                     proposed_changes_json=:json, updated_at=NOW()
                 WHERE id=:rid AND status='pending'"
            );
            $ur->execute([':by' => $adminUserId, ':note' => ($note !== '' ? $note : null),
                          ':json' => $mergedJson, ':rid' => $rid]);
            if ($ur->rowCount() < 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'This claim can no longer be reviewed.'];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('cr_claim_decide approve failed (request=' . $rid . '): ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Something went wrong approving the claim. Please try again.'];
        }

        $mailClaimant = cr_notify_provider($req, 'claim_approved', $note);
        $mailIncumbent = null;
        if ($notify !== 'none' && $previousOwner && trim((string)($ctx['current_owner_email'] ?? '')) !== '') {
            $mailIncumbent = _cr_notify_incumbent((string)$ctx['current_owner_email'],
                (string)($ctx['venue']['name'] ?? 'a venue'), (string)($ctx['claimant_name'] ?? ''));
        }
        audit_log($pdo, $adminUserId, 'approve', 'change_request', $rid,
            ['venue_id' => $venueId, 'previous_owner' => $previousOwner],
            ['new_owner' => $claimant, 'evidence_status' => $evStatus, 'decision' => 'approve',
             'notify' => $notify, 'notified_claimant' => $mailClaimant, 'notified_incumbent' => $mailIncumbent]);

        $out = ['ok' => true];
        if (!$mailClaimant) { $out['warning'] = 'Reassigned, but the claimant notification email failed to send.'; }
        return $out;
    }

    /* ---------------- REQUEST PROOF / REJECT (no reassign) ---------------- */
    $status   = ($decision === 'reject') ? 'rejected' : 'needs_changes';
    $mailKey  = ($decision === 'reject') ? 'claim_rejected' : 'claim_proof';
    try {
        $ur = $pdo->prepare(
            "UPDATE venue_change_requests
             SET status=:st, reviewed_by=:by, reviewed_at=NOW(), review_note=:note,
                 proposed_changes_json=:json, updated_at=NOW()
             WHERE id=:rid AND status='pending'"
        );
        $ur->execute([':st' => $status, ':by' => $adminUserId, ':note' => $note, ':json' => $mergedJson, ':rid' => $rid]);
        if ($ur->rowCount() < 1) {
            return ['ok' => false, 'error' => 'This claim can no longer be reviewed.'];
        }
    } catch (Throwable $e) {
        error_log('cr_claim_decide ' . $decision . ' failed (request=' . $rid . '): ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Something went wrong recording the decision. Please try again.'];
    }

    $mailOk = cr_notify_provider($req, $mailKey, $note);
    audit_log($pdo, $adminUserId, $decision, 'change_request', $rid, null,
        ['decision' => $decision, 'evidence_status' => $evStatus, 'note' => $note, 'notified' => $mailOk]);

    $out = ['ok' => true];
    if (!$mailOk) { $out['warning'] = 'Saved, but the claimant notification email failed to send.'; }
    return $out;
}

/** Email the incumbent provider that their venue was reassigned. */
function _cr_notify_incumbent(string $to, string $venueName, string $newOwner): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) { return false; }
    $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $body = '<div style="font-family:Arial,sans-serif;color:#0E1B2A;line-height:1.5;">'
          . '<h2 style="font-size:18px;">All The Venues — Provider Portal</h2>'
          . '<p>Hello,</p>'
          . '<p>Management of <strong>' . $esc($venueName) . '</strong> on All The Venues has been reassigned'
          . ($newOwner !== '' ? ' to <strong>' . $esc($newOwner) . '</strong>' : '')
          . ' following a verified ownership claim. It no longer appears in your provider portal.</p>'
          . '<p>If you believe this is a mistake, please contact All The Venues support.</p>'
          . '<p style="color:#6b7b88;">— The All The Venues team</p></div>';
    return send_mail($to, 'A venue was reassigned — All The Venues', $body);
}

/**
 * Email the submitting provider about a decision. $decision ∈ approved |
 * changes-requested | rejected  (edit requests), or  nv_published | nv_draft |
 * nv_changes | nv_rejected  (new-venue submissions), or  claim_approved |
 * claim_proof | claim_rejected  (claims). send_mail() never throws (returns bool).
 * Returns false (and logs) when there is no recipient / send failed.
 */
function cr_notify_provider(array $req, string $decision, string $note): bool
{
    $to = trim((string)($req['recipient'] ?? ($req['submitter_email'] ?? $req['provider_email'] ?? '')));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('cr_notify_provider: no valid recipient for request ' . (int)($req['id'] ?? 0));
        return false;
    }
    $esc     = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $venue   = (string)($req['venue_name'] ?? 'your venue');
    $link    = base_url('portal/venues/' . (int)($req['venue_id'] ?? 0));

    // Decisions that are pure approvals (no reviewer note shown to the provider).
    $positive = ['approved', 'nv_published', 'nv_draft', 'claim_approved'];

    switch ($decision) {
        case 'claim_approved':
            $subject = 'Your venue claim was approved — ' . $venue;
            $intro   = 'Good news — your claim for <strong>' . $esc($venue)
                     . '</strong> has been approved. It now appears in your provider portal and you can manage it.';
            $link    = base_url('portal/venues/' . (int)($req['venue_id'] ?? 0));
            break;
        case 'claim_proof':
            $subject = 'More information needed for your venue claim — ' . $venue;
            $intro   = 'We reviewed your claim for <strong>' . $esc($venue)
                     . '</strong> and need proof of authorisation before we can approve it. You can add proof from your provider portal.';
            $link    = base_url('portal/claim');
            break;
        case 'claim_rejected':
            $subject = 'Your venue claim was declined — ' . $venue;
            $intro   = 'Your claim for <strong>' . $esc($venue)
                     . '</strong> was reviewed and could not be approved. The current venue assignment is unchanged.';
            $link    = base_url('portal/claim');
            break;
        case 'approved':
            $subject = 'Your change request was approved — ' . $venue;
            $intro   = 'Good news — your requested changes to <strong>' . $esc($venue)
                     . '</strong> have been reviewed and applied. They are now live.';
            break;
        case 'rejected':
            $subject = 'Your change request was declined — ' . $venue;
            $intro   = 'Your requested changes to <strong>' . $esc($venue)
                     . '</strong> were reviewed and could not be applied.';
            break;
        case 'nv_published':
            $subject = 'Your venue is now live — ' . $venue;
            $intro   = 'Good news — your venue submission <strong>' . $esc($venue)
                     . '</strong> has been reviewed and published. It is now live on All The Venues.';
            break;
        case 'nv_draft':
            $subject = 'Your venue submission was accepted — ' . $venue;
            $intro   = 'Your venue submission <strong>' . $esc($venue)
                     . '</strong> has been accepted and saved as a draft. Our team will finish preparing it before it goes live.';
            break;
        case 'nv_rejected':
            $subject = 'Your venue submission was declined — ' . $venue;
            $intro   = 'Your venue submission <strong>' . $esc($venue)
                     . '</strong> was reviewed and could not be accepted.';
            break;
        case 'nv_changes':
            $subject = 'Changes requested on your venue submission — ' . $venue;
            $intro   = 'We reviewed your venue submission <strong>' . $esc($venue)
                     . '</strong> and need a few changes before it can go live. Please update your venue in the portal and we will re-review it.';
            break;
        default: // changes-requested (edit)
            $subject = 'Changes requested on your update — ' . $venue;
            $intro   = 'We reviewed your requested changes to <strong>' . $esc($venue)
                     . '</strong> and need a few adjustments before they can be applied. You can revise and resubmit.';
    }

    $noteHtml = (!in_array($decision, $positive, true) && trim($note) !== '')
        ? '<p style="margin:16px 0;padding:12px 14px;background:#f4f1ea;border-radius:6px;">'
          . '<strong>Reviewer note:</strong><br>' . nl2br($esc($note)) . '</p>'
        : '';

    $body = '<div style="font-family:Arial,sans-serif;color:#0E1B2A;line-height:1.5;">'
          . '<h2 style="font-size:18px;">All The Venues — Provider Portal</h2>'
          . '<p>Hello,</p>'
          . '<p>' . $intro . '</p>'
          . $noteHtml
          . '<p><a href="' . $esc($link) . '">View this venue in your portal</a></p>'
          . '<p style="color:#6b7b88;">— The All The Venues team</p>'
          . '</div>';

    return send_mail($to, $subject, $body);
}

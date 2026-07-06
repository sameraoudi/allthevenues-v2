<?php
declare(strict_types=1);

/**
 * #3 U-P5a — Provider portal: sensitive-field CHANGE REQUESTS (submit side).
 * Reached via dispatch (/portal/venues/{id}/request[/withdraw]), already
 * auth_require_partner-gated. A provider PROPOSES changes to REQUEST-tier fields
 * (name, slug, venue_type_id, emirate_id) — this writes ONE venue_change_requests
 * row (type='edit', status='pending') holding the diff; the venues table is NEVER
 * touched here. Ownership is re-checked on GET and POST (portal_venue_for_partner);
 * withdraw + create are owner-scoped in the query WHERE.
 * Expects in scope: $pdo, int $vid, string $requestAction ('' | 'withdraw'), $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';    // audit_log()
require_once __DIR__ . '/../../lib/portal.php';   // request fields + create/withdraw + owner-scoped read

$userId = (int)(auth_user()['id'] ?? 0);

$venue = portal_venue_for_partner($pdo, $vid, $partnerId);
if ($venue === null) {
    http_response_code(404);   // not owned / not found — existence never revealed
    $page_title          = 'Not found — Provider Portal';
    $portal_content_view = __DIR__ . '/../content/404_content.php';
    require __DIR__ . '/layout.php';
    return;
}

$pending = portal_pending_edit_request($pdo, $vid, $partnerId);

/* ---- (A) Withdraw the provider's own pending request (POST only) ------------ */
if ($requestAction === 'withdraw') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_validate()) {
        $rid = (int)($_POST['request_id'] ?? 0);
        if (portal_withdraw_request($pdo, $rid, $vid, $partnerId)) {
            audit_log($pdo, $userId ?: null, 'withdraw', 'change_request', $rid);
            $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Request withdrawn.'];
        }
    } else {
        $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    }
    redirect('portal/venues/' . $vid);
    return;
}

/* ---- (B) The request form / submit ------------------------------------------ */
// One pending edit request per venue — bounce to detail if one already exists.
if ($pending !== null) {
    $_SESSION['portal_flash'] = ['type' => 'error',
        'msg' => 'You already have a pending request for this venue; withdraw it before submitting another.'];
    redirect('portal/venues/' . $vid);
    return;
}

$errors = [];
$old    = [
    'name'          => (string)$venue['name'],
    'slug'          => (string)$venue['slug'],
    'venue_type_id' => (string)($venue['venue_type_id'] ?? ''),
    'emirate_id'    => (string)($venue['emirate_id'] ?? ''),
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = array_merge($old, array_intersect_key($_POST, $old));

    if (!csrf_validate()) {
        $errors['_form'] = 'Your session expired. Please review and submit again.';
    } else {
        // Validate the 4 REQUEST fields EXACTLY as admin edit does.
        $proposed = [];

        // name — required, plain, ≤255.
        $name = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 255);
        if ($name === '') { $errors['name'] = 'Name is required.'; }
        $proposed['name'] = $name;

        // slug — lowercase; format; ≤191; available (excluding this venue).
        $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
        if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 191) {
            $errors['slug'] = 'Use lowercase letters, numbers and hyphens only.';
        } elseif (!venue_slug_available($pdo, $slug, $vid)) {
            $errors['slug'] = 'That slug is already in use by another venue.';
        }
        $proposed['slug'] = $slug;

        // venue_type_id — nullable; validated if set.
        $vt = (int)($_POST['venue_type_id'] ?? 0);
        if ($vt > 0) {
            $s = $pdo->prepare('SELECT 1 FROM venue_types WHERE id = :id');
            $s->execute([':id' => $vt]);
            $proposed['venue_type_id'] = $s->fetchColumn() !== false ? $vt : null;
        } else { $proposed['venue_type_id'] = null; }

        // emirate_id — nullable; validated if set.
        $em = (int)($_POST['emirate_id'] ?? 0);
        if ($em > 0) {
            $s = $pdo->prepare('SELECT 1 FROM emirates WHERE id = :id');
            $s->execute([':id' => $em]);
            $proposed['emirate_id'] = $s->fetchColumn() !== false ? $em : null;
        } else { $proposed['emirate_id'] = null; }

        // Build the diff — only fields whose proposed value differs from current.
        $changes = [];
        foreach (portal_venue_request_fields() as $f) {
            $current = $venue[$f] ?? null;
            $new     = $proposed[$f] ?? null;
            if ((string)$current !== (string)$new) {
                $changes[$f] = ['old' => $current, 'new' => $new];
            }
        }

        if (!$errors && !$changes) {
            $errors['_form'] = 'No changes to request.';
        }

        if (!$errors) {
            try {
                $rid = portal_create_edit_request($pdo, $vid, $partnerId, $userId, $changes);
                audit_log($pdo, $userId ?: null, 'create', 'change_request', $rid, null, $changes);
                $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Change request submitted for review.'];
                redirect('portal/venues/' . $vid);
            } catch (Throwable $e) {
                error_log('portal change request failed (venue=' . $vid . '): ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong submitting your request. Please try again.';
            }
        }
    }
}

$page_title          = 'Request changes — Provider Portal';
$portal_active       = 'venues';
$portal_content_view = __DIR__ . '/venue-request-content.php';
require __DIR__ . '/layout.php';

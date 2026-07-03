<?php
declare(strict_types=1);

/**
 * Admin venue management controller (U4a). Already gated by dispatch
 * (auth_require_admin). Handles: list, edit (GET), save (POST).
 * Expects $pdo and $sub ('venues...') in scope.
 *
 * Scope fence: edits ONLY the venue's own scalar/text columns. Does not touch
 * venue_images / venue_layout_capacity / venue_event_types / partner records.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/venues.php';
require_once __DIR__ . '/../../lib/venue_admin.php';
require_once __DIR__ . '/../../lib/venue_images_admin.php';

$me   = auth_current_user();
$rest = trim(substr((string)$sub, strlen('venues')), '/');   // '' | 'edit' | 'images/*'

/* ===================== IMAGE MANAGER ACTIONS (U4b) ===================== */
// POST-only image mutations (upload/primary/reorder/alt/delete). The controller
// validates CSRF, audits, and redirects back to the venue edit page.
if (strncmp($rest, 'images/', 7) === 0) {
    require __DIR__ . '/venue-images.php';
    return;
}

/* ============================ LIST ====================================== */
if ($rest === '') {
    $filters = venue_admin_filters($_GET);
    $perPage = 25;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $result  = venue_admin_list($pdo, $filters, $page, $perPage);
    $rows    = $result['rows'];
    $total   = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    $emirates   = venue_emirates($pdo);
    $venueTypes = venue_types_all($pdo);

    $admin_active       = 'venues';
    $page_title         = 'Venues — Admin';
    $admin_page_title   = 'Venues';
    $admin_content_view = __DIR__ . '/venues-list.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ EDIT / SAVE =============================== */
if ($rest === 'edit') {
    $id    = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $venue = $id > 0 ? venue_admin_get($pdo, $id) : null;
    if ($venue === null) {
        http_response_code(404);
        $admin_active = 'venues'; $page_title = 'Not found — Admin';
        $admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
        $admin_content_view = __DIR__ . '/placeholder-content.php';
        require __DIR__ . '/layout.php';
        return;
    }

    $errors = [];
    $flash  = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    $old = $venue;   // values shown in the form (POST overrides on error)

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $old = array_merge($venue, $_POST);   // re-fill posted values on error

        if (!csrf_validate()) {
            $errors['_form'] = 'Your session expired. Please review and save again.';
        } else {
            $plain = static fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);

            $clean = [];
            // --- name (required) ---
            $clean['name'] = $plain('name', 255);
            if ($clean['name'] === '') { $errors['name'] = 'Name is required.'; }

            // --- slug (unique + format) ---
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 191) {
                $errors['slug'] = 'Use lowercase letters, numbers and hyphens only.';
            } elseif (!venue_slug_available($pdo, $slug, $id)) {
                $errors['slug'] = 'That slug is already in use by another venue.';
            }
            $clean['slug'] = $slug;

            // --- taxonomy FKs (nullable; validated if set) ---
            $vt = (int)($_POST['venue_type_id'] ?? 0);
            if ($vt > 0) {
                $s = $pdo->prepare('SELECT 1 FROM venue_types WHERE id = :id');
                $s->execute([':id' => $vt]);
                $clean['venue_type_id'] = $s->fetchColumn() !== false ? $vt : null;
            } else { $clean['venue_type_id'] = null; }

            $em = (int)($_POST['emirate_id'] ?? 0);
            if ($em > 0) {
                $s = $pdo->prepare('SELECT 1 FROM emirates WHERE id = :id');
                $s->execute([':id' => $em]);
                $clean['emirate_id'] = $s->fetchColumn() !== false ? $em : null;
            } else { $clean['emirate_id'] = null; }

            // --- scalars ---
            $clean['area']    = $plain('area', 150) ?: null;
            $clean['address'] = $plain('address', 255) ?: null;
            $clean['video_url'] = $plain('video_url', 255) ?: null;

            $io = trim((string)($_POST['indoor_outdoor'] ?? 'indoor'));
            $clean['indoor_outdoor'] = isset(venue_indoor_outdoor_options()[$io]) ? $io : 'indoor';

            $clean['capacity_min'] = ($_POST['capacity_min'] ?? '') !== '' ? max(0, (int)$_POST['capacity_min']) : null;
            $clean['capacity_max'] = ($_POST['capacity_max'] ?? '') !== '' ? max(0, (int)$_POST['capacity_max']) : null;
            $clean['minimum_spend'] = ($_POST['minimum_spend'] ?? '') !== '' ? max(0, (float)$_POST['minimum_spend']) : null;

            $pl = trim((string)($_POST['pricing_level'] ?? ''));
            $clean['pricing_level'] = in_array($pl, venue_pricing_levels(), true) ? $pl : null;

            $clean['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
            $clean['is_verified'] = !empty($_POST['is_verified']) ? 1 : 0;

            $st = trim((string)($_POST['status'] ?? ''));
            $clean['status'] = isset(venue_admin_statuses()[$st]) ? $st : $venue['status'];

            // --- rich-text (sanitized) ---
            foreach (venue_richtext_fields() as $rf) {
                $clean[$rf] = html_sanitize($_POST[$rf] ?? null);   // null if empty
            }

            if (!$errors) {
                // Diff for the audit trail (only changed columns).
                $changedOld = $changedNew = [];
                foreach ($clean as $col => $val) {
                    $before = $venue[$col] ?? null;
                    if ((string)$before !== (string)$val) {
                        $changedOld[$col] = $before;
                        $changedNew[$col] = $val;
                    }
                }
                try {
                    $sets = [];
                    foreach (array_keys($clean) as $col) { $sets[] = "$col = :$col"; }
                    $upd = $pdo->prepare('UPDATE venues SET ' . implode(', ', $sets) . ' WHERE id = :id');
                    foreach ($clean as $col => $val) { $upd->bindValue(':' . $col, $val); }
                    $upd->bindValue(':id', $id, PDO::PARAM_INT);
                    $upd->execute();

                    if ($changedNew) {
                        audit_log($pdo, (int)($me['id'] ?? 0) ?: null, 'update', 'venue', $id, $changedOld, $changedNew);
                    }
                    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Venue saved.'];
                    redirect('admin/venues/edit?id=' . $id);
                } catch (Throwable $e) {
                    error_log('venue save failed (id=' . $id . '): ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong saving the venue. Please try again.';
                }
            }
        }
    }

    $venueTypes = venue_types_all($pdo);
    $emirates   = venue_emirates($pdo);
    $images     = venue_images_admin_list($pdo, $id);   // for the image manager

    $admin_active       = 'venues';
    $page_title         = 'Edit venue — Admin';
    $admin_page_title   = 'Edit venue';
    $admin_content_view = __DIR__ . '/venue-edit.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ 404 ====================================== */
http_response_code(404);
$admin_active = 'venues'; $page_title = 'Not found — Admin';
$admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
$admin_content_view = __DIR__ . '/placeholder-content.php';
require __DIR__ . '/layout.php';

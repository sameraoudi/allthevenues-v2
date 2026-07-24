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
require_once __DIR__ . '/../../lib/slug_redirect.php';

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

/* ============================ NEW / CREATE ============================= */
if ($rest === 'new') {
    $errors = [];
    $old    = [];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $old = $_POST;   // re-fill on error

        if (!csrf_validate()) {
            $errors['_form'] = 'Your session expired. Please review and save again.';
        } else {
            $plain = static fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);

            $clean = [];
            // --- name (required) ---
            $clean['name'] = $plain('name', 255);
            if ($clean['name'] === '') { $errors['name'] = 'Name is required.'; }

            // --- slug (explicit → validate; blank → auto-generate unique) ---
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            if ($slug !== '') {
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 191) {
                    $errors['slug'] = 'Use lowercase letters, numbers and hyphens only.';
                } elseif (!venue_slug_available($pdo, $slug, 0)) {
                    $errors['slug'] = 'That slug is already in use by another venue.';
                }
                $clean['slug'] = $slug;
            } else {
                $base = slugify($clean['name']) ?: 'venue';
                $try  = $base; $n = 2;
                while (!venue_slug_available($pdo, $try, 0)) { $try = $base . '-' . $n; $n++; }
                $clean['slug'] = $try;
            }

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
            $clean['area']      = $plain('area', 150) ?: null;
            $clean['address']   = $plain('address', 255) ?: null;
            $clean['video_url'] = $plain('video_url', 255) ?: null;

            $io = trim((string)($_POST['indoor_outdoor'] ?? 'indoor'));
            $clean['indoor_outdoor'] = isset(venue_indoor_outdoor_options()[$io]) ? $io : 'indoor';

            $clean['capacity_min']  = ($_POST['capacity_min'] ?? '') !== '' ? max(0, (int)$_POST['capacity_min']) : null;
            $clean['capacity_max']  = ($_POST['capacity_max'] ?? '') !== '' ? max(0, (int)$_POST['capacity_max']) : null;
            $clean['minimum_spend'] = ($_POST['minimum_spend'] ?? '') !== '' ? max(0, (float)$_POST['minimum_spend']) : null;

            $pl = trim((string)($_POST['pricing_level'] ?? ''));
            $clean['pricing_level'] = in_array($pl, venue_pricing_levels(), true) ? $pl : null;

            // --- floor area (nullable; number + sqm/sqft unit) ---
            $clean['floor_area'] = ($_POST['floor_area'] ?? '') !== '' ? max(0, (float)$_POST['floor_area']) : null;
            $fau = trim((string)($_POST['floor_area_unit'] ?? ''));
            $clean['floor_area_unit'] = in_array($fau, ['sqm', 'sqft'], true) ? $fau : null;

            $clean['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
            $clean['is_verified'] = !empty($_POST['is_verified']) ? 1 : 0;

            $st = trim((string)($_POST['status'] ?? ''));
            $clean['status'] = isset(venue_admin_statuses()[$st]) ? $st : 'draft';

            // --- provider assignment (nullable; validated if set) ---
            $pid = (int)($_POST['partner_id'] ?? 0);
            if ($pid > 0) {
                $s = $pdo->prepare('SELECT 1 FROM partners WHERE id = :id');
                $s->execute([':id' => $pid]);
                $clean['partner_id'] = $s->fetchColumn() !== false ? $pid : null;
            } else { $clean['partner_id'] = null; }

            // --- website ---
            $clean['website'] = $plain('website', 255) ?: null;

            // --- map_embed (stored RAW; same Google-Maps-iframe guard as render) ---
            $mapEmbed = trim((string)($_POST['map_embed'] ?? ''));
            if ($mapEmbed === '') {
                $clean['map_embed'] = null;
            } elseif (!preg_match('#^<iframe[^>]*\ssrc="https://www\.google\.com/maps/#i', $mapEmbed)) {
                $errors['map_embed'] = 'Paste a valid Google Maps embed (the <iframe> code from Google Maps).';
            } else {
                $clean['map_embed'] = $mapEmbed;
            }

            // --- venue contact (internal admin-only) ---
            $clean['contact_name'] = $plain('contact_name', 255) ?: null;

            $cEmail = trim((string)($_POST['contact_email'] ?? ''));
            if ($cEmail === '') {
                $clean['contact_email'] = null;
            } elseif (!filter_var($cEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($cEmail) > 255) {
                $errors['contact_email'] = 'Enter a valid email address.';
            } else {
                $clean['contact_email'] = $cEmail;
            }

            $clean['contact_phone'] = $plain('contact_phone', 50) ?: null;

            // --- rich-text (sanitized) ---
            foreach (venue_richtext_fields() as $rf) {
                $clean[$rf] = html_sanitize($_POST[$rf] ?? null);
            }

            // --- provider-ownership provenance (#6-2): a provider chosen at
            //     creation is an admin assignment. No provider → the DB default
            //     'unassigned' applies (don't set the keys). ---
            if (!empty($clean['partner_id'])) {
                $clean['management_source']    = 'admin_assigned';
                $clean['provider_assigned_at'] = date('Y-m-d H:i:s');
                $clean['provider_assigned_by'] = (int)($me['id'] ?? 0) ?: null;
            }

            if (!$errors) {
                try {
                    $newId = venue_admin_create($pdo, $clean);
                    venue_layout_capacity_save($pdo, $newId, (array)($_POST['layout'] ?? []));
                    venue_admin_event_types_save($pdo, $newId, (array)($_POST['event_types'] ?? []));
                    audit_log($pdo, (int)($me['id'] ?? 0) ?: null, 'create', 'venue', $newId, null, $clean);
                    // Contacts-A A2 — fill any contact gap (fill-if-empty, both directions).
                    require_once __DIR__ . '/../../lib/contact_sync.php';
                    $cActor = (int)($me['id'] ?? 0) ?: null;
                    contact_sync_for_venue($pdo, $newId, $cActor);
                    if (($cPid = (int)($clean['partner_id'] ?? 0)) > 0) { contact_sync_for_provider($pdo, $cPid, $cActor); }
                    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Venue created — add images and finish the details below.'];
                    redirect('admin/venues/edit?id=' . $newId);
                } catch (Throwable $e) {
                    error_log('venue create failed: ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong creating the venue. Please try again.';
                }
            }
        }
    }

    $venueTypes = venue_types_all($pdo);
    $emirates   = venue_emirates($pdo);
    $partners   = venue_partner_options($pdo);

    $admin_active       = 'venues';
    $page_title         = 'Add venue — Admin';
    $admin_page_title   = 'Add venue';
    $admin_content_view = __DIR__ . '/venue-new.php';
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

            // --- floor area (nullable; number + sqm/sqft unit) ---
            $clean['floor_area'] = ($_POST['floor_area'] ?? '') !== '' ? max(0, (float)$_POST['floor_area']) : null;
            $fau = trim((string)($_POST['floor_area_unit'] ?? ''));
            $clean['floor_area_unit'] = in_array($fau, ['sqm', 'sqft'], true) ? $fau : null;

            $clean['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
            $clean['is_verified'] = !empty($_POST['is_verified']) ? 1 : 0;

            $st = trim((string)($_POST['status'] ?? ''));
            $clean['status'] = isset(venue_admin_statuses()[$st]) ? $st : $venue['status'];

            // --- Delist-2 Part B: stamp/clear delist bookkeeping when an admin
            //     flips the status to/from 'delisted' from the editor dropdown. ---
            $oldStatus = (string)($venue['status'] ?? '');
            if ($clean['status'] === 'delisted' && $oldStatus !== 'delisted') {
                $clean['delisted_at'] = date('Y-m-d H:i:s');
                $clean['delisted_by'] = (int)($me['id'] ?? 0) ?: null;
            } elseif ($clean['status'] !== 'delisted' && $oldStatus === 'delisted') {
                $clean['delisted_at']    = null;
                $clean['delisted_by']    = null;
                $clean['delist_reason']  = null;
                $clean['delist_details'] = null;
            }

            // --- provider assignment (nullable; validated if set) ---
            $pid = (int)($_POST['partner_id'] ?? 0);
            if ($pid > 0) {
                $s = $pdo->prepare('SELECT 1 FROM partners WHERE id = :id');
                $s->execute([':id' => $pid]);
                $clean['partner_id'] = $s->fetchColumn() !== false ? $pid : null;
            } else { $clean['partner_id'] = null; }

            // --- website ---
            $clean['website'] = $plain('website', 255) ?: null;

            // --- map_embed (stored RAW; validated against the SAME guard the
            //     public detail renders with — never html_sanitize'd) ---
            $mapEmbed = trim((string)($_POST['map_embed'] ?? ''));
            if ($mapEmbed === '') {
                $clean['map_embed'] = null;
            } elseif (!preg_match('#^<iframe[^>]*\ssrc="https://www\.google\.com/maps/#i', $mapEmbed)) {
                $errors['map_embed'] = 'Paste a valid Google Maps embed (the <iframe> code from Google Maps).';
            } else {
                $clean['map_embed'] = $mapEmbed;
            }

            // --- venue contact (internal admin-only) ---
            $clean['contact_name'] = $plain('contact_name', 255) ?: null;

            $cEmail = trim((string)($_POST['contact_email'] ?? ''));
            if ($cEmail === '') {
                $clean['contact_email'] = null;
            } elseif (!filter_var($cEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($cEmail) > 255) {
                $errors['contact_email'] = 'Enter a valid email address.';
            } else {
                $clean['contact_email'] = $cEmail;
            }

            $clean['contact_phone'] = $plain('contact_phone', 50) ?: null;

            // --- rich-text (sanitized) ---
            foreach (venue_richtext_fields() as $rf) {
                $clean[$rf] = html_sanitize($_POST[$rf] ?? null);   // null if empty
            }

            // --- provider-ownership provenance (#6-2): only when the provider
            //     actually changes. Unchanged providers keep their existing
            //     management_source (e.g. a legacy_import venue stays that). ---
            $oldPid = ($venue['partner_id'] ?? null) !== null ? (int)$venue['partner_id'] : null;
            $newPid = $clean['partner_id'];            // int or null
            if ($newPid !== $oldPid) {
                if ($newPid) {
                    $clean['management_source']    = 'admin_assigned';
                    $clean['provider_assigned_at'] = date('Y-m-d H:i:s');
                    $clean['provider_assigned_by'] = (int)($me['id'] ?? 0) ?: null;
                } else {
                    $clean['management_source']    = 'unassigned';
                    $clean['provider_assigned_at'] = null;
                    $clean['provider_assigned_by'] = null;
                }
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

                    venue_layout_capacity_save($pdo, $id, (array)($_POST['layout'] ?? []));
                    venue_admin_event_types_save($pdo, $id, (array)($_POST['event_types'] ?? []));

                    // #10 — record the old pretty slug → this venue for SEO-safe 301s.
                    slug_redirect_capture($pdo, 'venue', (string)($venue['slug'] ?? ''), $clean['slug'], $id);

                    if ($changedNew) {
                        audit_log($pdo, (int)($me['id'] ?? 0) ?: null, 'update', 'venue', $id, $changedOld, $changedNew);
                    }
                    // Contact gap-fill runs on CREATE only — an EDIT is authoritative, so a
                    // deliberately-cleared venue contact (or its provider's) is not re-filled.
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
    $partners   = venue_partner_options($pdo);           // provider-assignment select
    $images     = venue_images_admin_list($pdo, $id);   // for the image manager
    // Layout capacities for the form prefill (layout_type => capacity).
    $layoutValues = [];
    foreach (venue_layout_capacity($pdo, $id) as $lr) { $layoutValues[(string)$lr['layout_type']] = (int)$lr['capacity']; }
    // Current event-type tags for prechecking the Event types fieldset.
    $etChecked = venue_event_type_ids($pdo, $id);

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

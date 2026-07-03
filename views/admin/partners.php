<?php
declare(strict_types=1);

/**
 * Admin provider (partner) management controller (U4d-3a). Already gated
 * admin+editor by dispatch. Handles: list, edit (GET), save (POST).
 * Expects $pdo and $sub ('partners...') in scope.
 *
 * Scope fence: edits ONLY the partner's own scalar/text columns. Does not touch
 * venues / provider "type" (partner_group is unused; editable type is U4d-3b).
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/sanitize.php';
require_once __DIR__ . '/../../lib/partners.php';
require_once __DIR__ . '/../../lib/partner_admin.php';

$me   = auth_current_user();
$rest = trim(substr((string)$sub, strlen('partners')), '/');   // '' | 'edit' | 'cover/*'

/* ===================== COVER IMAGE ACTIONS (U4d-3c) ==================== */
// POST-only cover mutations (upload/alt/delete). The controller validates CSRF,
// audits, and redirects back to the provider edit page.
if (strncmp($rest, 'cover/', 6) === 0) {
    require __DIR__ . '/partner-cover.php';
    return;
}

/* ============================ LIST ====================================== */
if ($rest === '') {
    $filters = partner_admin_filters($_GET);
    $perPage = 25;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $result  = partner_admin_list($pdo, $filters, $page, $perPage);
    $rows    = $result['rows'];
    $total   = $result['total'];
    $totalPages = max(1, (int)ceil($total / $perPage));

    $emirates = venue_emirates($pdo);

    $admin_active       = 'partners';
    $page_title         = 'Providers — Admin';
    $admin_page_title   = 'Providers';
    $admin_content_view = __DIR__ . '/partners-list.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ EDIT / SAVE =============================== */
if ($rest === 'edit') {
    $id      = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    $partner = $id > 0 ? partner_admin_get($pdo, $id) : null;
    if ($partner === null) {
        http_response_code(404);
        $admin_active = 'partners'; $page_title = 'Not found — Admin';
        $admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
        $admin_content_view = __DIR__ . '/placeholder-content.php';
        require __DIR__ . '/layout.php';
        return;
    }

    $errors = [];
    $flash  = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    $old = $partner;   // values shown in the form (POST overrides on error)

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $old = array_merge($partner, $_POST);   // re-fill posted values on error

        if (!csrf_validate()) {
            $errors['_form'] = 'Your session expired. Please review and save again.';
        } else {
            $plain = static fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);

            $clean = [];
            // --- org_name (required) ---
            $clean['org_name'] = $plain('org_name', 255);
            if ($clean['org_name'] === '') { $errors['org_name'] = 'Name is required.'; }

            // --- slug (unique + format) ---
            $slug = strtolower(trim((string)($_POST['slug'] ?? '')));
            if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || mb_strlen($slug) > 191) {
                $errors['slug'] = 'Use lowercase letters, numbers and hyphens only.';
            } elseif (!partner_admin_slug_available($pdo, $slug, $id)) {
                $errors['slug'] = 'That slug is already in use by another provider.';
            }
            $clean['slug'] = $slug;

            // --- emirate (nullable; validated if set) ---
            $em = (int)($_POST['emirate_id'] ?? 0);
            if ($em > 0) {
                $s = $pdo->prepare('SELECT 1 FROM emirates WHERE id = :id');
                $s->execute([':id' => $em]);
                $clean['emirate_id'] = $s->fetchColumn() !== false ? $em : null;
            } else { $clean['emirate_id'] = null; }

            // --- scalars ---
            $clean['city_text']    = $plain('city_text', 150) ?: null;
            $clean['contact_name'] = $plain('contact_name', 255) ?: null;
            $clean['phone']        = $plain('phone', 50) ?: null;
            $clean['website']      = $plain('website', 255) ?: null;

            // --- email (nullable; validated if present) ---
            $email = trim((string)($_POST['email'] ?? ''));
            if ($email === '') {
                $clean['email'] = null;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                $errors['email'] = 'Enter a valid email address.';
            } else {
                $clean['email'] = $email;
            }

            $clean['is_featured'] = !empty($_POST['is_featured']) ? 1 : 0;
            $clean['is_verified'] = !empty($_POST['is_verified']) ? 1 : 0;

            $st = trim((string)($_POST['status'] ?? ''));
            $clean['status'] = isset(partner_admin_statuses()[$st]) ? $st : $partner['status'];

            // --- rich-text (sanitized) ---
            foreach (partner_admin_richtext_fields() as $rf) {
                $clean[$rf] = html_sanitize($_POST[$rf] ?? null);   // null if empty
            }

            if (!$errors) {
                // Diff for the audit trail (only changed columns).
                $changedOld = $changedNew = [];
                foreach ($clean as $col => $val) {
                    $before = $partner[$col] ?? null;
                    if ((string)$before !== (string)$val) {
                        $changedOld[$col] = $before;
                        $changedNew[$col] = $val;
                    }
                }
                try {
                    $sets = [];
                    foreach (array_keys($clean) as $col) { $sets[] = "$col = :$col"; }
                    $upd = $pdo->prepare('UPDATE partners SET ' . implode(', ', $sets) . ' WHERE id = :id');
                    foreach ($clean as $col => $val) { $upd->bindValue(':' . $col, $val); }
                    $upd->bindValue(':id', $id, PDO::PARAM_INT);
                    $upd->execute();

                    if ($changedNew) {
                        audit_log($pdo, (int)($me['id'] ?? 0) ?: null, 'update', 'partner', $id, $changedOld, $changedNew);
                    }
                    $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Provider saved.'];
                    redirect('admin/partners/edit?id=' . $id);
                } catch (Throwable $e) {
                    error_log('partner save failed (id=' . $id . '): ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong saving the provider. Please try again.';
                }
            }
        }
    }

    $emirates = venue_emirates($pdo);

    $admin_active       = 'partners';
    $page_title         = 'Edit provider — Admin';
    $admin_page_title   = 'Edit provider';
    $admin_content_view = __DIR__ . '/partner-edit.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ 404 ====================================== */
http_response_code(404);
$admin_active = 'partners'; $page_title = 'Not found — Admin';
$admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
$admin_content_view = __DIR__ . '/placeholder-content.php';
require __DIR__ . '/layout.php';

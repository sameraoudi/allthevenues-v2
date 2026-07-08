<?php
declare(strict_types=1);

/**
 * Admin user & role management (U4). Already gated ADMIN-ONLY by dispatch
 * (auth_require_role(['admin'])). Handles: list, new (GET/POST), edit (GET/POST).
 * Expects $pdo and $sub ('users...') in scope.
 *
 * Safety: never demote/disable the last active admin; a user can't change their
 * own role or disable themselves; password hashes are never rendered; errors are
 * generic (detail to error_log). CSRF + audit_log on every write.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/users_admin.php';
require_once __DIR__ . '/../../lib/password_token.php';   // #3 U-P9a invite tokens

$me   = auth_current_user();
$meId = (int)($me['id'] ?? 0);
$rest = trim(substr((string)$sub, strlen('users')), '/');   // '' | 'new' | 'edit' | 'resend'

/* ==================== RESEND INVITE (#3 U-P9a) ========================== */
if ($rest === 'resend') {
    $id   = (int)($_POST['id'] ?? 0);
    $user = $id > 0 ? user_admin_get($pdo, $id) : null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate() || $user === null) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Could not resend the invite.'];
        redirect($user ? 'admin/users/edit?id=' . $id : 'admin/users');
    }
    // Only offered while the password is not yet set OR the invite has expired.
    $pwStatus = password_status_for_user($user);
    $inStatus = invite_status_for_user($pdo, $id);
    if ((string)$user['role'] !== 'partner' || !($pwStatus === 'not_set' || $inStatus === 'expired')) {
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'A new invite isn’t needed for this account.'];
        redirect('admin/users/edit?id=' . $id);
    }
    try {
        $tok = pt_create($pdo, $id, 'invite', $meId ?: null, (string)$user['email']);
        $providerName = '';
        if (!empty($user['partner_id'])) {
            $ps = $pdo->prepare('SELECT org_name FROM partners WHERE id = :id');
            $ps->execute([':id' => (int)$user['partner_id']]);
            $providerName = (string)($ps->fetchColumn() ?: '');
        }
        send_invite_email($user, $tok['raw'], $providerName);
        audit_log($pdo, $meId ?: null, 'user.invite_resend', 'user', $id, null, ['email' => $user['email']]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Invite re-sent to ' . $user['email'] . '.'];
    } catch (Throwable $e) {
        error_log('invite resend failed (user=' . $id . '): ' . $e->getMessage());
        $_SESSION['admin_flash'] = ['type' => 'error', 'msg' => 'Could not resend the invite. Please try again.'];
    }
    redirect('admin/users/edit?id=' . $id);
}

/* ============ Provider contacts (Contacts-A "View contacts" JSON) ======== */
if ($rest === 'provider-contacts') {
    header('Content-Type: application/json; charset=utf-8');
    // Admin-gated by dispatch; add a same-origin defence (CSP is already 'self').
    $sfs = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($sfs !== '' && $sfs !== 'same-origin') {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        return;
    }
    require_once __DIR__ . '/../../lib/contact_sync.php';
    $pid = (int)($_GET['provider_id'] ?? 0);
    if ($pid <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'bad_request']);
        return;
    }
    echo json_encode(contact_provider_summary($pdo, $pid), JSON_UNESCAPED_UNICODE);
    return;
}

/* ============================ LIST ====================================== */
if ($rest === '') {
    $rows  = user_admin_list($pdo);
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    // One-time temp-password reveal (after create / reset).
    $tempPw = $_SESSION['admin_user_temppw'] ?? null;
    unset($_SESSION['admin_user_temppw']);

    $admin_active       = 'users';
    $page_title         = 'Users — Admin';
    $admin_page_title   = 'Users & roles';
    $admin_content_view = __DIR__ . '/users-list.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ====================== NEW / EDIT (shared form) ======================== */
if ($rest === 'new' || $rest === 'edit') {
    $isNew = ($rest === 'new');
    $id    = $isNew ? 0 : (int)($_POST['id'] ?? $_GET['id'] ?? 0);

    $user = null;
    if (!$isNew) {
        $user = $id > 0 ? user_admin_get($pdo, $id) : null;
        if ($user === null) {
            http_response_code(404);
            $admin_active = 'users'; $page_title = 'Not found — Admin';
            $admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
            $admin_content_view = __DIR__ . '/placeholder-content.php';
            require __DIR__ . '/layout.php';
            return;
        }
    }

    $errors = [];
    $old    = $isNew
        ? ['name' => '', 'email' => '', 'role' => 'editor', 'status' => 'active']
        : $user;

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $old = array_merge($old, $_POST);

        if (!csrf_validate()) {
            $errors['_form'] = 'Your session expired. Please review and save again.';
        } else {
            // --- name ---
            $name = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 255);
            if ($name === '') { $errors['name'] = 'Name is required.'; }

            // --- email ---
            $email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 255);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email is required.';
            } elseif (!user_email_available($pdo, $email, $id)) {
                $errors['email'] = 'That email is already in use.';
            }

            // --- role / status (whitelisted) ---
            $role = (string)($_POST['role'] ?? '');
            if (!isset(user_admin_roles()[$role])) { $errors['role'] = 'Choose a role.'; }

            $status = (string)($_POST['status'] ?? '');
            if (!isset(user_admin_statuses()[$status])) { $errors['status'] = 'Choose a status.'; }

            // --- provider (partner accounts only; forced NULL for staff) ---
            $partnerId = null;
            if ($role === 'partner') {
                $partnerId = (int)($_POST['partner_id'] ?? 0);
                if (!user_admin_provider_is_approved($pdo, $partnerId)) {
                    $errors['partner_id'] = 'Choose an approved provider for this account.';
                    $partnerId = null;
                }
            }

            // --- self / last-admin safety (edit only) ---
            if (!$isNew && !$errors) {
                if ($id === $meId && $role !== (string)$user['role']) {
                    $errors['role'] = 'You can’t change your own role.';
                }
                if ($id === $meId && $status !== 'active') {
                    $errors['status'] = 'You can’t disable your own account.';
                }
                $wasActiveAdmin = ((string)$user['role'] === 'admin' && (string)$user['status'] === 'active');
                $staysActiveAdmin = ($role === 'admin' && $status === 'active');
                if ($wasActiveAdmin && !$staysActiveAdmin
                    && user_count_active_admins($pdo, $id) === 0) {
                    $errors['_form'] = 'This is the last active administrator — promote another admin first.';
                }
            }

            // --- password (create must set one; edit optional) ---
            $genPassword = !empty($_POST['gen_password']);
            $rawPassword = (string)($_POST['password'] ?? '');
            $newHash     = null;
            $tempPlain   = null;
            if (!$errors) {
                if ($role === 'partner') {
                    // Partner accounts set their own password via the emailed link —
                    // an empty hash is unusable (can't pass password_verify).
                    if ($isNew) { $newHash = ''; }
                } elseif ($genPassword || ($isNew && $rawPassword === '')) {
                    $tempPlain = user_generate_temp_password();
                    $newHash   = password_hash($tempPlain, PASSWORD_BCRYPT);
                } elseif ($rawPassword !== '') {
                    if (mb_strlen($rawPassword) < 10) {
                        $errors['password'] = 'Use at least 10 characters (or leave blank to auto-generate).';
                    } else {
                        $newHash = password_hash($rawPassword, PASSWORD_BCRYPT);
                    }
                }
            }

            if (!$errors) {
                try {
                    if ($isNew) {
                        $ins = $pdo->prepare(
                            'INSERT INTO users (name, email, password_hash, role, status, partner_id)
                             VALUES (:name, :email, :hash, :role, :status, :pid)'
                        );
                        $ins->execute([
                            ':name' => $name, ':email' => $email, ':hash' => $newHash,
                            ':role' => $role, ':status' => $status, ':pid' => $partnerId,
                        ]);
                        $newId = (int)$pdo->lastInsertId();
                        audit_log($pdo, $meId ?: null, 'user.create', 'user', $newId,
                            null, ['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status, 'partner_id' => $partnerId]);

                        if ($role === 'partner') {
                            // Issue the one-time invite + email the set-password link.
                            $providerName = '';
                            if ($partnerId) {
                                $ps = $pdo->prepare('SELECT org_name FROM partners WHERE id = :id');
                                $ps->execute([':id' => $partnerId]);
                                $providerName = (string)($ps->fetchColumn() ?: '');
                            }
                            try {
                                $tok = pt_create($pdo, $newId, 'invite', $meId ?: null, $email);
                                send_invite_email(['id' => $newId, 'name' => $name, 'email' => $email], $tok['raw'], $providerName);
                            } catch (Throwable $e) {
                                error_log('invite send failed on create (user=' . $newId . '): ' . $e->getMessage());
                            }
                            // Contacts-A A4 — the new partner user becomes the provider's contact.
                            // Server-enforced: role=partner + provider present (both true here).
                            // No provider contact → always set (+ fill contactless venues);
                            // provider HAS a contact → overwrite provider + all venues ONLY when ticked.
                            if ($partnerId) {
                                require_once __DIR__ . '/../../lib/contact_sync.php';
                                $providerHasContact = contact_has(_contact_provider_row($pdo, $partnerId) ?? []);
                                $overwrite = ((string)($_POST['contact_overwrite'] ?? '') === '1');
                                if (!$providerHasContact) {
                                    contact_set_from_user($pdo, $partnerId, $name, $email, false, $meId ?: null);
                                } elseif ($overwrite) {
                                    contact_set_from_user($pdo, $partnerId, $name, $email, true, $meId ?: null);
                                }
                            }
                            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'Provider account created — set-up invite emailed to ' . $email . '.'];
                        } else {
                            if ($tempPlain !== null) {
                                $_SESSION['admin_user_temppw'] = ['email' => $email, 'password' => $tempPlain];
                            }
                            $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'User created.'];
                        }
                    } else {
                        // Diff (audit) — password recorded as "reset", never the value.
                        $changedOld = $changedNew = [];
                        foreach (['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status,
                                  'partner_id' => $partnerId] as $col => $val) {
                            if ((string)($user[$col] ?? '') !== (string)($val ?? '')) {
                                $changedOld[$col] = $user[$col] ?? null;
                                $changedNew[$col] = $val;
                            }
                        }
                        $sql = 'UPDATE users SET name = :name, email = :email, role = :role, status = :status, partner_id = :pid';
                        $params = [':name' => $name, ':email' => $email, ':role' => $role, ':status' => $status, ':pid' => $partnerId, ':id' => $id];
                        if ($newHash !== null) {
                            $sql .= ', password_hash = :hash';
                            $params[':hash'] = $newHash;
                            $changedNew['password'] = 'reset';
                        }
                        $sql .= ' WHERE id = :id';
                        $pdo->prepare($sql)->execute($params);

                        if ($changedNew) {
                            audit_log($pdo, $meId ?: null, 'user.update', 'user', $id, $changedOld, $changedNew);
                        }
                        if ($tempPlain !== null) {
                            $_SESSION['admin_user_temppw'] = ['email' => $email, 'password' => $tempPlain];
                        }
                        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'User saved.'];
                    }
                    redirect('admin/users');
                } catch (Throwable $e) {
                    error_log('user save failed (id=' . $id . '): ' . $e->getMessage());
                    $errors['_form'] = 'Something went wrong saving the user. Please try again.';
                }
            }
        }
    }

    // #3 U-P9a — partner-account extras for the form/detail view.
    $providerOptions = user_admin_provider_options($pdo);
    $inviteStatus = $passwordStatus = null;
    $inviteLatest = null;
    if (!$isNew && (string)($user['role'] ?? '') === 'partner') {
        $inviteStatus   = invite_status_for_user($pdo, $id);
        $passwordStatus = password_status_for_user($user);
        $inviteLatest   = invite_latest_for_user($pdo, $id);
    }
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    $admin_active       = 'users';
    $page_title         = ($isNew ? 'Add user' : 'Edit user') . ' — Admin';
    $admin_page_title   = $isNew ? 'Add user' : 'Edit user';
    $admin_content_view = __DIR__ . '/user-edit.php';
    require __DIR__ . '/layout.php';
    return;
}

/* ============================ 404 ====================================== */
http_response_code(404);
$admin_active = 'users'; $page_title = 'Not found — Admin';
$admin_page_title = 'Not found'; $admin_notfound = true; $sectionTitle = 'Not found';
$admin_content_view = __DIR__ . '/placeholder-content.php';
require __DIR__ . '/layout.php';

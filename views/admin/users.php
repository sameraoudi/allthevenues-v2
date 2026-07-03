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

$me   = auth_current_user();
$meId = (int)($me['id'] ?? 0);
$rest = trim(substr((string)$sub, strlen('users')), '/');   // '' | 'new' | 'edit'

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
                if ($genPassword || ($isNew && $rawPassword === '')) {
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
                            'INSERT INTO users (name, email, password_hash, role, status)
                             VALUES (:name, :email, :hash, :role, :status)'
                        );
                        $ins->execute([
                            ':name' => $name, ':email' => $email, ':hash' => $newHash,
                            ':role' => $role, ':status' => $status,
                        ]);
                        $newId = (int)$pdo->lastInsertId();
                        audit_log($pdo, $meId ?: null, 'user.create', 'user', $newId,
                            null, ['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status]);
                        if ($tempPlain !== null) {
                            $_SESSION['admin_user_temppw'] = ['email' => $email, 'password' => $tempPlain];
                        }
                        $_SESSION['admin_flash'] = ['type' => 'success', 'msg' => 'User created.'];
                    } else {
                        // Diff (audit) — password recorded as "reset", never the value.
                        $changedOld = $changedNew = [];
                        foreach (['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status] as $col => $val) {
                            if ((string)$user[$col] !== (string)$val) {
                                $changedOld[$col] = $user[$col];
                                $changedNew[$col] = $val;
                            }
                        }
                        $sql = 'UPDATE users SET name = :name, email = :email, role = :role, status = :status';
                        $params = [':name' => $name, ':email' => $email, ':role' => $role, ':status' => $status, ':id' => $id];
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

    $flash = null;
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

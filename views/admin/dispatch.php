<?php
declare(strict_types=1);

/**
 * Admin controller. Entry for every /admin* route (index.php routes here and
 * sets $adminSub to the path after /admin). Login is public; everything else
 * is gated fail-closed by auth_require_admin().
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ratelimit.php';

$pdo = db_pdo();
$sub = isset($adminSub) ? trim((string)$adminSub, '/') : '';

// Enquiries lead inbox (list / detail / actions / CSV) — staff. Handles its
// own dynamic sub-routes, so it sits before the exact-match switch.
if ($sub === 'enquiries' || strncmp($sub, 'enquiries/', 10) === 0) {
    auth_require_role(['admin', 'editor']);
    require __DIR__ . '/enquiries.php';
    return;
}

// Venue management (list / edit) — staff. Own dynamic sub-routes.
if ($sub === 'venues' || strncmp($sub, 'venues/', 7) === 0) {
    auth_require_role(['admin', 'editor']);
    require __DIR__ . '/venues.php';
    return;
}

// User & role management — ADMIN ONLY. Own dynamic sub-routes.
if ($sub === 'users' || strncmp($sub, 'users/', 6) === 0) {
    auth_require_role(['admin']);
    require __DIR__ . '/users.php';
    return;
}

// Provider (partner) management (list / edit) — staff. Own dynamic sub-routes.
if ($sub === 'partners' || strncmp($sub, 'partners/', 9) === 0) {
    auth_require_role(['admin', 'editor']);
    require __DIR__ . '/partners.php';
    return;
}

// Reporting (aggregations + per-section CSV) — staff. Own sub-routes/params.
if ($sub === 'reports' || strncmp($sub, 'reports/', 8) === 0) {
    auth_require_role(['admin', 'editor']);
    require __DIR__ . '/reports.php';
    return;
}

switch ($sub) {

    /* ---- Login (public) -------------------------------------------------- */
    case 'login':
        if (auth_is_logged_in()) {
            redirect('admin');
        }
        $loginError = null;
        $old        = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $old   = $_POST;
            $email = trim((string)($_POST['email'] ?? ''));
            if (!csrf_validate()) {
                $loginError = 'Your session expired. Please try again.';
            } elseif (!ratelimit_hit('admin_login_ip', client_ip(), 10, 900)
                   || !ratelimit_hit('admin_login_email', $email !== '' ? $email : 'none', 5, 900)) {
                $loginError = 'Too many attempts. Please try again in a few minutes.';
            } else {
                $user = auth_login_attempt($pdo, $email, (string)($_POST['password'] ?? ''));
                if ($user !== null) {
                    auth_login($user);
                    redirect('admin');
                }
                $loginError = 'Invalid email or password.';   // generic — no enumeration
            }
        }
        $page_title   = 'Admin sign in — All The Venues';
        $content_view = __DIR__ . '/../content/admin-login.php';
        require __DIR__ . '/../layout.php';
        break;

    /* ---- Logout ---------------------------------------------------------- */
    case 'logout':
        auth_logout();
        redirect('admin/login');
        break;

    /* ---- Dashboard (staff) ---------------------------------------------- */
    case '':
        auth_require_role(['admin', 'editor']);
        $stats = [
            'enquiries_total'  => (int)$pdo->query("SELECT COUNT(*) FROM enquiries")->fetchColumn(),
            'enquiries_new'    => (int)$pdo->query("SELECT COUNT(*) FROM enquiries WHERE status='new'")->fetchColumn(),
            'venues_published' => (int)$pdo->query("SELECT COUNT(*) FROM venues WHERE status='published'")->fetchColumn(),
            'partners_total'   => (int)$pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn(),
        ];
        $admin_active       = 'dashboard';
        $page_title         = 'Dashboard — Admin';
        $admin_page_title   = 'Dashboard';
        $admin_content_view = __DIR__ . '/dashboard-content.php';
        require __DIR__ . '/layout.php';
        break;

    /* ---- Settings / availability — ADMIN ONLY placeholder --------------- */
    case 'settings':
        auth_require_role(['admin']);
        $admin_active       = 'settings';
        $sectionTitle       = 'Settings';
        $page_title         = 'Settings — Admin';
        $admin_page_title   = 'Settings';
        $admin_notfound     = false;
        $admin_content_view = __DIR__ . '/placeholder-content.php';
        require __DIR__ . '/layout.php';
        break;

    /* ---- Unknown /admin/* — gate first, then 404 ------------------------ */
    default:
        auth_require_role(['admin', 'editor']);
        http_response_code(404);
        $admin_active       = '';
        $sectionTitle       = 'Not found';
        $page_title         = 'Not found — Admin';
        $admin_page_title   = 'Not found';
        $admin_notfound     = true;
        $admin_content_view = __DIR__ . '/placeholder-content.php';
        require __DIR__ . '/layout.php';
        break;
}

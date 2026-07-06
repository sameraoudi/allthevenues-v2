<?php
declare(strict_types=1);

/**
 * #3 U-P2 — Provider portal controller. Reached only when PORTAL_ENABLED is true
 * (index.php gates the whole /portal branch on the flag). Own login/session/
 * logout for role='partner' users — fully separate from staff (/admin) auth.
 * Every portal page is noindex. Expects: string $portalSub (path after /portal).
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ratelimit.php';
require_once __DIR__ . '/../../lib/portal.php';

$pdo = db_pdo();
$sub = isset($portalSub) ? trim((string)$portalSub, '/') : '';

$robots = 'noindex, nofollow';   // the portal is never indexed

// My Venues: /portal/venues and /portal/venues/{id} — partner-scoped, gated.
if ($sub === 'venues' || strncmp($sub, 'venues/', 7) === 0) {
    auth_require_partner();
    $partnerId = (int)(auth_user()['partner_id']);
    if ($sub === 'venues/new') {
        require __DIR__ . '/venue-new.php';
        return;
    }
    if (preg_match('#^venues/(\d+)/edit$#', $sub, $me)) {
        $vid = (int)$me[1];
        require __DIR__ . '/venue-edit.php';
        return;
    }
    if (preg_match('#^venues/(\d+)/request(?:/(withdraw))?$#', $sub, $mr)) {
        $vid           = (int)$mr[1];
        $requestAction = $mr[2] ?? '';   // '' = form/submit, 'withdraw' = withdraw
        require __DIR__ . '/venue-request.php';
        return;
    }
    if (preg_match('#^venues/(\d+)$#', $sub, $mm)) {
        $venue = portal_venue_for_partner($pdo, (int)$mm[1], $partnerId);
        if ($venue === null) {
            http_response_code(404);
            $page_title          = 'Not found — Provider Portal';
            $portal_content_view = __DIR__ . '/../content/404_content.php';
            require __DIR__ . '/layout.php';
            return;
        }
        $vid       = (int)$venue['id'];
        $images    = venue_images($pdo, $vid);
        $layouts   = venue_layout_capacity($pdo, $vid);
        $eventTags = portal_venue_event_types($pdo, $vid);
        $pending   = portal_pending_edit_request($pdo, $vid, $partnerId);
        $flash     = $_SESSION['portal_flash'] ?? null;
        unset($_SESSION['portal_flash']);
        $page_title          = (string)$venue['name'] . ' — Provider Portal';
        $portal_active       = 'venues';
        $portal_content_view = __DIR__ . '/venue.php';
        require __DIR__ . '/layout.php';
        return;
    }
    redirect('portal');   // /portal/venues with no id → dashboard
    return;
}

switch ($sub) {

    /* ---- Login (public) -------------------------------------------------- */
    case 'login':
        if (auth_role() === 'partner') {
            redirect('portal');
        }
        $loginError = null;
        $old        = [];
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $old   = $_POST;
            $email = trim((string)($_POST['email'] ?? ''));
            if (!csrf_validate()) {
                $loginError = 'Your session expired. Please try again.';
            } elseif (!ratelimit_hit('portal_login_ip', client_ip(), 10, 900)
                   || !ratelimit_hit('portal_login_email', $email !== '' ? $email : 'none', 5, 900)) {
                $loginError = 'Too many attempts. Please try again in a few minutes.';
            } else {
                $user = auth_partner_login_attempt($pdo, $email, (string)($_POST['password'] ?? ''));
                if ($user !== null) {
                    auth_login($user);
                    redirect('portal');
                }
                $loginError = 'Invalid email or password.';   // generic — no enumeration
            }
        }
        $page_title   = 'Provider sign in — All The Venues';
        $content_view = __DIR__ . '/login-content.php';
        require __DIR__ . '/../layout.php';
        break;

    /* ---- Logout ---------------------------------------------------------- */
    case 'logout':
        auth_logout();
        redirect('portal/login');
        break;

    /* ---- Portal landing → "My venues" dashboard (gated) ------------------ */
    case '':
        auth_require_partner();
        $myVenues            = portal_my_venues($pdo, (int)(auth_user()['partner_id']));
        $page_title          = 'My Venues — Provider Portal';
        $portal_active       = 'venues';
        $portal_content_view = __DIR__ . '/dashboard.php';
        require __DIR__ . '/layout.php';
        break;

    /* ---- Unknown /portal/* — gate first, then branded not-found ---------- */
    default:
        auth_require_partner();
        http_response_code(404);
        $page_title          = 'Not found — Provider Portal';
        $portal_content_view = __DIR__ . '/../content/404_content.php';
        require __DIR__ . '/layout.php';
        break;
}

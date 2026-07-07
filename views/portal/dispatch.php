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
require_once __DIR__ . '/../../lib/turnstile.php';   // #3 U-P9c — anti-bot on portal login
require_once __DIR__ . '/../../lib/portal.php';

$pdo = db_pdo();
$sub = isset($portalSub) ? trim((string)$portalSub, '/') : '';

$robots = 'noindex, nofollow';   // the portal is never indexed

// My Venues: /portal/venues and /portal/venues/{id} — partner-scoped, gated.
if ($sub === 'venues' || strncmp($sub, 'venues/', 7) === 0) {
    auth_require_partner();
    $partnerId = (int)(auth_user()['partner_id']);
    // PU-A1 — /portal/venues is now the My Venues list (dashboard is /portal).
    if ($sub === 'venues') {
        $myVenues            = portal_my_venues($pdo, $partnerId);
        $pendingCrs          = portal_owned_pending_crs($pdo, $partnerId);
        $page_title          = 'My Venues — Partner Portal';
        $portal_active       = 'venues';
        $portal_content_view = __DIR__ . '/venues-list.php';
        require __DIR__ . '/layout.php';
        return;
    }
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
    if (preg_match('#^venues/(\d+)/images$#', $sub, $mimg)) {
        $vid = (int)$mimg[1];
        require __DIR__ . '/venue-images.php';
        return;
    }
    if (preg_match('#^venues/(\d+)/submit$#', $sub, $msub)) {
        $vid = (int)$msub[1];
        require __DIR__ . '/venue-submit.php';
        return;
    }
    if (preg_match('#^venues/(\d+)/delete$#', $sub, $mdel)) {
        $vid = (int)$mdel[1];
        require __DIR__ . '/venue-delete.php';   // draft-only, owner-scoped (POST + CSRF)
        return;
    }
    if (preg_match('#^venues/(\d+)/withdraw$#', $sub, $mwd)) {
        $vid = (int)$mwd[1];
        require __DIR__ . '/venue-withdraw.php';  // under-review → draft, owner-scoped (POST + CSRF)
        return;
    }
    if (preg_match('#^venues/(\d+)/delist(?:/(withdraw))?$#', $sub, $mdl)) {
        $vid          = (int)$mdl[1];
        $delistAction = $mdl[2] ?? '';   // '' = form/request, 'withdraw' = withdraw request
        require __DIR__ . '/venue-delist.php';    // published → request delist; owner-scoped
        return;
    }
    if (preg_match('#^venues/(\d+)/relist$#', $sub, $mrl)) {
        $vid = (int)$mrl[1];
        require __DIR__ . '/venue-relist.php';     // delisted → published, self-serve (POST + CSRF)
        return;
    }
    if (preg_match('#^venues/(\d+)$#', $sub, $mm)) {
        $venue = portal_venue_for_partner($pdo, (int)$mm[1], $partnerId);
        if ($venue === null) {
            http_response_code(404);
            $page_title          = 'Not found — Partner Portal';
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
        $page_title          = (string)$venue['name'] . ' — Partner Portal';
        $portal_active       = 'venues';
        $portal_content_view = __DIR__ . '/venue.php';
        require __DIR__ . '/layout.php';
        return;
    }
    redirect('portal');   // /portal/venues with no id → dashboard
    return;
}

// Claim an existing venue (#3 U-P8a) — targets venues NOT owned, so it is a
// separate gated branch (never routed through the owned-venue id guard).
if ($sub === 'claim' || strncmp($sub, 'claim/', 6) === 0) {
    auth_require_partner();
    $partnerId      = (int)(auth_user()['partner_id']);
    $claimTarget    = 0;
    $claimAction    = '';
    $claimRequestId = 0;
    if (preg_match('#^claim/(\d+)/(withdraw|proof)$#', $sub, $mc)) {
        $claimRequestId = (int)$mc[1];
        $claimAction    = $mc[2];
    } elseif (preg_match('#^claim/(\d+)$#', $sub, $mc)) {
        $claimTarget = (int)$mc[1];
    }
    require __DIR__ . '/claim.php';
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
            } elseif (!turnstile_verify((string)($_POST['cf-turnstile-response'] ?? ''), client_ip())) {
                $loginError = 'Please complete the verification and try again.';   // fail closed
            } else {
                $user = auth_partner_login_attempt($pdo, $email, (string)($_POST['password'] ?? ''));
                if ($user !== null) {
                    auth_login($user);
                    redirect('portal');
                }
                $loginError = 'Invalid email or password.';   // generic — no enumeration
            }
        }
        $page_title   = 'Partner sign in — All The Venues';
        $content_view = __DIR__ . '/login-content.php';
        require __DIR__ . '/../layout.php';
        break;

    /* ---- Logout ---------------------------------------------------------- */
    case 'logout':
        auth_logout();
        redirect('portal/login');
        break;

    /* ---- Portal landing → dashboard (gated) ------------------------------ */
    case '':
        auth_require_partner();
        $me                  = auth_user();
        $partnerId           = (int)($me['partner_id'] ?? 0);
        $counts              = portal_dashboard_counts($pdo, $partnerId);
        $nextSteps           = portal_dashboard_next_steps($pdo, $partnerId);
        $recent              = portal_dashboard_recent($pdo, $partnerId);
        $page_title          = 'Dashboard — Partner Portal';
        $portal_active       = 'dashboard';
        $portal_content_view = __DIR__ . '/dashboard.php';
        require __DIR__ . '/layout.php';
        break;

    /* ---- Guide (#8) — static help in the shell (gated) ------------------- */
    case 'guide':
        auth_require_partner();
        $page_title          = 'Guide — Partner Portal';
        $portal_active       = 'guide';
        $portal_content_view = __DIR__ . '/guide.php';
        require __DIR__ . '/layout.php';
        break;

    /* ---- Account — read-only details + in-portal change password (gated) - */
    case 'account':
        auth_require_partner();
        require __DIR__ . '/account.php';
        break;

    /* ---- Unknown /portal/* — gate first, then branded not-found ---------- */
    default:
        auth_require_partner();
        http_response_code(404);
        $page_title          = 'Not found — Partner Portal';
        $portal_content_view = __DIR__ . '/../content/404_content.php';
        require __DIR__ . '/layout.php';
        break;
}

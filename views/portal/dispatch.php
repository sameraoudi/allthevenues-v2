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

$pdo = db_pdo();
$sub = isset($portalSub) ? trim((string)$portalSub, '/') : '';

$robots = 'noindex, nofollow';   // the portal is never indexed

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

    /* ---- Portal landing (gated; real dashboard = U-P3) ------------------- */
    case '':
        auth_require_partner();
        $page_title   = 'Provider Portal — All The Venues';
        $content_view = __DIR__ . '/placeholder.php';
        require __DIR__ . '/../layout.php';
        break;

    /* ---- Unknown /portal/* — gate first, then branded not-found ---------- */
    default:
        auth_require_partner();
        http_response_code(404);
        $page_title   = 'Not found — Provider Portal';
        $content_view = __DIR__ . '/../content/404_content.php';
        require __DIR__ . '/../layout.php';
        break;
}

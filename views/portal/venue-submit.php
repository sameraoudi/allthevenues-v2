<?php
declare(strict_types=1);

/**
 * #15 — Submit an owned DRAFT venue for review (POST-only). Reached via dispatch
 * (/portal/venues/{id}/submit), already auth_require_partner-gated. Owner-scoped +
 * gated on ≥1 photo inside portal_submit_venue_for_review(). Flashes the outcome
 * and redirects to the venue detail. Expects in scope: $pdo, int $vid, int $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/audit.php';
require_once __DIR__ . '/../../lib/portal.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !csrf_validate()) {
    $_SESSION['portal_flash'] = ['type' => 'error', 'msg' => 'Your session expired. Please try again.'];
    redirect('portal/venues/' . $vid);
}

$userId = (int)(auth_user()['id'] ?? 0);
try {
    $res = portal_submit_venue_for_review($pdo, $vid, $partnerId, $userId);
} catch (Throwable $e) {
    error_log('portal submit failed (venue=' . $vid . '): ' . $e->getMessage());
    $res = ['ok' => false, 'error' => 'Something went wrong. Please try again.'];
}

$_SESSION['portal_flash'] = !empty($res['ok'])
    ? ['type' => 'success', 'msg' => 'Venue submitted for review. It stays private until approved.']
    : ['type' => 'error', 'msg' => (string)($res['error'] ?? 'Could not submit this venue.')];
redirect('portal/venues/' . $vid);

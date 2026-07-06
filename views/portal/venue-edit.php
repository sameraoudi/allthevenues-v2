<?php
declare(strict_types=1);

/**
 * #3 U-P4 — Provider portal: LIVE edit of a venue's low-risk fields. Reached via
 * dispatch (/portal/venues/{id}/edit), already auth_require_partner-gated. The
 * SECURITY BOUNDARY is portal_venue_live_columns(): validation iterates the
 * allowlist, NEVER $_POST — so forged keys (name/slug/status/is_featured/
 * partner_id/contact_*) are ignored. Ownership is re-checked on GET and POST, and
 * the UPDATE re-scopes to the owner (WHERE id AND partner_id).
 * Expects in scope: $pdo, int $vid, int $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/sanitize.php';   // html_sanitize()
require_once __DIR__ . '/../../lib/audit.php';       // audit_log()
require_once __DIR__ . '/../../lib/portal.php';      // allowlist + owner-scoped read

$venue = portal_venue_for_partner($pdo, $vid, $partnerId);
if ($venue === null) {
    http_response_code(404);   // not owned / not found — existence never revealed
    $page_title          = 'Not found — Provider Portal';
    $portal_content_view = __DIR__ . '/../content/404_content.php';
    require __DIR__ . '/layout.php';
    return;
}

$errors = [];
$old    = $venue;   // form display values (POST overrides on error)

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = array_merge($venue, $_POST);

    if (!csrf_validate()) {
        $errors['_form'] = 'Your session expired. Please review and save again.';
    } else {
        $live  = portal_venue_live_columns();
        $rich  = venue_richtext_fields();
        $plain = static fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);

        // Build $clean ONLY from the allowlist — anything else in $_POST is ignored.
        $clean = [];
        foreach ($live as $col) {
            if (in_array($col, $rich, true)) {
                $clean[$col] = html_sanitize($_POST[$col] ?? null);   // null if empty
                continue;
            }
            switch ($col) {
                case 'area':      $clean[$col] = $plain('area', 150) ?: null; break;
                case 'address':   $clean[$col] = $plain('address', 255) ?: null; break;
                case 'video_url': $clean[$col] = $plain('video_url', 255) ?: null; break;
                case 'website':   $clean[$col] = $plain('website', 255) ?: null; break;
                case 'indoor_outdoor':
                    $io = trim((string)($_POST['indoor_outdoor'] ?? 'indoor'));
                    $clean[$col] = isset(venue_indoor_outdoor_options()[$io]) ? $io : 'indoor';
                    break;
                case 'capacity_min':  $clean[$col] = ($_POST['capacity_min'] ?? '') !== '' ? max(0, (int)$_POST['capacity_min']) : null; break;
                case 'capacity_max':  $clean[$col] = ($_POST['capacity_max'] ?? '') !== '' ? max(0, (int)$_POST['capacity_max']) : null; break;
                case 'minimum_spend': $clean[$col] = ($_POST['minimum_spend'] ?? '') !== '' ? max(0, (float)$_POST['minimum_spend']) : null; break;
                case 'pricing_level':
                    $pl = trim((string)($_POST['pricing_level'] ?? ''));
                    $clean[$col] = in_array($pl, venue_pricing_levels(), true) ? $pl : null;
                    break;
                case 'floor_area': $clean[$col] = ($_POST['floor_area'] ?? '') !== '' ? max(0, (float)$_POST['floor_area']) : null; break;
                case 'floor_area_unit':
                    $fau = trim((string)($_POST['floor_area_unit'] ?? ''));
                    $clean[$col] = in_array($fau, ['sqm', 'sqft'], true) ? $fau : null;
                    break;
                case 'map_embed':
                    $mapEmbed = trim((string)($_POST['map_embed'] ?? ''));
                    if ($mapEmbed === '') {
                        $clean[$col] = null;
                    } elseif (!preg_match('#^<iframe[^>]*\ssrc="https://www\.google\.com/maps/#i', $mapEmbed)) {
                        $errors['map_embed'] = 'Paste a valid Google Maps embed (the <iframe> code from Google Maps).';
                    } else {
                        $clean[$col] = $mapEmbed;
                    }
                    break;
            }
        }

        if (!$errors) {
            // Audit diff (only changed live columns).
            $changedOld = $changedNew = [];
            foreach ($clean as $col => $val) {
                $before = $venue[$col] ?? null;
                if ((string)$before !== (string)$val) { $changedOld[$col] = $before; $changedNew[$col] = $val; }
            }
            try {
                $sets = [];
                foreach (array_keys($clean) as $col) { $sets[] = "$col = :$col"; }
                // WHERE re-scopes to the owner — a stale/forged id can't write another provider's row.
                $upd = $pdo->prepare('UPDATE venues SET ' . implode(', ', $sets)
                    . ' WHERE id = :id AND partner_id = :pid');
                foreach ($clean as $col => $val) { $upd->bindValue(':' . $col, $val); }
                $upd->bindValue(':id', $vid, PDO::PARAM_INT);
                $upd->bindValue(':pid', $partnerId, PDO::PARAM_INT);
                $upd->execute();

                venue_layout_capacity_save($pdo, $vid, (array)($_POST['layout'] ?? []));

                if ($changedNew) {
                    audit_log($pdo, (int)(auth_user()['id'] ?? 0) ?: null, 'update', 'venue', $vid, $changedOld, $changedNew);
                }
                $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Saved.'];
                redirect('portal/venues/' . $vid);
            } catch (Throwable $e) {
                error_log('portal venue edit failed (venue=' . $vid . '): ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong saving your venue. Please try again.';
            }
        }
    }
}

// Layout prefill (layout_type => capacity).
$layoutValues = [];
foreach (venue_layout_capacity($pdo, $vid) as $lr) { $layoutValues[(string)$lr['layout_type']] = (int)$lr['capacity']; }

$page_title          = 'Edit venue — Provider Portal';
$portal_active       = 'venues';
$portal_content_view = __DIR__ . '/venue-edit-content.php';
require __DIR__ . '/layout.php';

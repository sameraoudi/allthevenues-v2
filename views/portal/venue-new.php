<?php
declare(strict_types=1);

/**
 * #3 U-P6a — Provider portal: submit a NEW venue. Reached via dispatch
 * (/portal/venues/new), already auth_require_partner-gated. On submit, creates a
 * status='pending' venue owned by this provider (management_source=
 * 'provider_created') plus a 'new_venue' change request for admin review (U-P6b).
 * The venue is never public until published. Validation iterates the allowlist
 * (portal_new_venue_fields()), NEVER $_POST — forged columns are ignored.
 * Expects in scope: $pdo, int $partnerId.
 */

require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/ratelimit.php';
require_once __DIR__ . '/../../lib/sanitize.php';   // html_sanitize()
require_once __DIR__ . '/../../lib/audit.php';       // audit_log()
require_once __DIR__ . '/../../lib/portal.php';      // allowlist + create

$userId = (int)(auth_user()['id'] ?? 0);

$errors       = [];
$layoutErrors = [];   // #19 — layout capacity > venue max
$old          = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old = $_POST;

    if (!csrf_validate()) {
        $errors['_form'] = 'Your session expired. Please review and submit again.';
    } elseif (!ratelimit_hit('portal_new_venue_' . $partnerId, (string)$partnerId, 10, 3600)) {
        $errors['_form'] = "You've submitted several venues recently; please try again later.";
    } else {
        $fields = portal_new_venue_fields();
        $rich   = venue_richtext_fields();
        $plain  = static fn(string $k, int $max) => mb_substr(trim(strip_tags((string)($_POST[$k] ?? ''))), 0, $max);

        // Build $clean ONLY from the allowlist — anything else in $_POST is ignored.
        $clean = [];
        foreach ($fields as $col) {
            if (in_array($col, $rich, true)) {
                $clean[$col] = html_sanitize($_POST[$col] ?? null);
                continue;
            }
            switch ($col) {
                case 'name':
                    $clean['name'] = $plain('name', 255);
                    if ($clean['name'] === '') { $errors['name'] = 'Name is required.'; }
                    break;
                case 'venue_type_id':
                    $vt = (int)($_POST['venue_type_id'] ?? 0);
                    if ($vt > 0) {
                        $s = $pdo->prepare('SELECT 1 FROM venue_types WHERE id = :id');
                        $s->execute([':id' => $vt]);
                        $clean['venue_type_id'] = $s->fetchColumn() !== false ? $vt : null;
                    } else { $clean['venue_type_id'] = null; }
                    break;
                case 'emirate_id':
                    $em = (int)($_POST['emirate_id'] ?? 0);
                    if ($em > 0) {
                        $s = $pdo->prepare('SELECT 1 FROM emirates WHERE id = :id');
                        $s->execute([':id' => $em]);
                        $clean['emirate_id'] = $s->fetchColumn() !== false ? $em : null;
                    } else { $clean['emirate_id'] = null; }
                    break;
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

        // #19 — no layout capacity may exceed the venue maximum.
        $layoutErrors = venue_layout_capacity_errors(
            (array)($_POST['layout'] ?? []),
            isset($clean['capacity_max']) ? (int)$clean['capacity_max'] : null
        );
        foreach ($layoutErrors as $t => $msg) { $errors['layout_' . $t] = $msg; }

        if (!$errors) {
            try {
                // #15 — create as a DRAFT (no review request yet); save layouts + event types.
                $vid = portal_create_new_venue($pdo, $partnerId, $userId, $clean);
                venue_layout_capacity_save($pdo, $vid, (array)($_POST['layout'] ?? []));   // #18
                portal_venue_event_types_save($pdo, $vid, $partnerId, (array)($_POST['event_types'] ?? []));
                $_SESSION['portal_flash'] = ['type' => 'success', 'msg' => 'Draft saved — add photos, then submit.'];
                redirect('portal/venues/' . $vid . '/images');   // Step 2
            } catch (Throwable $e) {
                error_log('portal new venue failed (partner=' . $partnerId . '): ' . $e->getMessage());
                $errors['_form'] = 'Something went wrong saving your venue. Please try again.';
            }
        }
    }
}

$page_title          = 'Submit a venue — Provider Portal';
$portal_active       = 'venues';
$portal_content_view = __DIR__ . '/venue-new-content.php';
require __DIR__ . '/layout.php';

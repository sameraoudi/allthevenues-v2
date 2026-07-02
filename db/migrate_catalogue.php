<?php
declare(strict_types=1);

// CLI only — never run over HTTP (this script truncates + rebuilds the
// catalogue). Defence in depth alongside db/.htaccess + .cpanel.yml exclude.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

/**
 * All The Venues — legacy CATALOGUE migration (U1b).
 *
 * Transforms the legacy `sameraou_atv` catalogue into the new `sameraou_atv2`
 * schema (docs/ATV-SCHEMA.md §6). Enquiries/leads are OUT OF SCOPE (U3).
 *
 * Runs ON THE SERVER (both DBs are localhost-only). Develop/test locally
 * against a loaded copy of the dump.
 *
 *   php db/migrate_catalogue.php
 *
 * IDEMPOTENT: truncates the target catalogue tables at the start, so every
 * run yields identical logical results. Re-runnable safely.
 *
 * Target MySQL 5.7 (cPanel host) — no MariaDB/8.0-only syntax.
 *
 * ----------------------------------------------------------------------------
 * SANITIZATION: rich-text fields are cleaned with a strict DOMDocument
 * allowlist (see html_sanitize()) — tags p/br/strong/em/ul/ol/li/a only,
 * <a href> restricted to http/https/mailto, everything else stripped
 * (script/style/iframe/on* removed). Chosen over HTML Purifier to stay
 * dependency-free / no-build-step (vanilla PHP). This closes the deferred
 * stored-XSS surface at import time.
 *
 * NOTE on `map_embed`: the legacy `map` column holds a Google Maps <iframe>.
 * It is stored RAW (NOT run through the prose allowlist, which would strip
 * it). It is never rendered in Phase 1 (tight CSP has no maps frame-src yet).
 * When the maps feature lands it MUST be rendered safely (extract src into a
 * sandboxed iframe under an expanded CSP), never echoed as-is.
 *
 * NOTE on `partners`: the finalized schema has no `org_type` column, so the
 * legacy provider `type` is folded into `partners.notes` as a labelled line.
 * `partner_group` is left NULL (legacy has no group FK).
 * ----------------------------------------------------------------------------
 */

/* ===========================================================================
 * CONFIG — DB connections.
 *
 * On the SERVER: fill LEGACY read creds below (Samer supplies them); the NEW
 * write creds are read from config/config.php (DB_HOST/DB_NAME/DB_USER/DB_PASS).
 *
 * For LOCAL testing: override any value via environment variables, e.g.
 *   ATV_LEGACY_DB_HOST / _PORT / _NAME / _USER / _PASS
 *   ATV_NEW_DB_HOST / _PORT / _NAME / _USER / _PASS
 * =========================================================================== */

$LEGACY = [
    'host' => getenv('ATV_LEGACY_DB_HOST') ?: 'localhost',
    'port' => (int)(getenv('ATV_LEGACY_DB_PORT') ?: 3306),
    'name' => getenv('ATV_LEGACY_DB_NAME') ?: 'sameraou_atv',
    'user' => getenv('ATV_LEGACY_DB_USER') ?: 'REPLACE_LEGACY_READ_USER',
    'pass' => getenv('ATV_LEGACY_DB_PASS') ?: 'REPLACE_LEGACY_READ_PASS',
];

// New DB: prefer env overrides (local test); else config/config.php constants.
$NEW = null;
if (getenv('ATV_NEW_DB_HOST')) {
    $NEW = [
        'host' => getenv('ATV_NEW_DB_HOST'),
        'port' => (int)(getenv('ATV_NEW_DB_PORT') ?: 3306),
        'name' => getenv('ATV_NEW_DB_NAME') ?: 'sameraou_atv2',
        'user' => getenv('ATV_NEW_DB_USER') ?: 'root',
        'pass' => getenv('ATV_NEW_DB_PASS') ?: '',
    ];
} else {
    $cfg = __DIR__ . '/../config/config.php';
    if (!is_file($cfg)) {
        fwrite(STDERR, "config/config.php not found; set ATV_NEW_DB_* env vars or create it.\n");
        exit(1);
    }
    require_once $cfg;
    $NEW = [
        'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
        'port' => 3306,
        'name' => defined('DB_NAME') ? DB_NAME : 'sameraou_atv2',
        'user' => defined('DB_USER') ? DB_USER : '',
        'pass' => defined('DB_PASS') ? DB_PASS : '',
    ];
}

/* ---- Path scheme for migrated media (served from 'self') ------------------ */
// file_path values written to the DB are relative to the app docroot.
// The server-side copy step (db/copy_media.sh) places the actual files here.
const PATH_VENUE_IMAGES = 'uploads/venues/images/';
const PATH_VENUE_DOCS   = 'uploads/venues/documents/';
const PATH_PARTNER_LOGO = 'uploads/partners/';

// Unusable password sentinel for migrated admins (fixed → idempotent).
// password_verify() against this always returns false; the admin-login unit
// must force a password reset for these accounts before they can sign in.
const UNUSABLE_PASSWORD = '!legacy-md5-must-reset!';

/* ===========================================================================
 * Helpers
 * =========================================================================== */

function pdo_connect(array $c): PDO
{
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

/** URL slug: lowercase, non-alnum → '-', collapse, trim. */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    // transliterate accented chars best-effort
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($t !== false) {
        $s = $t;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? $s : 'item';
}

/** Return a slug unique within $seen (adds -2, -3, …); records it. */
function unique_slug(string $base, array &$seen): string
{
    $slug = $base;
    $n = 2;
    while (isset($seen[$slug])) {
        $slug = $base . '-' . $n;
        $n++;
    }
    $seen[$slug] = true;
    return $slug;
}

/**
 * Strict HTML sanitizer — allowlist of prose tags only.
 * Allowed: p, br, strong, em, ul, ol, li, a[href(http|https|mailto)].
 * Everything else: tag stripped, text kept. script/style content removed.
 */
function html_sanitize(?string $html): ?string
{
    $html = trim((string)$html);
    if ($html === '') {
        return null;
    }

    $allowed = ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'a'];

    $dom = new DOMDocument('1.0', 'UTF-8');
    // Wrap so we always have a single root; suppress malformed-HTML warnings.
    $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
    libxml_use_internal_errors(true);
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $root = $dom->getElementsByTagName('div')->item(0);
    if ($root === null) {
        return null;
    }

    _sanitize_node($root, $allowed);

    // Serialize inner HTML of the wrapper div.
    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $dom->saveHTML($child);
    }
    $out = trim($out);
    // Collapse fully-empty results.
    if ($out === '' || strip_tags(str_replace('<br>', '', $out)) === '' && !str_contains($out, '<br')) {
        $stripped = trim(strip_tags($out));
        if ($stripped === '') {
            return null;
        }
    }
    return $out !== '' ? $out : null;
}

/** Recursively enforce the tag/attribute allowlist. */
function _sanitize_node(DOMNode $node, array $allowed): void
{
    // Iterate over a static copy — we mutate the tree while walking.
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child instanceof DOMElement) {
            $tag = strtolower($child->tagName);

            if ($tag === 'script' || $tag === 'style') {
                // Drop element AND its contents.
                $node->removeChild($child);
                continue;
            }

            // Recurse first so descendants are cleaned regardless of this tag.
            _sanitize_node($child, $allowed);

            if (!in_array($tag, $allowed, true)) {
                // Unwrap: replace element with its (already-sanitized) children.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            // Strip all attributes except a safe href on <a>.
            $keepHref = null;
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $href = trim($child->getAttribute('href'));
                if (preg_match('#^(https?:|mailto:)#i', $href)) {
                    $keepHref = $href;
                }
            }
            // Remove every attribute.
            while ($child->attributes->length > 0) {
                $child->removeAttribute($child->attributes->item(0)->nodeName);
            }
            if ($tag === 'a') {
                if ($keepHref !== null) {
                    $child->setAttribute('href', $keepHref);
                    $child->setAttribute('rel', 'noopener nofollow');
                } else {
                    // No safe href → unwrap the anchor.
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }
            }
        }
        // Text/other nodes are left as-is (escaped on serialize).
    }
}

/** Join non-empty pieces with a separator. */
function join_ne(array $parts, string $sep = "<br>\n"): ?string
{
    $parts = array_values(array_filter(array_map(
        static fn($p) => trim((string)$p),
        $parts
    ), static fn($p) => $p !== ''));
    return $parts ? implode($sep, $parts) : null;
}

function nn(?string $s): ?string   // null if empty
{
    $s = trim((string)$s);
    return $s === '' ? null : $s;
}

/** Derive a pricing_level budget label from a minimum spend (AED). */
function pricing_level(int $minSpend): string
{
    if ($minSpend <= 0)        return 'Price on request';
    if ($minSpend <= 15000)    return 'Budget-friendly';
    if ($minSpend <= 50000)    return 'Mid-range';
    if ($minSpend <= 150000)   return 'Premium';
    return 'Luxury';
}

/* ===========================================================================
 * Run
 * =========================================================================== */

$t0 = microtime(true);
echo "== ATV catalogue migration (U1b) ==\n";

try {
    $src = pdo_connect($LEGACY);
    $dst = pdo_connect($NEW);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: {$e->getMessage()}\n");
    exit(1);
}
echo "Connected: legacy={$LEGACY['name']}  new={$NEW['name']}\n";

/* ---- 0. Truncate target catalogue tables (idempotent re-run) ------------- */
$dst->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'venue_documents', 'venue_images', 'venue_event_types',
    'venue_layout_capacity', 'venues', 'partners', 'users',
] as $t) {
    $dst->exec("TRUNCATE TABLE `$t`");
}
$dst->exec('SET FOREIGN_KEY_CHECKS=1');
echo "Truncated target catalogue tables.\n";

/* ---- Load emirate name/slug → id map ------------------------------------- */
$emirateBySlug = [];
foreach ($dst->query('SELECT id, name, slug FROM emirates') as $r) {
    $emirateBySlug[$r['slug']] = (int)$r['id'];
}
$defaultEmirateId = $emirateBySlug['dubai'] ?? 1;
$mapEmirate = static function (?string $city) use ($emirateBySlug, $defaultEmirateId): int {
    $slug = slugify((string)$city);
    return $emirateBySlug[$slug] ?? $defaultEmirateId;
};

/* ---- Load venue_type name→id map + legacy-type mapping -------------------- */
$venueTypeBySlug = [];
foreach ($dst->query('SELECT id, slug FROM venue_types') as $r) {
    $venueTypeBySlug[$r['slug']] = (int)$r['id'];
}
$TYPE_MAP = [   // legacy `type` → new venue_types.slug (per docs/ATV-SCHEMA.md §6)
    'ballroom'     => 'hotel-ballroom',
    'restaurant'   => 'restaurant',
    'beach'        => 'beach-venue',
    'garden'       => 'garden-venue',
    'island'       => 'island-venue',
    'meeting room' => 'meeting-room',
    'yacht'        => 'yacht',
    'villa'        => 'villa',
    'warehouse'    => 'warehouse',
    'art gallery'  => 'unique-venue',
    'museum'       => 'unique-venue',
    'theater'      => 'unique-venue',
    'other'        => 'unique-venue',
];
$mapVenueType = static function (?string $type) use ($TYPE_MAP, $venueTypeBySlug): ?int {
    $key = strtolower(trim((string)$type));
    $slug = $TYPE_MAP[$key] ?? 'unique-venue';
    return $venueTypeBySlug[$slug] ?? $venueTypeBySlug['unique-venue'] ?? null;
};

$mapIndoorOutdoor = static function (?string $cat): string {
    $c = strtolower(trim((string)$cat));
    if ($c === '') return 'indoor';
    if (str_starts_with($c, 'indoor with outdoor')) return 'both';
    if ($c === 'outdoor') return 'outdoor';
    return 'indoor';
};

/* ===========================================================================
 * 1. partners ← providers
 * =========================================================================== */
$partnerIdByPid    = [];   // legacy pid → new partner id
$partnerIdByName   = [];   // lower(org_name) → new partner id  (for venue resolution)
$partnerSlugSeen   = [];

$insPartner = $dst->prepare(
    'INSERT INTO partners
        (slug, org_name, partner_group, contact_name, email, phone, website,
         emirate_id, city_text, about, logo_path, status, is_featured, notes, approved_at)
     VALUES
        (:slug, :org_name, NULL, :contact_name, :email, :phone, :website,
         :emirate_id, :city_text, :about, :logo_path, :status, :is_featured, :notes, :approved_at)'
);

$providers = $src->query('SELECT * FROM providers ORDER BY pid');
$nPartners = 0;
foreach ($providers as $p) {
    $slug   = unique_slug(slugify($p['name']), $partnerSlugSeen);
    $status = ((int)$p['status'] === 1) ? 'approved' : 'draft';

    // Fold legacy `type` into notes (schema has no org_type column).
    $noteParts = [];
    if (nn($p['type']))  { $noteParts[] = 'Legacy org type: ' . trim($p['type']); }
    if (nn($p['notes'])) { $noteParts[] = trim($p['notes']); }
    $notes = $noteParts ? implode("\n", $noteParts) : null;

    $logo = nn($p['photo']) ? PATH_PARTNER_LOGO . basename(trim($p['photo'])) : null;

    $insPartner->execute([
        ':slug'         => $slug,
        ':org_name'     => trim($p['name']),
        ':contact_name' => nn($p['contact']),
        ':email'        => nn($p['email']),
        ':phone'        => nn($p['phone']),
        ':website'      => nn($p['url']),
        ':emirate_id'   => $mapEmirate($p['city']),
        ':city_text'    => nn($p['city']),
        ':about'        => html_sanitize($p['about']),
        ':logo_path'    => $logo,
        ':status'       => $status,
        ':is_featured'  => ((int)$p['featured'] > 0) ? 1 : 0,
        ':notes'        => $notes,
        ':approved_at'  => ($status === 'approved') ? date('Y-m-d H:i:s', 0) : null,
    ]);
    $newId = (int)$dst->lastInsertId();
    $partnerIdByPid[(int)$p['pid']]          = $newId;
    $partnerIdByName[strtolower(trim($p['name']))] = $newId;
    $nPartners++;
}
echo "1. partners:  $nPartners\n";

/* ===========================================================================
 * 2. venues ← venues   (+ build legacy venue id → new id map)
 * =========================================================================== */
$venueIdMap    = [];   // legacy venue id → new venue id
$venueSlugSeen = [];
$unresolvedProviders = [];   // [legacy venue id, name, provider text]

$insVenue = $dst->prepare(
    'INSERT INTO venues
        (slug, partner_id, name, status, is_featured, is_verified,
         venue_type_id, indoor_outdoor, emirate_id, area, address, map_embed,
         description, best_for, facilities, food_beverage, av_support,
         restrictions, packages, special_offer, atv_special_offer, video_url,
         capacity_max, capacity_min, pricing_level, minimum_spend,
         atv_rating, atv_review, main_image, published_at)
     VALUES
        (:slug, :partner_id, :name, :status, :is_featured, :is_verified,
         :venue_type_id, :indoor_outdoor, :emirate_id, :area, :address, :map_embed,
         :description, :best_for, :facilities, :food_beverage, :av_support,
         :restrictions, :packages, :special_offer, :atv_special_offer, :video_url,
         :capacity_max, :capacity_min, :pricing_level, :minimum_spend,
         :atv_rating, :atv_review, :main_image, :published_at)'
);

$rows = $src->query('SELECT * FROM venues ORDER BY id');
$nVenues = 0;
foreach ($rows as $v) {
    $slug = unique_slug(slugify($v['name']), $venueSlugSeen);

    // Resolve provider TEXT → partner_id (case-insensitive on org_name).
    $partnerId = null;
    $provTxt = trim((string)$v['provider']);
    if ($provTxt !== '') {
        $partnerId = $partnerIdByName[strtolower($provTxt)] ?? null;
        if ($partnerId === null) {
            $unresolvedProviders[] = [(int)$v['id'], $v['name'], $provTxt];
        }
    }

    $isPublished = ((int)$v['status'] === 1);
    $minSpend    = (int)$v['minimum-spending'];
    $rating      = (int)$v['atv-rating'];

    $insVenue->execute([
        ':slug'           => $slug,
        ':partner_id'     => $partnerId,
        ':name'           => trim($v['name']),
        ':status'         => $isPublished ? 'published' : 'draft',
        ':is_featured'    => ((int)$v['mainpage'] > 0) ? 1 : 0,
        ':is_verified'    => 0,
        ':venue_type_id'  => $mapVenueType($v['type']),
        ':indoor_outdoor' => $mapIndoorOutdoor($v['category']),
        ':emirate_id'     => $mapEmirate($v['city']),
        ':area'           => null,
        ':address'        => nn($v['address']),
        ':map_embed'      => nn($v['map']),                       // RAW iframe; see header note
        ':description'    => html_sanitize($v['venue-theme'] !== '' ? $v['venue-theme'] : join_ne([$v['setting'], $v['style']])),
        ':best_for'       => html_sanitize($v['ideal-for']),
        ':facilities'     => html_sanitize($v['additional-elements']),
        ':food_beverage'  => html_sanitize(join_ne([$v['food'], $v['beverages']])),
        ':av_support'     => html_sanitize(join_ne([$v['audio-facility'], $v['video-facility'], $v['lighting']])),
        ':restrictions'   => html_sanitize($v['restrictions']),
        ':packages'       => html_sanitize($v['packages']),
        ':special_offer'  => html_sanitize(join_ne([$v['special-offer'], $v['atv-special-offer']])),
        ':atv_special_offer' => html_sanitize($v['atv-special-offer']),
        ':video_url'      => nn($v['video']),
        ':capacity_max'   => ((int)$v['capacity'] > 0) ? (int)$v['capacity'] : null,
        ':capacity_min'   => ((int)$v['minimum-guest'] > 0) ? (int)$v['minimum-guest'] : null,
        ':pricing_level'  => pricing_level($minSpend),
        ':minimum_spend'  => ($minSpend > 0) ? $minSpend : null,
        ':atv_rating'     => ($rating > 0) ? $rating : null,
        ':atv_review'     => html_sanitize($v['atv_review']),
        ':main_image'     => nn($v['main-photo']) ? PATH_VENUE_IMAGES . basename(trim($v['main-photo'])) : null,
        ':published_at'   => $isPublished ? (nn($v['date']) ?? null) : null,
    ]);
    $venueIdMap[(int)$v['id']] = (int)$dst->lastInsertId();
    $nVenues++;
}
echo "2. venues:    $nVenues  (partner resolved: " . ($nVenues - count($unresolvedProviders)) . ", unresolved: " . count($unresolvedProviders) . ")\n";

/* ===========================================================================
 * 3. venue_layout_capacity ← the 8 capacity-* columns (value > 0)
 * =========================================================================== */
$LAYOUTS = [   // legacy column → new ENUM label
    'capacity-banquet'    => 'Banquet',
    'capacity-reception'  => 'Reception',
    'capacity-theater'    => 'Theatre',
    'capacity-classroom'  => 'Classroom',
    'capacity-cabaret'    => 'Cabaret',
    'capacity-boardroom'  => 'Boardroom',
    'capacity-ushape'     => 'U-shape',
    'capacity-hshape'     => 'H-shape',
];
$insLayout = $dst->prepare(
    'INSERT INTO venue_layout_capacity (venue_id, layout_type, capacity)
     VALUES (:venue_id, :layout_type, :capacity)'
);
$rows = $src->query('SELECT * FROM venues ORDER BY id');
$nLayouts = 0;
foreach ($rows as $v) {
    $newVid = $venueIdMap[(int)$v['id']] ?? null;
    if ($newVid === null) continue;
    foreach ($LAYOUTS as $col => $label) {
        $cap = (int)($v[$col] ?? 0);
        if ($cap > 0) {
            $insLayout->execute([
                ':venue_id'    => $newVid,
                ':layout_type' => $label,
                ':capacity'    => $cap,
            ]);
            $nLayouts++;
        }
    }
}
echo "3. layout rows: $nLayouts\n";

/* ===========================================================================
 * 4. venue_images ← venue_images
 * =========================================================================== */
$insImg = $dst->prepare(
    'INSERT INTO venue_images
        (venue_id, file_path, thumb_path, alt_text, is_primary, sort_order, status)
     VALUES
        (:venue_id, :file_path, NULL, :alt_text, :is_primary, :sort_order, :status)'
);

// main-photo basename per legacy venue id (to pick the primary image).
$mainPhotoByVid = [];
foreach ($src->query('SELECT id, `main-photo` mp FROM venues') as $r) {
    $mainPhotoByVid[(int)$r['id']] = strtolower(basename(trim((string)$r['mp'])));
}

// Group images by legacy venue id, preserving legacy id order.
$imagesByVid = [];
foreach ($src->query('SELECT * FROM venue_images ORDER BY venue_id, id') as $img) {
    $imagesByVid[(int)$img['venue_id']][] = $img;
}

$nImages = 0;
foreach ($imagesByVid as $legacyVid => $imgs) {
    $newVid = $venueIdMap[$legacyVid] ?? null;
    if ($newVid === null) continue;   // orphan (none in this dataset)

    $mainBase = $mainPhotoByVid[$legacyVid] ?? '';
    // Determine which image is primary: match main-photo, else the first.
    $primaryIdx = 0;
    foreach ($imgs as $i => $img) {
        if ($mainBase !== '' && strtolower(basename(trim((string)$img['image']))) === $mainBase) {
            $primaryIdx = $i;
            break;
        }
    }

    $sort = 0;
    foreach ($imgs as $i => $img) {
        $file = nn($img['image']);
        if ($file === null) continue;
        $insImg->execute([
            ':venue_id'   => $newVid,
            ':file_path'  => PATH_VENUE_IMAGES . basename(trim($img['image'])),
            ':alt_text'   => nn($img['title']) ?? nn($img['message']),
            ':is_primary' => ($i === $primaryIdx) ? 1 : 0,
            ':sort_order' => $sort++,
            ':status'     => 'active',
        ]);
        $nImages++;
    }
}
echo "4. images:    $nImages\n";

/* ===========================================================================
 * 5. venue_documents ← venue_document (metadata only)
 * =========================================================================== */
$insDoc = $dst->prepare(
    'INSERT INTO venue_documents (venue_id, title, file_path)
     VALUES (:venue_id, :title, :file_path)'
);
$nDocs = 0;
foreach ($src->query('SELECT * FROM venue_document ORDER BY venue_id, id') as $d) {
    $newVid = $venueIdMap[(int)$d['venue_id']] ?? null;
    if ($newVid === null) continue;
    $file = nn($d['image']);
    if ($file === null) continue;
    $insDoc->execute([
        ':venue_id'  => $newVid,
        ':title'     => nn($d['title']),
        ':file_path' => PATH_VENUE_DOCS . basename(trim($d['image'])),
    ]);
    $nDocs++;
}
echo "5. documents: $nDocs\n";

/* ===========================================================================
 * 6. users ← admin  (role=admin; unusable password, must reset)
 * =========================================================================== */
$insUser = $dst->prepare(
    'INSERT INTO users (name, email, password_hash, role, status)
     VALUES (:name, :email, :password_hash, :role, :status)'
);
$nUsers = 0;
$seenEmail = [];
foreach ($src->query('SELECT * FROM admin ORDER BY id') as $a) {
    $email = nn($a['email']);
    if ($email === null) continue;                 // email is required + unique
    $key = strtolower($email);
    if (isset($seenEmail[$key])) continue;         // skip duplicate emails
    $seenEmail[$key] = true;
    $insUser->execute([
        ':name'          => nn($a['admin']) ?? $email,
        ':email'         => $email,
        ':password_hash' => UNUSABLE_PASSWORD,      // MD5 legacy NOT carried over
        ':role'          => 'admin',
        ':status'        => 'active',
    ]);
    $nUsers++;
}
echo "6. users:     $nUsers  (unusable password — must reset when admin-login lands)\n";

/* ---- Report -------------------------------------------------------------- */
echo "\n-- Unresolved provider links (venue → partner_id NULL) --\n";
if ($unresolvedProviders) {
    foreach ($unresolvedProviders as [$lid, $vname, $prov]) {
        echo "  legacy venue #$lid  \"$vname\"  provider=\"$prov\"\n";
    }
} else {
    echo "  (none)\n";
}

printf("\nDone in %.2fs.\n", microtime(true) - $t0);

<?php
declare(strict_types=1);

/**
 * Unit tests for the backfill mapping logic (db/_migrate_lib.php):
 *   - ml_normalize_website_url()  — URL validation/normalization
 *   - ml_map_best_for_to_event_slugs() — keyword → event_type mapping
 *
 *   php tests/migrate_lib_test.php
 *
 * No DB required (pure functions). Exits non-zero if any assertion fails.
 */

require_once __DIR__ . '/../db/_migrate_lib.php';

$pass = 0;
$fail = 0;

function check(string $label, $expected, $actual): void
{
    global $pass, $fail;
    $ok = $expected === $actual;
    if ($ok) {
        $pass++;
    } else {
        $fail++;
        fwrite(STDERR, sprintf(
            "  FAIL: %s\n        expected: %s\n        actual:   %s\n",
            $label, var_export($expected, true), var_export($actual, true)
        ));
    }
}

/* ============================ URL normalization ============================= */
echo "-- ml_normalize_website_url --\n";

$urlCases = [
    // raw                              => expected
    ['https://example.com',              'https://example.com'],
    ['http://example.com/path?x=1',      'http://example.com/path?x=1'],
    ['www.example.com',                  'https://www.example.com'],   // bare domain → https
    ['example.co.uk',                    'https://example.co.uk'],
    ['  https://Trimmed.com  ',          'https://Trimmed.com'],       // trims
    ['www.x.com (main site)',            'https://www.x.com'],         // first token
    ['HTTPS://Caps.COM',                 'HTTPS://Caps.COM'],          // scheme kept, still valid
    // junk / rejected
    ['',                                 null],
    ['   ',                              null],
    ['N/A',                              null],
    ['none',                             null],
    ['-',                                null],
    ['http://',                          null],
    ['info@example.com',                 null],   // email, not a website
    ['just some text',                   null],   // first token "just" has no dot
    ['ftp://example.com',                null],   // non-http scheme
    ['localhost',                        null],   // no dotted TLD
    ['http://nodot',                     null],   // host has no TLD
];
foreach ($urlCases as [$raw, $expected]) {
    check("normalize(" . var_export($raw, true) . ")", $expected, ml_normalize_website_url($raw));
}
// Over-length URL → rejected (column is VARCHAR(255)).
check('normalize(over-255)', null, ml_normalize_website_url('https://x.com/' . str_repeat('a', 260)));

/* ======================= best_for → event_type mapping ===================== */
echo "-- ml_map_best_for_to_event_slugs --\n";

$mapCases = [
    // best_for text                                        => expected slugs (in map order)
    ['Weddings',                                             ['wedding']],
    ['Weddings,Large business functions,Training',          ['wedding', 'corporate-event', 'training']],
    ['Small business functions,Team building activities',   ['corporate-event']],
    ['Weddings,Private parties',                            ['wedding', 'private-party']],
    ['Weddings,Large business functions,Product launch,Social Gathering',
                                                            ['wedding', 'corporate-event', 'product-launch', 'private-party']],
    ['Conference',                                          ['conference']],
    ['Meetings',                                            ['meeting']],
    ['Corporate events',                                    ['corporate-event']],
    ['Art exhibitions',                                     ['exhibition']],
    ['Yacht parties',                                       ['private-party', 'yacht-event']],
    ['Baby showers',                                        ['private-party']],
    ['Networking event',                                    ['networking-event']],
    ['Business networking & private parties',               ['corporate-event', 'private-party', 'networking-event']],
    // niche categories with no clean event type → untagged (empty)
    ['Music videos',                                        []],
    ['Car reviews, fashion shows',                          []],
    ['',                                                    []],
    ['<p>Weddings</p>',                                     ['wedding']],   // strips tags
];
foreach ($mapCases as [$text, $expected]) {
    check("map(" . var_export($text, true) . ")", $expected, ml_map_best_for_to_event_slugs($text));
}

/* ---- Print the documented mapping table (for the report) ------------------ */
echo "\n-- Documented keyword → event_type map --\n";
foreach (ml_event_type_keyword_map() as $kw => $slug) {
    printf("  %-18s → %s\n", "'" . $kw . "'", $slug);
}

/* ============================== Result ===================================== */
printf("\n%d passed, %d failed\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);

<?php
declare(strict_types=1);

/**
 * Curated inline-SVG icon set (no Font Awesome / CDN — keeps CSP self-only).
 * Simple, consistent line icons (24x24, currentColor) — a step toward a
 * future custom set. Use icon('name', 'css-class').
 */

/** Inner SVG markup for each icon (paths only; wrapped by icon()). */
function _icon_paths(string $name): ?string
{
    static $set = null;
    if ($set === null) {
        $set = [
            // UI
            'chevron-down' => '<path d="M6 9l6 6 6-6"/>',
            'arrow-right'  => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
            'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'plus'         => '<path d="M12 5v14M5 12h14"/>',
            'chart'        => '<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="5" width="3" height="13"/>',
            'download'     => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
            // Venue layout glyphs (simple, distinct hints — refine later).
            'layout-reception' => '<circle cx="6" cy="7" r="1.2"/><circle cx="12" cy="5" r="1.2"/><circle cx="18" cy="8" r="1.2"/><circle cx="8" cy="13" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="5" cy="18" r="1.2"/><circle cx="12" cy="18" r="1.2"/><circle cx="18" cy="17" r="1.2"/>',
            'layout-theatre'   => '<path d="M4 5h16"/><circle cx="7" cy="10" r="1"/><circle cx="12" cy="10" r="1"/><circle cx="17" cy="10" r="1"/><circle cx="7" cy="14" r="1"/><circle cx="12" cy="14" r="1"/><circle cx="17" cy="14" r="1"/><circle cx="7" cy="18" r="1"/><circle cx="12" cy="18" r="1"/><circle cx="17" cy="18" r="1"/>',
            'layout-banquet'   => '<circle cx="7" cy="7" r="3"/><circle cx="16.5" cy="8" r="3"/><circle cx="8.5" cy="16.5" r="3"/><circle cx="17" cy="16.5" r="3"/>',
            'layout-classroom' => '<path d="M4 8h6M14 8h6M4 13h6M14 13h6M4 18h6M14 18h6"/><circle cx="7" cy="5.5" r=".8"/><circle cx="17" cy="5.5" r=".8"/>',
            'layout-cabaret'   => '<path d="M4 10a3 3 0 0 1 6 0"/><path d="M14 10a3 3 0 0 1 6 0"/><path d="M9 18a3 3 0 0 1 6 0"/>',
            'layout-hshape'    => '<path d="M6 4v16M18 4v16M6 12h12"/>',
            'layout-ushape'    => '<path d="M6 4v9a6 6 0 0 0 12 0V4"/>',
            'layout-boardroom' => '<rect x="6" y="8" width="12" height="8" rx="2"/><circle cx="9" cy="5" r=".8"/><circle cx="15" cy="5" r=".8"/><circle cx="9" cy="19" r=".8"/><circle cx="15" cy="19" r=".8"/><circle cx="3.5" cy="12" r=".8"/><circle cx="20.5" cy="12" r=".8"/>',
            'area'             => '<path d="M15 3h6v6"/><path d="M9 21H3v-6"/><path d="M21 3l-7 7"/><path d="M3 21l7-7"/>',
            'heart'        => '<path d="M12 20S3.5 14.7 3.5 9.4C3.5 6.9 5.4 5 7.9 5c1.6 0 3 .8 4.1 2.1C13 5.8 14.4 5 16 5c2.5 0 4.4 1.9 4.4 4.4C20.4 14.7 12 20 12 20z"/>',
            'users'        => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.5a3 3 0 0 1 0 6"/><path d="M18.5 20a5.5 5.5 0 0 0-2.7-4.8"/>',
            // Trust
            'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
            'info-circle'  => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/>',
            'hand-heart'   => '<path d="M11.5 8.5l.5.5.5-.5a1.8 1.8 0 0 1 2.6 2.6L12 14.5 8.9 11.1a1.8 1.8 0 0 1 2.6-2.6z"/><path d="M3 13l3.5 3.5A4 4 0 0 0 9.3 18H15a2 2 0 0 0 0-4h-3.5"/>',
            // Event types
            'ring'         => '<circle cx="12" cy="14.5" r="5.5"/><path d="M9 9l3-4 3 4"/>',
            'briefcase'    => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5.5A2.5 2.5 0 0 1 10.5 3h3A2.5 2.5 0 0 1 16 5.5V7"/><path d="M3 12.5h18"/>',
            'glass-cheers' => '<path d="M12 4v9"/><path d="M8 21h8"/><path d="M12 13v8"/><path d="M6.5 4h11l-1 5a4.5 4.5 0 0 1-9 0z"/>',
            'rocket'       => '<path d="M12 3c3.5 1 6 3.5 7 7-1 3.5-3.5 6-7 7-1-3-2.9-4.9-5.9-5.9C7.1 8.6 9 4 12 3z"/><circle cx="13.5" cy="9.5" r="1.4"/><path d="M6 15c-1 1-1.2 3.8-1.2 3.8S7.6 18.6 8.5 17.6"/>',
            'tree'         => '<path d="M12 3l4.5 7h-2.8L18 16H6l4.3-6H7.5z"/><path d="M12 16v5"/>',
            'star'         => '<path d="M12 3.5l2.5 5.1 5.6.8-4 4 1 5.6L12 16.4 6.9 19l1-5.6-4-4 5.6-.8z"/>',
            'cake'         => '<path d="M4 21h16"/><path d="M5 21v-6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v6"/><path d="M4.5 16.5c1.6 0 1.6 1.4 3.2 1.4S9.3 16.5 12 16.5s1.6 1.4 3.2 1.4 1.6-1.4 3.2-1.4"/><path d="M12 8v3"/><path d="M12 5h.01"/>',
            'anchor'       => '<circle cx="12" cy="5" r="2"/><path d="M12 7v13"/><path d="M5 12a7 7 0 0 0 14 0"/><path d="M5 12H3"/><path d="M19 12h2"/>',
            'image'        => '<rect x="3" y="4" width="18" height="15" rx="2"/><circle cx="8.5" cy="9" r="1.6"/><path d="M21 16l-5-4-7 6"/>',
            'presentation' => '<rect x="3" y="4" width="18" height="11" rx="1"/><path d="M12 15v4"/><path d="M8 21h8"/>',
            'sparkles'     => '<path d="M12 4l1.6 4.4L18 10l-4.4 1.6L12 16l-1.6-4.4L6 10l4.4-1.6z"/><path d="M18.5 15l.6 1.9 1.9.6-1.9.6-.6 1.9-.6-1.9-1.9-.6 1.9-.6z"/>',
            // Admin
            'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
            'inbox'        => '<path d="M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1z"/><path d="M3 13h5l2 3h4l2-3h5"/>',
            'building'     => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01"/><path d="M10 21v-3h4v3"/>',
            'map-pin'      => '<path d="M12 21s7-5.5 7-11a7 7 0 0 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'send'         => '<path d="M21 3 10.5 13.5"/><path d="M21 3l-6.5 18-4-8-8-4z"/>',
            // Provider-type identity icons (decorative — see provider_type_icon())
            'hotel'        => '<path d="M4 21V4a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v17"/><path d="M3 21h18"/><path d="M8 7h.01M12 7h.01M16 7h.01M8 11h.01M12 11h.01M16 11h.01"/><path d="M10 21v-4h4v4"/>',
            'resort'       => '<circle cx="12" cy="7" r="3"/><path d="M12 2.5V4M12 10v1.5M6.5 7H5M19 7h-1.5M8.1 3.1 7 2M17 3.1 15.9 2"/><path d="M3 19c1.6 0 1.6 1.2 3.2 1.2S7.8 19 9.4 19s1.6 1.2 3.2 1.2S14.2 19 15.8 19s1.6 1.2 3.2 1.2"/><path d="M3 22c1.6 0 1.6 1.2 3.2 1.2S7.8 22 9.4 22s1.6 1.2 3.2 1.2S14.2 22 15.8 22s1.6 1.2 3.2 1.2"/>',
            'restaurant'   => '<path d="M6 3v6a2 2 0 0 0 4 0V3"/><path d="M8 9v12"/><path d="M16.5 3C15 3 14 5 14 7.5S15 12 16.5 12"/><path d="M16.5 3v18"/>',
            'yacht'        => '<path d="M4 18h16l-2.2 3H6.2z"/><path d="M11 16V3l7 13z"/><path d="M11 16H5l6-3z"/>',
            'logout'       => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
            'shield'       => '<path d="M12 3l7 3v5.5c0 4.3-3 7.5-7 8.5-4-1-7-4.2-7-8.5V6z"/><path d="M9 12l2 2 4-4"/>',
            'settings'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 13.5a7.6 7.6 0 0 0 0-3l1.8-1.4-1.8-3.1-2.1.8a7.5 7.5 0 0 0-2.6-1.5L14.3 2h-3.6l-.4 2.3a7.5 7.5 0 0 0-2.6 1.5l-2.1-.8-1.8 3.1L5.6 10.5a7.6 7.6 0 0 0 0 3l-1.8 1.4 1.8 3.1 2.1-.8a7.5 7.5 0 0 0 2.6 1.5l.4 2.3h3.6l.4-2.3a7.5 7.5 0 0 0 2.6-1.5l2.1.8 1.8-3.1z"/>',
            // Communication / relationship (partner-email indicators)
            'mail'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M4 8l8 5 8-5"/>',
            'handshake'    => '<path d="M12 8L9 5.2a2 2 0 0 0-2.8 0L3 8.3v3.3l4.2 4.2a1.5 1.5 0 0 0 2.1 0"/><path d="M21 8.3L17.8 5.2a2 2 0 0 0-2.8 0L12 8"/><path d="M21 8.3v3.3l-4.5 4.5a1.5 1.5 0 0 1-2.1 0L12 14"/>',
            // Socials
            'instagram'    => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17 7h.01"/>',
            'facebook'     => '<path d="M14.5 8.5H16V5.5h-1.5A3.5 3.5 0 0 0 11 9v2H9v3h2v6h3v-6h2l.5-3H14V9c0-.3.2-.5.5-.5z"/>',
            'linkedin'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 10.5V17"/><path d="M8 7.5h.01"/><path d="M12 17v-3.5a2 2 0 0 1 4 0V17"/>',
            'x'            => '<path d="M5 5l14 14"/><path d="M19 5L5 19"/>',
        ];
    }
    return $set[$name] ?? null;
}

/**
 * Render an inline SVG icon. Returns '' for unknown names.
 */
function icon(string $name, string $class = '', ?string $title = null): string
{
    $inner = _icon_paths($name);
    if ($inner === null) {
        $inner = _icon_paths('sparkles');   // safe fallback
    }
    $cls   = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    $label = $title !== null
        ? ' role="img" aria-label="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"'
        : ' aria-hidden="true"';
    return '<svg' . $cls . $label . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

/** Map an event_type slug → icon name (fallback: sparkles). */
function event_type_icon(string $slug): string
{
    static $map = [
        'wedding'          => 'ring',
        'engagement'       => 'ring',
        'corporate-event'  => 'briefcase',
        'conference'       => 'users',
        'meeting'          => 'users',
        'networking-event' => 'users',
        'training'         => 'presentation',
        'exhibition'       => 'image',
        'product-launch'   => 'rocket',
        'gala-dinner'      => 'star',
        'private-party'    => 'glass-cheers',
        'birthday'         => 'cake',
        'outdoor-event'    => 'tree',
        'yacht-event'      => 'anchor',
        'other'            => 'sparkles',
    ];
    return $map[$slug] ?? 'sparkles';
}

/**
 * Map a provider's display type (Hotel / Resort / Restaurant / Unique venue /
 * …) to an identity icon. Decorative only — unknown/blank → generic building.
 */
function provider_type_icon(string $type): string
{
    $t = mb_strtolower(trim($type));
    if ($t === '')                        return 'building';
    if (str_contains($t, 'hotel'))        return 'hotel';
    if (str_contains($t, 'resort'))       return 'resort';
    if (str_contains($t, 'restaurant'))   return 'restaurant';
    if (str_contains($t, 'conference'))   return 'presentation';
    if (str_contains($t, 'yacht'))        return 'yacht';
    if (str_contains($t, 'unique'))       return 'sparkles';
    return 'building';
}

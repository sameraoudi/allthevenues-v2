<?php
declare(strict_types=1);

/**
 * Strict HTML sanitizer — allowlist of prose tags only.
 * Allowed: p, br, strong, em, ul, ol, li, a[href(http|https|mailto)].
 * Everything else: tag stripped, text kept; script/style content removed.
 *
 * This is the SINGLE source of truth for rich-text sanitisation, used both by
 * the migration import (db/migrate_catalogue.php) and admin editing so what an
 * admin saves is cleaned identically to imported content — never store raw
 * user HTML. Returns null for empty/whitespace-only input.
 */

if (!function_exists('html_sanitize')) {
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
}

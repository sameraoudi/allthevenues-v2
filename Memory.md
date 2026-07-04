# Memory.md — allthevenues.com (v2)

*Current state, open work, and dated history. Update at every closeout: move finished items into the dated
history with a one-liner, add new open items, refresh "current state." Pairs with `CLAUDE.md` (how we work)
and `VISION.md` (north star).*

---

## Current state (as of 3 Jul 2026)

> Latest closeout: **U4d provider management complete + the `/event-types` page shipped**, all verified on
> prod. Providers now fully editable in admin (Verified/Featured, type, email, about, website, status,
> single cover image, commission); a new editorial Event Types mosaic replaced the dead navbar link.

**Live on `staging.allthevenues.com`.** The full public shopfront + the admin to run it are built and
verified on staging. Core loop works end-to-end: browse → enquire → admin inbox → context-aware forward.

**Public site (final design + terminology):**
- Homepage (hero + featured venues + how-it-works), Coastal UAE Soft Blue design.
- **Venues:** `/venues` listing (filter sidebar, active-filter chips, horizontal cards, sort, pagination,
  mobile toggle) + `/venues/{slug}` detail (bounded gallery + self-hosted lightbox, sticky tabs, Key Info,
  "What makes it special" from `highlights`, map embed, venue website link, similar venues).
  **Keyword search (`q`)** in the hero + sidebar matches venue name / provider name / location (area +
  emirate), carried through sort/pagination as a removable chip.
- **Header nav** works on mobile (hamburger → full-width dropdown panel; Shortlist hidden ≤900px; desktop
  unchanged). The dead **Locations** link was removed (a real Locations browse page is planned for U5).
- **Venue Providers:** `/providers` image-led cards (cover = **provider's own cover image if set, else** best
  venue image; type-icon chip; gradient fallback rare) + `/providers/{slug}` cover+avatar header, "Venues by
  this provider", provider info panel, managed enquiry panel. `/partners` → `/providers` 301. No public PII.
- **Event Types:** `/event-types` editorial mosaic — 6 image-led featured tiles (Wedding hero, Corporate
  tall, + Conference/Product Launch/Private Party/Exhibition) linking to `/venues?event_type={slug}`; top +
  bottom enquiry bridges; each tile gated on ≥1 published venue (Gala/Yacht auto-appear once tagged);
  count-threshold soft label ("Explore venues" under 5). Images live in `assets/img/event-types/{slug}.webp`.
- Enquiry is **context-aware**: venue-mode (specific venue), assisted-mode ("help me find"), partner-mode
  (`/enquire?provider=id`) — each shows the right fields; all land as tracked leads.

**Admin:**
- Auth + RBAC (**Administrator / Editor**; partner reserved), real name + role in the chrome, fail-closed
  gates, `/admin/users` management (create the assistant), audit_log.
- Lead inbox: list/filter/detail/status, CSV export, notes; **context-aware forwarding** (defaults the
  partner + email from the enquiry's linked venue).
- **U4a venue edit** (core fields + the new `highlights` field) with audit.
- **U4b venue image management** (secure upload via `lib/upload`, WebP re-encode of full + thumb,
  set-primary, reorder, alt text, delete). Verified on prod.
- **U4d provider management** at `/admin/partners` (list + edit): org/slug/status/emirate/city/contact,
  **email** (fills migration gaps), **Verified/Featured** (independent), **type** (editable via
  `partner_group`), **single cover image** (WebP, overrides venue-derived cover), **commission_rate**
  (admin-only, tri-state). CSRF + RBAC (admin/editor) + audit throughout.

**Data:** 98 venues, ~68 approved providers migrated (latin1→utf8mb4, slugs, taxonomy re-tag, sanitized
HTML). `venues.website` backfilled 98/98. `venue_event_types` seeded 96/98 from `best_for` (Event Type
filter now works). Filter logic corrected (guest-band overlap; indoor/outdoor includes "both"). **Schema
grown for providers:** `partners.is_verified` (real column, replaces the `is_featured` alias),
`cover_image_path`/`cover_thumb_path`/`cover_image_alt`, `commission_rate DECIMAL(5,2) NULL` (NULL=unknown /
0=none / >0=rate), and `partner_group` repurposed as the editable type. Migrations 008–010 applied on prod.
Samer began filling provider emails + adding providers via the new admin.

**In progress:** none — U4d + Event Types closed. Next up: **U4c** (add-venue) or **U5** (SEO landing pages).

---

## Open work

**Admin build-out (U4):**
- **U4c** add-venue (create): reuse the U4b upload path; include the fields Samer flagged missing (images,
  location/map, website, contact person). New build. *(Only remaining U4 item — U4a/b/d all shipped.)*

**Monetization & tiering (Phase 2/3 — see `docs/ATV-TIERS.md`):**
- Homepage **Featured Venues** driven by `is_featured` (DB) + a **Venue of the Month** editorial slot.
- Recommended/relevance **sort** (relevance + completeness + image quality + verified + featured boost +
  freshness + editorial). `tier`/`provider_tier` fields; admin controls to set them.
- A **campaigns/placements** table (which venue/provider holds which paid slot, when) + billing + reporting;
  the **partner portal** (self-serve) — Phase 2/3.

**SEO & launch:**
- **U5** SEO landing pages (event×city, e.g. "Wedding venues in Dubai") + sitemap.
- **U6** launch hardening: notifications set, GoatCounter events, **301s from legacy URLs**, mobile QA,
  security check, backup + rollback, **cutover** staging → apex domain (retire legacy code, tailored CSP
  replaces any stopgap).

**Polish (approved, cascade platform-wide):**
- **Card hover effect** (approved on homepage) — apply to all cards.
- **Button capitalization** rule — first letter capital on every button.

**Content track (Samer):**
- Tag the **2 untagged venues** + fill the empty event types. **Tagging venues to Gala Dinner / Yacht Event
  auto-activates their tiles** on `/event-types` (they're built + gated, just hidden until they have ≥1
  published venue) — add `gala-dinner.webp` / `yacht-event.webp` to `assets/img/event-types/` when ready.
- Fill remaining **provider emails** (started) + reconcile the **~4 NULL `partner_id`** venues; set provider
  **type / Verified / commission** where known via the new provider admin.
- Real venue **photography** + **provider cover images** replacing migrated/placeholder imagery where weak.

**Optional / low priority:**
- **U3c** backfill the 2,378 legacy `inquiry` rows (junk-filtered) into `enquiries` — only if lead history
  is wanted.

---

## Dated history

**3 Jul 2026**
- Split ATV out of the shared sameroudi.com Cowork project into its own project + repo-canonical docs
  (this `CLAUDE.md` / `Memory.md` / `VISION.md` + `docs/`).
- U4 admin foundation: RBAC (Administrator/Editor), real name in chrome, `/admin/users`, fail-closed gates,
  audit on user changes.
- Google-Maps CSP `frame-src https://www.google.com` moved into the **repo** `.htaccess` (persists across
  deploys) after a manual prod edit was clobbered.
- Venue Providers redesign (image-led cards, cover+avatar header, type-icon chip, glass button contrast fix,
  "View" footer) + **Partner→Venue Provider** terminology rename + `/partners`→`/providers` 301.
- Consolidated the monetization/terminology strategy into `docs/ATV-TIERS.md`.
- `venues.website` (98/98) + `venue_event_types` (96/98) backfilled from legacy; venue-detail P0 fixes
  (gallery overlap, lightbox, restored map + website link); guest/indoor-outdoor filter logic corrected.
- U4a venue edit + editable `highlights` field.
- U4b venue image management: secure upload (`lib/upload` allowlist + `getimagesize()` real-image check +
  random filenames), WebP re-encode of full (≤2000px) + thumb (≤600px) stripping EXIF, PNG transparency
  preserved; set-primary/reorder/alt/delete with per-venue ownership + CSRF guards + audit rows. Verified on
  prod: jpg/png → WebP, transparency kept, rejects (.svg/.gif/.php.jpg/>12MB/cross-venue/missing-CSRF) all
  fail clean, `/uploads/test.php` → 403 (non-exec confirmed).
- Committed the canonical docs to the repo (`CLAUDE`/`Memory`/`VISION` + rebuild-plan/tiers/preview) — they'd
  only existed in the working copy after the project split.
- **U4d provider management** (schema-before-code throughout; migrations 008–010 applied on prod):
  - **U4d-1/2** `partners.is_verified` real column + backfill = is_featured; `partner_is_verified()` now reads
    it (Verified independent of Featured); dropped dead `logo_path` from provider SELECTs.
  - **U4d-3a** `/admin/partners` list + edit (status/emirate/city/contact/**email**/website/about +
    independent Verified/Featured), CSRF+RBAC+audit; swapped the dispatch placeholder for a real controller.
  - **U4d-3c** provider **single cover image** — `lib/upload` refactored to a shared core + `upload_partner_cover`;
    admin upload/replace/alt/delete; public card (thumb) + detail hero (full) prefer the provider cover,
    fall back to the venue-derived image; `partners.cover_*` columns.
  - **U4d-3d** `commission_rate` (admin-only, tri-state NULL/0/>0), validated 0–100, never public.
  - **U4d-3b** editable **provider type** stored as the bucket label in `partner_group`;
    `partner_org_type_expr()` prefers it (else the migrated notes value); `/providers` type filter + public
    display update for free.
- **`/event-types` page** built from the approved design lock (`docs/atv-event-types-preview.html`): editorial
  mosaic, 6 gated image tiles → `/venues?event_type` filter, count-threshold soft label, top+bottom enquiry
  bridges, inline-SVG icons (no CDN), real nav wiring (header+footer). Images committed under
  `assets/img/event-types/`.
- **Public polish:** keyword search (`q`) on hero + `/venues` (name / provider / location; removable chip,
  carried through sort+pagination); mobile-nav fix (hamburger → full-width dropdown panel, Shortlist hidden
  ≤900px, desktop unchanged); removed the dead **Locations** nav link (real page deferred to U5); shorter
  hero tagline.

**Late Jun 2026 (rebuild through U3):**
- U0 scaffold (front controller, `lib/` ported, tailored CSP, self-hosted assets).
- U1 schema (14 tables) + legacy→new migration (venues/providers/images, sanitize-on-import) on staging DB.
- U2 public browse (venues list + detail) + homepage on the Coastal UAE design; venue-pages visual pass.
- U3 enquiry system (context-aware modes) + admin lead inbox + context-aware forwarding.
- Partner (provider) public pages (pre-redesign).
- Infra fixes: rsync `--no-perms` (403), MySQL-5.7 compat (not MariaDB), app-owned session path (csrf
  fatal), subdomain docroot, taxonomy → 17 venue types + indoor/outdoor.

**Decision:** rebuild the app + migrate the data (legacy code carried systemic security debt); build on a
staging subdomain against a fresh DB (`sameraou_atv2`), cut over when ready. (Full rationale:
`docs/ATV-REBUILD-PLAN.md`.)

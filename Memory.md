# Memory.md — allthevenues.com (v2)

*Current state, open work, and dated history. Update at every closeout: move finished items into the dated
history with a one-liner, add new open items, refresh "current state." Pairs with `CLAUDE.md` (how we work)
and `VISION.md` (north star).*

---

## Current state (as of 5 Jul 2026)

> Latest closeout: **add-provider create flow (U4d-4)** — deployed + verified on prod (mirrors the U4c
> add-venue pattern: `/admin/partners/new`, auto-slug, draft default, audit, redirect-to-edit). Reporting
> **#5 design lock approved** (`docs/atv-reports-preview.html`) — build order next. **Timezone bug FIXED +
> verified on prod** (commit `4282a31`): app now runs on Gulf time (Asia/Dubai / +04:00); inbox date filter
> returns Gulf "today" correctly; Received column reads Gulf. **Sortable Event Date / Received columns** shipped + verified on prod (commit
> `9d64542`; allowlist-safe ORDER BY, NULL event-dates last, sort carries through pager/filter/CSV).
> **#5 admin reporting foundation SHIPPED + verified on prod** (commit `417c566`) — `/admin/reports`. Next:
> **#8/U6** launch audit + apex cutover (plus #9 image rights, #1 card-hover polish).

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
- **404** is a branded not-found page (Coastal UAE styling, map-pin icon, `.atv-btn` CTAs) — shared across
  all not-found routes.
- **Info/legal (#7):** `/about`, `/terms-of-use`, `/privacy-policy`, `/cookie-policy` (rendered from
  `docs/legal/*.md` via `views/page.php` + `.atv-legal` prose; Terms §10 strengthened image licence) +
  **`/contact`** (become-partner-style hero + overlapping form card; own `mode='contact'` enquiry with a
  reason dropdown → admin inbox with a "Contact" badge/filter + dedicated detail view, no forwarding). Partner
  form now links the live Terms/Privacy + states the image-permission consent. Placeholders + **UAE legal
  review** still pending.
- **Admin inbox** gained a mode filter (all modes), admin-only **delete** (transaction + CSRF + confirm +
  audit; `app.js` now loaded in the admin layout so `data-confirm` fires), and a tidied Venue(s) column.
- **SEO (U5):** every page emits a meta description + canonical (path-only, dedupes filtered listings) + OG/
  Twitter (`views/layout.php`). **Event×city landing pages** at `/venues/{event}-in-{emirate}` (templated
  intro + filtered results + internal links + FAQ w/ FAQPage JSON-LD + enquiry CTA), gated on ≥
  `LANDING_MIN_VENUES` (3); thin combos 301 to the filtered search; invalid → 404; resolves after real venue
  slugs. Venue cards now carry **View details + Enquire**. **Locations** `/locations` (see above). Dynamic
  **`/sitemap.xml`** (hubs + published venues + approved providers + qualifying landing combos) + domain-
  agnostic **`/robots.txt`** (disallow `/admin`).
- **Venue Providers:** `/providers` image-led cards (cover = **provider's own cover image if set, else** best
  venue image; type-icon chip; gradient fallback rare) + `/providers/{slug}` cover+avatar header, "Venues by
  this provider", provider info panel, managed enquiry panel. `/partners` → `/providers` 301. No public PII.
- **Become a Venue Partner:** `/become-a-venue-partner` (nav CTA repointed here from `/enquire`) — hero +
  value cards + provider form; submits as a `partner_signup` enquiry (structured: `company`/`provider_type`/
  `website`/`venues_managed`/`city_pref`/`notes`) into the admin inbox with its own badge, mode filter, and a
  dedicated "Partner request" detail view (no venue/forward panels). Reuses CSRF/honeypot/rate-limit/
  Turnstile/mail. Terms/Privacy links are stubs until backlog #7 (public go-live waits on them).
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
- **Venue admin (U4a/U4c)** — full edit form + **add-venue create** (`/admin/venues/new`, auto-slug, draft
  default, then land on edit for images). Fields via a shared `_venue-fields.php` partial: provider
  assignment (`partner_id`, for routing), classification, capacity/pricing, website, **map_embed** (Google-
  Maps-iframe guarded, stored raw), rich-text content, and an **internal contact** (name/email/phone, never
  public). Audit on create + update.
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
0=none / >0=rate), and `partner_group` repurposed as the editable type. **Venues** gained
`contact_name`/`contact_email`/`contact_phone` (migration 011). Migrations 008–011 applied on prod. Samer
began filling provider emails + adding providers/venues via the new admin.

**In progress:** none — U4, U5, and launch-track **#2, #4, #6, #7, U3c** all complete (+ the docs-exposure
security fix). Next: **#5** reporting → **#8/U6** audit + apex cutover. Also open: **#9** image
rights/provenance (schema + admin), **#3** provider portal (Phase 2), **#1** card-hover polish. See
`docs/ATV-BACKLOG.md`.

---

## 🚀 CUTOVER COMPLETE — `allthevenues.com` LIVE on ATV (5 Jul 2026)

Executed the docroot repoint: `allthevenues.com` addon-domain Document Root → `/atv-staging` (home-relative
`/atv-staging`). Verified on the apex (cache-bust): robots meta `index, follow` (ATV format), legacy
`venue.php?venueid=9` → `301 /venues/grand-ballroom`, GSC file `google5540984c536828b7.html` → 200, sitemap on
apex host. Zero data movement (same DB `sameraou_atv2` + uploads + code). Config `BASE_URL` flipped to
`https://allthevenues.com` (backup `config.php.bak-preapex` kept). Hiccup: first smoke tests showed legacy =
stale LiteSpeed cache pre-propagation; a second flush fixed it. **Legacy kept as rollback** (files
`/public_html/allthevenues` + DB `sameraou_atv` untouched — do not delete for a few weeks). Kept (harmless,
force-redirect to apex): `allthevenues.sameraoudi.com` (cPanel addon subdomain — do NOT delete, tied to the
addon domain), `m.allthevenues.com` (legacy mobile → keep, 301s old m. links to apex = SEO win).
**Post-cutover DONE (5 Jul 2026):** marker removed; real apex enquiry end-to-end OK (Turnstile→inbox→forward);
GSC sitemap submitted + read Success (174 pages); GoatCounter live. **GoatCounter query-strings:** no dashboard
toggle exists — GC records full path incl. `?...` by DEFAULT (stripping is opt-in via `window.goatcounter={path:...}`,
which we did NOT set), so `?submitted=1` conversions are already counted as distinct paths. Nothing to configure.
**Remaining = passive:** keep legacy (`/public_html/allthevenues` + DB `sameraou_atv`) as rollback a few weeks;
72h watch (error_log, GSC coverage, leads). **ATV v2 is LIVE.** **#10 slug-history 301s ✅ DONE (6 Jul 2026); #1 card-hover ✅ DONE.** In progress:
**#3 provider portal** (Phase-2 unit 1) — plan in `docs/ATV-PORTAL-PLAN.md`; **U-P0→U-P5a shipped + inert on
prod** (dark behind `PORTAL_ENABLED`): flag/skeleton, schema, partner auth, My Venues + read-only detail,
live low-risk edit, and sensitive-field change-request submit/withdraw. Next is **U-P5b** (admin review +
approve/notify). Remaining post-launch: rest of #3 (U-P5b→U-P9), #9 (image rights/provenance), U6 passive
watch.

**⚠️ Deploy now hits PROD directly.** Apex serves from `/atv-staging`, so a cPanel `allthevenues-v2` repo
Deploy-HEAD updates the LIVE apex (no separate staging buffer). Workflow unchanged otherwise: local dev
`~/Sites/allthevenues-v2` → GitHub `sameraoudi/allthevenues-v2` → cPanel Git (repo path
`/home1/sameraou/repositories/allthevenues-v2`) → Update from Remote → Deploy HEAD → flush LiteSpeed. Lean on
CC local verification before every deploy. `staging.allthevenues.com` currently = same `/atv-staging` docroot =
a noindex prod-alias (NOT an isolated test env) — set up a real isolated staging (own docroot + cloned DB)
before big/risky features like #3. Legacy cPanel Git repo `allthevenues` (`/home1/sameraou/repositories/
allthevenues`) kept during the rollback window (harmless — its deploy target isn't the live apex).

## Cutover plan (Phase 9) — runbook in `docs/ATV-CUTOVER-RUNBOOK.md`

Hosting layout (confirmed 5 Jul 2026): cPanel primary domain `sameraoudi.com` (home1/sameraou).
`allthevenues.com` = **addon domain**, docroot `/home1/sameraou/public_html/allthevenues` (LEGACY site, DB
`sameraou_atv`). `staging.allthevenues.com` = subdomain, docroot `/home1/sameraou/atv-staging` (NEW app, DB
`sameraou_atv2`, uploads inside). **Cutover = repoint the addon domain's Document Root → `/home1/sameraou/
atv-staging`.** Zero data movement (same DB + uploads + code), no DNS change (domain already on account),
rollback = revert docroot (legacy files + `sameraou_atv` never touched). BASE_URL flips staging→apex in
`atv-staging/config/config.php`; noindex/GoatCounter gates flip automatically by host. Pre-flight gotchas:
Turnstile widget Hostnames must include `allthevenues.com`; GSC `google*.html` verification file must be
copied into `atv-staging` (or DNS-verified); deploy latest code first; `rm uploads/test.php`. Backups done +
downloaded (DB 1.6M, uploads 275M). All staging phases (1–8) complete; only Phase 9 cutover + Phase 10 watch
remain.

## Open work

> **Post-launch backlog:** Samer's 8-item action list (partner portal, multi-venue shortlist, reporting,
> provider ownership, Become-a-Venue-Partner form, contact/legal pages, card-hover polish, launch audit) is
> reconciled against current state + sequenced in **`docs/ATV-BACKLOG.md`**. Decided (Jul 2026): **lean
> launch** on the Phase-1 track, provider portal ships as the first Phase-2 unit.

**Admin build-out (U4): ✅ complete** (U4a edit, U4b images, U4c add-venue, U4d provider management).

**Monetization & tiering (Phase 2/3 — see `docs/ATV-TIERS.md`):**
- Homepage **Featured Venues** driven by `is_featured` (DB) + a **Venue of the Month** editorial slot.
- Recommended/relevance **sort** (relevance + completeness + image quality + verified + featured boost +
  freshness + editorial). `tier`/`provider_tier` fields; admin controls to set them.
- A **campaigns/placements** table (which venue/provider holds which paid slot, when) + billing + reporting;
  the **partner portal** (self-serve) — Phase 2/3.

**SEO & launch:**
- **U5 ✅ complete** — SEO head infra, event×city landing pages, Locations, sitemap.xml + robots.txt.
- **Launch track** (see `docs/ATV-BACKLOG.md`, lean-launch order): #2 partner form ✅ → #6 provider-ownership
  fields ✅ → #4 multi-venue shortlist ✅ → #7 legal/contact/about ✅ → U3c historical enquiries ✅ → #5
  reporting foundation ✅ → **#8/U6 audit + apex cutover = the only remaining launch-track item.** Also open,
  not launch-blocking: #9 image rights/provenance, #1 card-hover polish; #3 provider portal = first Phase-2.
- **U6** launch hardening: notifications set, GoatCounter events, **301s from legacy URLs**, mobile QA,
  security check, backup + rollback, **cutover** staging → apex domain (retire legacy code, tailored CSP
  replaces any stopgap).

**Polish (approved, cascade platform-wide):**
- **Card hover effect** — ✅ DONE (6 Jul 2026, #1): applied to venue/provider/listing cards platform-wide.
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

**6 Jul 2026 (post-launch)**
- **#10 slug-history 301 redirects SHIPPED + verified on prod** (commit `cd694f3`): migration 018 added
  `slug_redirects` (`entity_type` ENUM venue/provider, `old_slug`, `entity_id`, UNIQUE(entity_type,old_slug));
  `lib/slug_redirect.php` (`slug_redirect_capture` on admin save when the slug changes — rows point at the
  entity id so chained renames A→B→C resolve in one hop; `slug_redirect_maybe_301` resolver only 301s to a
  currently published/approved target, else falls through to landing/404). Capture hooks after the successful
  UPDATE in `views/admin/venues.php` + `partners.php`; resolver hooks as the first statement in the `=== null`
  branch of `views/venue.php` + `partner.php`; admin slug-hint copy flipped to "old links auto-redirect."
  Prod-verified: rename A→B 301s old→new, chained A/B→C single-hop, revert auto-drops the row (no loop), draft
  target → 404 (never 301 to unpublished). *Known gap:* only captures FUTURE renames — pre-launch renames
  (e.g. Caesars→Banyan, which is draft anyway) aren't backfilled; add a `slug_redirects` row by hand if an
  already-renamed old slug was indexed and is now live.
- **#1 card-hover standardization SHIPPED + verified on prod** (commit `7bd950c`): the approved `.atv-et-tile`
  lift+zoom (translateY(-4px) + shadow `0 16px 34px rgba(14,27,42,.16)`, image `scale(1.06)`; `.28s` lift /
  `.5s` zoom) applied to `.atv-card` (featured/similar/shortlist), `.pcard` (/providers), and `.venue-row`
  (/venues listing) in `assets/css/brand.css` only. Added `overflow:hidden` to `.atv-card__img` + `.venue-row__img`
  (NOT `.pcard__cover` — would clip the `.pcard__type` avatar); `prefers-reduced-motion` guard disables the
  motion. Design preview approved before build. CSS-only → deploy + LiteSpeed flush, no migration.

- **#3 provider portal — scoped + first 3 units shipped (Phase-2 unit 1).** Full plan in
  `docs/ATV-PORTAL-PLAN.md`. Decisions locked (Samer): **no isolated staging** → dark-launch behind a
  `PORTAL_ENABLED` flag (deploys hit LIVE prod, so every unit ships inert until the flag flips at U-P9); **v1
  scope = all four** (edit-requests, new venues, image requests, claims); **hybrid edit model** (low-risk
  fields live / sensitive fields = admin-approved change requests / commercial+trust+ownership locked).
  - **U-P0 (commit `1c12337`, DEPLOYED + verified inert on apex):** `PORTAL_ENABLED` flag (undefined = OFF, so
    prod stays dark with no manual step), `portal_enabled()` in `lib/helpers.php`, `/portal` router branch in
    `index.php` (flag off = falls through to branded 404), skeleton `views/portal/dispatch.php` +
    `placeholder.php` (noindex via the layout's `$robots` override). `/portal` returns 404 on live.
  - **U-P1 (commit `0e14ace`, migration 019 APPLIED on prod):** `venue_change_requests` table (see
    `docs/ATV-SCHEMA.md`). FKs to venues/partners/users; empty + inert until U-P5. Confirmed prod deps:
    `users.partner_id` (int unsigned) + `venue_images.status` (enum active/hidden — a visibility flag, NOT a
    review lane; U-P7 needs its own pending mechanism).
  - **U-P2 (commit `917163f`, DEPLOYED + verified inert on apex):** partner auth —
    `auth_partner_login_attempt()` (role=partner + active + non-null partner_id; mirrors staff login, generic
    errors, uniform timing) + `auth_require_partner()` (fail-closed to `/portal/login`) in `lib/auth.php`;
    `auth_user()` SELECT extended with `partner_id` (DB-fresh, NULL for staff); `/portal/login` +
    `/portal/logout` + gated landing in `dispatch.php`; `login-content.php` mirrors admin login. CSRF +
    rate-limit (10/IP, 5/email per 15m); **no Turnstile** (matches staff-login precedent; can add at U-P9).
    Staff/partner fully separated (verified both directions). `/portal/*` returns 404 on live (flag off).
  - **U-P3 (commit `250b983`, DEPLOYED + inert):** partner-scoped "My Venues" + read-only detail. `lib/portal.php`
    (`portal_my_venues`, `portal_venue_for_partner` — fail-closed ownership, null⇒404 no existence leak; SAFE
    column set EXCLUDES contact_*/commission/management_source); portal chrome `views/portal/layout.php`
    (noindex); `dashboard.php` + `venue.php`; `/portal/venues/{id}` route. Leak check passed (sentinel
    contact values absent from HTML).
  - **U-P4 (commit `9a90a6e`, DEPLOYED + inert):** live edit of LOW-RISK fields only, via a server-side
    ALLOWLIST (`portal_venue_live_columns()` — area/address/website/video/indoor_outdoor/capacity/
    minimum_spend/pricing/floor_area+unit/map_embed[guarded]/richtext + layouts). Validation mirrors admin
    verbatim; UPDATE re-scoped `WHERE id=:id AND partner_id=:pid`; audited. Proven: forged
    name/slug/status/is_featured/partner_id/contact in the POST are ignored (iterates the allowlist, never
    `$_POST`). `/portal/venues/{id}/edit`.
  - **U-P5a (commit `c73ca38`, DEPLOYED + inert):** sensitive-field change requests (SUBMIT side). Provider
    proposes name/slug/venue_type_id/emirate_id → ONE `venue_change_requests` row (type=edit, status=pending,
    JSON old→new diff); venue is NOT modified. One pending per venue; withdraw supported; both owner-scoped +
    audited. `portal_create_edit_request` / `portal_pending_edit_request` / `portal_withdraw_request`;
    `/portal/venues/{id}/request[/withdraw]`. No email yet (U-P5b).
  - **Next:** **U-P5b** admin review side — queue + per-request diff (before→after), approve (applies diff to
    the venue) / reject / needs_changes + email notifications (admin on submit, provider on decision). New
    admin visual surface → design preview first (as with the reports screen). Then U-P6 new venue, U-P7 image
    requests, U-P8 claims, U-P9 launch (flip the flag + onboard providers). Partner accounts created at U-P9.

**5 Jul 2026 (post-launch)**
- **Venue Layouts & Capacity editor + floor area** (first post-launch feature, shipped live to apex):
  migration 017 (`venues.floor_area` DECIMAL + `floor_area_unit` ENUM sqm/sqft). Admin venue editor now edits
  the 8 fixed layout types (Reception/Theatre/Banquet/Classroom/Cabaret/H-shape/U-shape/Boardroom) via
  `venue_layout_capacity_save()` upsert (blank = delete row); floor-area field with unit. Public detail:
  per-layout icons + floor area in Key Info with a client-side sqm↔sqft toggle (app.js, 1 m²=10.7639 ft²).
  Layouts tab still auto-hides when empty. Icons added to `lib/icons.php` (8 layout + area). Deployed + tested
  on prod, works. NB: confirmed the new prod-apply flow (migration on prod DB first, then deploy = live).

**5 Jul 2026**
- **Venue listing card polish:** provider now shows on the location line as `Provider | City`
  (`views/partials/venue-row.php`) — same size as the city, leading "The" stripped, `mb_strimwidth` 24-char
  cap + `.venue-row__addr` nowrap/ellipsis guard so it never wraps. Disambiguates same-named venues (three
  "Diamond Ballroom"s). Plain text, listing-only. Deployed + verified on prod.
- **U6 launch audit started** — working checklist at `docs/ATV-U6-AUDIT.md` (the #8/U6 runway). Legal review
  decided **not blocking**. **Phase 6 (security) ✅ swept + deployed** (commit `589785e`): CSP already
  tight/domain-agnostic + added a defensive `unset` for the apex parent-docroot guard; CSRF/rate-limit/
  Turnstile on all public forms, RBAC fail-closed, uploads non-exec, error hygiene, `html_sanitize` XSS all
  verified. Apex-header confirmation deferred to cutover (Phase 9). **Phase 4 legacy 301 map BUILT** (commit `a7ea3c9`),
  **pending prod apply**: migration 016 (`legacy_id` on venues+partners), `db/backfill_legacy_ids.php`
  (name+owning-provider match, dry-run default, reports matched/ambiguous/unmatched), `lib/legacy_redirect.php`
  (early index.php hook, real 301 by legacy_id, hub fallback), `.htaccess` fixed-path 301s (venues/providers/
  about/legal→terms/help→contact/whydubai→about). Legacy PK=URL id confirmed (`venueid`→legacy `venues.id`,
  `pid`→`providers.pid`, owner = text `venues.provider`). **Phase 4 DONE + verified on staging** (5 Jul 2026):
  016 applied, backfill --commit (168/169 auto: venues 98/98, providers 70/71, 0 ambiguous); straggler legacy
  #62 (Caesars Palace Dubai → renamed **Banyan Tree Dubai** `banyan-tree-dubai`, partner id 60, `status=draft`)
  hand-set `legacy_id=62`. curl proved all 6 cases single-hop 301; `pid=62`→`/providers` hub because Banyan is
  draft (correct — never 301 to an unpublished page; also not in the legacy indexed set). *Open content decision:
  approve Banyan Tree Dubai if it should be public.* **Phase 3 SEO/indexability ✅ verified on staging** (commit `4a0f81b`): host-gated noindex — staging
  (`staging.` host) returns `X-Robots-Tag: noindex,nofollow` + meta noindex; apex/other hosts index,follow (no
  hardcoded noindex, so apex indexes day one); per-page overrides preserved on prod; robots.txt left crawlable.
  Meta/canonical/OG/sitemap/robots/FAQ-JSON-LD all present. **Cutover-time SEO steps (Phase 9):** apex
  `config/config.php` must set `BASE_URL=https://allthevenues.com` (else canonicals point at noindex staging =
  apex won't index); keep the GSC `google….html` verification file on the apex; submit apex sitemap (never
  staging's) post-cutover. **Phase 5 analytics ✅ built** (commit `de9576e`): GoatCounter snippet in layout, production-apex-gated (off on
  staging/localhost); conversions via PRG success URLs (`/enquire?submitted=1` etc.) counted as pageviews;
  live-fire + "ignore query strings OFF" at cutover. **Phase 1 objective curl sweep ✅** (all routes correct;
  gate code-verified `lib/partners.php:244`; banyan-tree-dubai now approved). **U6 remaining:** Phase 1
  interactive walk (Samer), 2 content, 7 perf, 8 mobile, 9 cutover, 10 watch.
  NB: `Memory.md` + `docs/ATV-U6-AUDIT.md` uncommitted in working copy — fold into a docs commit at a checkpoint.
- **#5 admin reporting foundation** (commit `417c566`, shipped + verified on prod): new
  `/admin/reports` (controller `views/admin/reports.php` + `reports-content.php`), `lib/report_admin.php`
  (9 aggregation fns over `enquiry_admin_where` + optional `AND e.is_historical=0`), built to the approved
  `docs/atv-reports-preview.html` lock. Filter bar reuses the inbox parser + a NEW optional **`provider`**
  filter added to `enquiry_admin_filters`/`enquiry_admin_where` (distinct `:prov`/`:prov2`, HY093-safe,
  backward-compatible; inbox doesn't send it). Historical toggle default live-only (banner + excluded-count).
  KPIs, over-time, by status/event/emirate/venue, provider two-lens (touching vs forwarded), per-section CSV.
  Bars sized by `assets/js/app.js` from `data-pct` (CSP-safe — 0 inline styles/scripts). Nav item reuses
  `enquiries.manage` cap (admin+editor); added `chart`+`download` icons. Report JOIN aliases (`evx/evk/vt/lr`)
  don't collide with where-clause subquery aliases (`ev/evp/vp`).
- **Known future item (not a bug):** `PDO::MYSQL_ATTR_INIT_COMMAND` (Gulf-time fix, `config/db.php`) throws a
  deprecation notice on **PHP 8.5** (local only). Prod is **PHP 8.3** where it's correct — do NOT change (the
  `Pdo\Mysql::` replacement doesn't exist on 8.3). Revisit only if/when prod moves to PHP 8.5+.
- **Add-provider create flow (U4d-4):** new `admin/partners/new` route in `views/admin/partners.php` (between
  list + edit), `partner_admin_create()` in `lib/partner_admin.php` (mirrors `venue_admin_create`),
  self-contained `views/admin/partner-new.php` (Basics + About; no cover panel — needs an id first, like
  venue-new), "New provider" button in `partners-list.php`. Auto-slug from name (blank → slugify, `-2` on
  clash), status default `draft`, audit `create`/`partner`, redirect to edit. `created_at` covered by its
  `DEFAULT CURRENT_TIMESTAMP`; only `slug`+`org_name` are NOT NULL-without-default (both set). Verified on
  prod, test rows cleaned. Commit `94a5fba`.
- **#5 reporting design lock approved** — `docs/atv-reports-preview.html` (Coastal UAE admin shell; filter
  bar reusing the inbox filter set; historical toggle default live-only; tables + CSS bars; KPI cards; over-
  time; by status/event/emirate/venue; by provider two lenses; per-section CSV). Build order pending the
  date-filter fix.
- **Bug found:** enquiry inbox **date-range filter** returns EMPTY on prod. CC proved the code correct at
  every layer locally (form field names, `$_GET` parse, `enquiry_admin_where()` bounds `00:00:00`/`23:59:59`,
  param binding, direct SQL, full controller render — incl. inclusive single-day boundary + only-From/only-To
  + same-day NOW() row). Date lines unchanged since `aef069a` (U3b-2). **No code fix made** (would risk
  breaking correct code). Prod-specific — leading hypothesis: **session-timezone / stored-value** offset
  (local tz is consistent end-to-end so can't reproduce). Next: run 4 read-only queries on prod DB
  (`sameraou_atv2`: NOW()+time_zone; MIN/MAX/COUNT(created_at); a COUNT over a "should-have-rows" window;
  raw created_at of recent rows). If prod COUNT>0 but UI empty → request/session issue (instrument live
  controller); if COUNT=0 → tz/stored-value fix (e.g. pin PDO session `time_zone`, or normalize on read).
  Fix (if any) is environmental/shared → #5 reporting inherits it. Not blocking the #5 build.
  **ROOT CAUSE CONFIRMED (prod evidence):** prod MySQL runs on **UTC** (`NOW()=2026-07-04 22:50`, tz=SYSTEM),
  ~4h behind Gulf; `created_at` is `TIMESTAMP` (stored UTC). Newest row `2026-07-04 10:47`, **no rows on
  server-date 07-05**, so filtering a Gulf "today" window (`>= 2026-07-05 00:00:00`) correctly returns empty.
  Not a code bug — a **timezone-alignment gap**. Local couldn't reproduce (UTC-consistent end-to-end).
  App sets **no** PHP default tz (inherits UTC) and **no** MySQL session tz. **Fix (pending CC + deploy):**
  align the whole app to Gulf — `date_default_timezone_set('Asia/Dubai')` in `index.php` + PDO
  `MYSQL_ATTR_INIT_COMMAND "SET time_zone='+04:00'"` in `config/db.php` (numeric offset, no tz-tables needed;
  UAE has no DST). Caveat: existing TIMESTAMP rows then read +4h (Gulf) — desirable for live data; legacy
  U3c rows drift +4h on display (day-boundary only, accepted). #5 reports inherit Gulf time.

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
- **U4c add-venue + completed venue edit** (finishes U4): migration 011 (venues contact columns); exposed
  the fields the edit form was missing — provider (`partner_id`), website, `map_embed` (guarded, raw),
  internal contact — via a shared `_venue-fields.php` partial; new `/admin/venues/new` create flow
  (auto-unique slug, draft default, audit 'create', redirect to edit for images) + "New venue" button.
- **Branded 404** — Coastal UAE styling, map-pin icon, friendlier copy, `.atv-btn` CTAs (replaces bare
  Bootstrap).
- **U5 SEO (complete):** U5-a head infra (meta/canonical/OG on layout + per-page); U5-b event×city landing
  pages (`/venues/{event}-in-{emirate}`, templated intro+FAQ+JSON-LD+internal links, gated ≥3, thin→301,
  invalid→404, resolves after real venue slugs) + venue-card Enquire action sitewide; U5-c Locations mosaic
  (`/locations`, city images + venue-photo/gradient fallback, nav link restored); U5-d dynamic sitemap.xml
  (175 URLs) + domain-agnostic robots.txt. Design locks: `atv-landing-preview.html`, `atv-locations-preview.html`.
- **Planning:** reconciled Samer's 8-item action list into `docs/ATV-BACKLOG.md`; decided **lean launch**
  (portal is first Phase-2 unit).
- **Launch #2 — Become a Venue Partner** (`/become-a-venue-partner`): migration 012 (enquiries.mode
  `partner_signup` + `provider_type`/`website`/`venues_managed`); dedicated page (hero + value cards + form),
  submits as a structured `partner_signup` lead into the inbox (own badge + mode filter + "Partner request"
  detail); nav CTA repointed. Design lock `atv-partner-signup-preview.html`. (Gotcha: a refinements commit
  shipped un-pushed — confirm `git push` reached origin before deploying.)
- **Launch #7 — info/legal/contact** (`/about`, `/terms-of-use`, `/privacy-policy`, `/cookie-policy` from
  `docs/legal/*.md`; strengthened Terms §10; `/contact` with `mode='contact'` + reason dropdown + admin
  detail/badge/filter, no forwarding; partner-form image consent + live Terms/Privacy links). Admin inbox:
  mode filter, admin-only delete (confirm+audit), tidied Venue(s) column, `app.js` loaded in admin layout.
  Legal drafts saved to `docs/legal/`; **#9 image-rights plan** captured in the backlog.
- **U3c — historical enquiries imported.** Legacy `inquiry` (~2,311 rows) was NOT sqlmap-junk after all —
  mostly real (2017→2026). Migration 015 added `enquiries.is_historical` + `legacy_id` (unique).
  `db/backfill_legacy_enquiries.php` (CLI-only, dry-run-by-default, idempotent via `legacy_id`, legacy DB
  creds supplied at runtime — placeholder in repo) imported all: field-mapped, `inquiry_date`→`created_at`
  preserved, guest→band + event-type→slug mapped, **1,971 best-effort venue links** (legacy name→slug), spam
  scored → `status='spam'` (116), real → `closed` (2,195), `is_historical=1`. **Samer then deleted the 116
  spam rows** (+ their `enquiry_venues`/`lead_routing`). NB: re-running the backfill would re-import them.
- **Admin enquiries pager** is now windowed (First/Prev/… /Next/Last + "Page X of Y") via reusable
  `views/partials/admin-pager.php` — the 2.3k import had ballooned it to 93 links.
- **SECURITY fix:** `docs/` + root `*.md` (CLAUDE/Memory/VISION/backlog/previews) were deploying to the
  docroot and **publicly served** (leaked infra: DB name, `/home1/...` paths, repo, deploy-key alias). Now
  **`.htaccess` denies `/docs/*` + any `.md`** (403); legal pages unaffected (they read `docs/legal/*.md` via
  the PHP filesystem, not HTTP). **Done (5 Jul 2026):** all root + `docs/` `.md` files manually deleted from
  the staging docroot. *(Optional hardening still open — exclude them in `.cpanel.yml`.)*
- **Launch #6 — provider ownership provenance:** migration 013 added `venues.management_source`
  (unassigned/admin_assigned/provider_created/provider_claimed/legacy_import) + `provider_assigned_at`/`_by`
  (backfilled 94 legacy_import / 4 unassigned). Venue save/create now auto-sets source + assigned_at/by when
  the provider changes (clearing → unassigned; unchanged keeps prior source); edit shows read-only
  provider-managed status/source/when/who. `managed_by_provider` is derived (`partner_id IS NOT NULL`).

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

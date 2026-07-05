# ATV-BACKLOG.md — post-U5 action items (reconciled)

*Source: Samer's "Development Action Items" (Jul 2026), reconciled against the actual codebase and
`VISION.md` phasing. Naming/DB details corrected to match what's really in the repo. This is the roadmap
backlog; `Memory.md` remains the live current-state. Pairs with `VISION.md` (phasing authority) and
`docs/ATV-TIERS.md` (monetization/portal model).*

**How to read the "Reconcile" lines:** they flag what already exists so we don't rebuild it, and correct
field names from memory → actual schema.

---

## 1. Standardize card hover effects (Medium)
Consistent lift + stronger shadow + slow image-zoom on hover, across venue / provider / event-type /
location / similar / featured / landing cards.
- **Reconcile:** already an open item in `Memory.md` ("card hover effect approved on homepage → apply to all
  cards"). The mosaic tiles (event-types, locations, landing internal) already have lift+zoom; the venue
  card (`.atv-card`) and provider card (`.pcard`) need the effect applied/normalised. Mostly CSS.
- **Fits:** polish pass, any time. Low risk. Single CC prompt touching `assets/css/brand.css` (+ ensure each
  card image sits in an overflow-hidden wrapper for the zoom).

## 2. Fix "Become a Venue Partner" link (High — launch-critical) — ✅ DONE (Jul 2026)
**Shipped:** `/become-a-venue-partner` page + form; submits as a structured `partner_signup` enquiry
(migration 012 added the mode + `provider_type`/`website`/`venues_managed`); admin inbox badge + mode filter +
"Partner request" detail; nav CTA repointed; added "Yacht provider"/"Other" types. Terms/Privacy links remain
stubs pending #7 — hold the public go-live of the CTA until #7 lands. Original notes below for reference.

The nav CTA currently points at the public venue enquiry form (`/enquire`). Needs a real partner-interest
page + form.
- **Reconcile:** confirmed — `views/partials/header.php` links "Become a Venue Partner" → `base_url('enquire')`.
  This is a routing bug + a missing page.
- **Build:** a `/become-a-venue-partner` page + form (org name, contact, email, phone, website, provider
  type, city/emirate, #venues managed, message, consent). Provider type list should add **"Yacht provider"**
  and **"Other"** to the existing buckets (Hotel/Resort/Restaurant/Unique venue) — extends
  `partner_type_buckets()`. Reuse the existing form infra: CSRF, Turnstile, rate-limit, `lib/mail`. Store as
  a lead — recommend a new `enquiries.mode` value (`partner_signup`) OR a `pending` `partners` row for admin
  review; land it in an admin inbox. **Decision needed:** lead-style vs pending-partner-record.
- **Fits:** standalone unit, buildable now. Depends a little on #7 (Provider Terms link on the form).

## 3. Provider venue management workflow — the partner portal (Critical, but Phase 2)
Approved providers log in, see "My Venues," edit low-risk fields / submit new venues / upload images — all as
**change requests** that admin approves before publishing. Restricted fields (name, slug, ownership,
published/featured/verified, SEO, primary emirate) are request-only.
- **Reconcile:** foundations exist — RBAC already reserves a **`partner` role** (`lib/auth.php`), the venue
  `status` enum already includes `draft/pending/published/needs_changes/archived` (the review states), and
  `audit_log` is in place. What's missing is the **change-request layer** (store proposed edits separately
  for admin diff/approve, not live) + the provider-facing portal UI + notifications.
- **Reconcile (VISION):** this is explicitly **VISION Phase 2** ("partner portal, self-serve"). Large unit.
- **Build shape:** a `venue_change_requests` table (venue_id, submitted_by, payload/diff, status, reviewed_by,
  timestamps); provider portal (`/portal` or gated `/admin` subset) with per-provider venue scoping; admin
  review screen showing before/after; approve applies the diff, reject/needs-changes notify. Field-permission
  map (editable vs request-only) enforced server-side.
- **Dependency:** #6 (ownership/managed-by-provider) must land first (portal scopes on it).

## 4. Multi-venue shortlist + one enquiry (Critical)
Shortlist multiple venues (heart), view/remove, submit ONE enquiry linked to all selected. No account (phase
1) — client-side storage.
- **Reconcile:** the data model is **already there** — `enquiry_venues` (M:N) links one enquiry to many
  venues (per `docs/ATV-SCHEMA.md`), and the header already has a (non-functional) "Shortlist 0" affordance +
  a heart icon on cards. So this is mostly: make the heart functional via `assets/js/app.js` +
  `localStorage` (CSP `script-src 'self'` allows it — it's self-hosted, not inline), a `/shortlist` page, and
  a shortlist→enquiry path that writes one `enquiries` row + N `enquiry_venues` rows.
- **Reconcile (enquiry fields):** the enquiry form already captures event type / date / guests / budget /
  location / F&B / AV / name / email / phone / notes / consent (context-aware modes). Reuse it; add a
  shortlist context mode.
- **Fits:** strong lead-gen, buildable now. Mobile shortlist required.

## 5. Admin reporting dashboard (High)
Enquiry / venue / provider reports with date-range + venue/provider/event/location/status filters + CSV,
**including migrated historical enquiries**.
- **Reconcile:** the admin dashboard today shows only headline counts. Reporting is net-new. The lead inbox
  already has list/filter/CSV for enquiries — extend that thinking into aggregate reports.
- **Dependency — historical enquiries:** this needs the legacy `inquiry` backfill (**U3c**, currently
  deferred/optional in `Memory.md`) — the 2,378 legacy rows are **sqlmap-polluted**, so migration MUST
  junk-filter to real enquiries, preserve original dates, preserve venue/provider links where possible, mark
  rows `is_historical`, and avoid double-counting. Promoting U3c from optional → required for reliable
  reporting.
- **Fits:** report foundation buildable now; "reliable" once U3c lands.

## 6. Provider ownership / "managed by provider" (High — enables #3) — ✅ DONE (Jul 2026)
**Shipped:** migration 013 added `management_source` + `provider_assigned_at`/`_by` on venues (backfill 94
legacy_import / 4 unassigned); save/create auto-set the source + assigned_at/by on provider change; edit shows
read-only provenance. `managed_by_provider` derived (`partner_id IS NOT NULL`), not stored. Original notes below.

A venue becomes provider-managed via admin assignment, provider creation, provider claim+approval, or a saved
provider assignment.
- **Reconcile / CORRECT the field names:** the ownership link is **`venues.partner_id`** (NOT `provider_id`),
  and it's **already admin-assignable** as of U4c-2 (the venue edit form now has a provider selector). So
  "zero venues managed by providers" is a *data* state, not a missing capability — admin can assign now.
- **Additions proposed:** `managed_by_provider` (flag/derived), `management_source`
  (`admin_assigned` / `provider_created` / `provider_claimed` / `legacy_import` / `unassigned`),
  `provider_assigned_at`, `provider_assigned_by`. These are provenance fields that the portal (#3) keys on.
  Migration + surface in venue edit + reports.
- **Fits:** small schema unit; do it right before / with #3.

## 7. Contact, About, Legal pages (Medium; legal before provider registration)
**Drafts provided (Jul 2026):** Samer supplied full **Terms of Use** + **Privacy Policy** drafts (strong,
UAE/Bianca Event Styling framing, cover lead-routing/shortlist/Featured-Verified/provider submissions).
Build the legal pages from these (save the source text into the repo, e.g. `docs/legal/`, so it persists).
Fill placeholders (last-updated date, privacy/contact email, UAE registered address). **Not legal advice —
get a UAE-qualified legal review before publishing** (licence grant, liability/indemnity, UAE PDPL). Fold in
the strengthened image clause from #9 (Terms §10). Original notes below.

About, Contact (+ general contact form with reason dropdown), Privacy, Terms, Cookie notice, Disclaimer, and
Venue Provider Terms.
- **Reconcile:** none exist yet; footer has no About/Contact/legal links. Contact form can reuse the enquiry/
  mail infra (CSRF + Turnstile + rate-limit + `lib/mail`) with a "reason for contact" select, kept clearly
  separate from the venue enquiry flow.
- **Sequencing constraint (Samer's):** Provider Terms must exist **before** #2's partner registration goes
  fully live; other legal pages before public launch (#8).

## 8. Final pre-launch audit (Critical) = U6
The comprehensive functional / content / SEO / security / performance / data-migration audit + backup &
rollback + launch checklist + legacy 301s + cutover to the apex domain.
- **Reconcile:** this *is* **U6 — Launch hardening** in the plan, expanded into a full audit checklist. Adopt
  Samer's checklist as the U6 acceptance criteria. Includes GoatCounter events, legacy-URL 301s, mobile QA,
  security review, backup/rollback, and staging→apex cutover.

---

## 9. Image rights & provenance (High — compliance; do before/at launch)
Ensure ATV only uses images it has the right to use, and track provenance per image. Raised by Samer (Jul
2026): not all current images are owned/consented (some pulled from provider public sites), so word the
legal docs carefully and build a tracking + review process.

**Allowed image categories (production policy):** (1) provider-approved (uploaded/sent with permission);
(2) ATV-owned (taken/commissioned by Bianca/ATV, with rights); (3) licensed (commercial-use licence);
(4) temporary placeholders (until approved, tracked). Phase out images taken from provider public websites
unless written permission is obtained.

**Schema — add to `venue_images` (+ the provider cover on `partners`):**
`permission_status` ENUM (default **`legacy_needs_review`**), `image_source`, `source_url`,
`provider_approved_by`, `approval_date` (DATE), `usage_notes` (TEXT), `expires_at` (DATE, for licences).
Status values: `approved_by_provider`, `owned_by_atv`, `licensed_stock`, `legacy_needs_review`,
`public_website_needs_permission`, `remove_replace`. **Backfill all existing images → `legacy_needs_review`**
(nothing assumed approved).

**Admin:** per-image controls in the image manager to set status/source/approval; a **"needs review" filter/
report** to work the backlog down. (Note: consider a public gate later — only show images with an approved
status — but that would hide most current imagery until reviewed, so gate *after* the classification pass,
not before.)

**Classification actions (content track — Samer/Bianca):** approved/owned → keep; unclear-but-important →
request permission; from public website → replace or request permission; identifiable people → review
carefully; weak/low-quality → replace; unknown source → remove or mark for replacement.

**Onboarding consent (add to #2 partner form + #3 portal upload):**
> By uploading or submitting images to All The Venues, you confirm that you own the images or have the
> necessary rights, licences, and permissions to allow All The Venues and Bianca Event Styling to display,
> crop, resize, edit for formatting, and use the images on allthevenues.com and related promotional
> materials for the purpose of promoting your venues.

**Terms §10 clause (strengthened licence grant — fold into #7):** non-exclusive, royalty-free licence to
display/reproduce/resize/crop/format/use submitted content for operating + promoting the platform; provider
responsible for rights; withdrawal on written request → remove/replace in reasonable time.

**Permission-request email (Samer/Bianca send):** polite request to confirm permission to display/crop/resize
selected venue images, or to send official images. (Template held by Samer.)

**Sequencing:** legal clause + onboarding consent land with #7 / #2 (copy only). The provenance schema + admin
is its own small unit. The classification pass is content-track, ongoing. Not a hard launch-blocker for the
*site*, but the legal wording + onboarding consent should be live before public provider registration.

---

## 10. Slug-history 301 redirects (Medium — SEO; Phase 2)
Make venue/provider **slug changes SEO-safe**: when a slug changes, the old public URL
(`/venues/{old-slug}` or `/providers/{old-slug}`) should **301 to the current URL** automatically instead of
404ing. Raised by Samer (Jul 2026) after launch: renames/rebrands happen (e.g. Caesars Palace Dubai →
Banyan Tree Dubai), and today changing a slug silently breaks the old indexed URL and loses its SEO equity
(the admin edit form even warns "old links will stop working").

- **Why it's needed:** the dynamic sitemap self-heals (drops old slug, lists new) and legacy `venue.php?venueid=`/
  `provider.php?pid=` links already follow renames (they resolve by `legacy_id` → current slug). The ONLY gap
  is the *pretty* `/venues/{old-slug}` URLs — no automatic redirect, so Google-indexed old slugs 404 and their
  ranking/links are lost.
- **Schema:** one table `slug_redirects` — `id`, `entity_type` ENUM('venue','provider'), `old_slug`
  VARCHAR(191), `entity_id` INT (points at `venues.id`/`partners.id`), `created_at`; **UNIQUE(entity_type,
  old_slug)**. Rows point at the entity ID (not the next slug) so chained renames A→B→C resolve in one hop.
- **Capture (admin save):** in the venue + provider save handlers (`views/admin/venues.php`,
  `views/admin/partners.php`), when the slug changes, INSERT `(entity_type, old_slug=previous, entity_id)`.
  On save, DELETE any history row whose `old_slug` == the NEW slug being adopted (prevents a redirect loop /
  stale shadowed row when a slug is reused or reverted).
- **Resolve (public):** in the `/venues/{slug}` + `/providers/{slug}` detail path, on "not found by current
  slug," look up `slug_redirects`; if it maps to an entity that is **currently published/approved**, 301 to
  that entity's current slug — else branded 404 (never 301 to an unpublished page; same rule as the legacy
  map). Independent of `lib/legacy_redirect.php` (that keys on `legacy_id`; this keys on old pretty slug).
- **Admin UX:** flip the edit-form warning from "old links will stop working" to "old links auto-redirect";
  optionally list an entity's past slugs.
- **Effort/fit:** small, standalone unit (1 migration + 2 save hooks + 1 resolver hook + copy). Schema-before-
  code. No dependency on #3, but pairs naturally with the portal (providers may request slug changes). Could
  even ship *before* #3 as a quick SEO win if renames start happening.

---

## Proposed sequencing (respecting VISION phasing + Samer's priorities)

**Finish Phase-1 launch track first** (what VISION calls the MVP):
1. **U5-d** — sitemap + robots (in progress unit).
2. **#2** Become-a-Venue-Partner page/form (launch-critical, standalone, small).
3. **#6** Provider ownership fields (small schema; unblocks the portal + reports).
4. **#4** Multi-venue shortlist + enquiry (data model ready; high lead-gen value).
5. **#7 (partial)** Legal pages needed for launch (Privacy/Terms/Cookies) + About/Contact.
6. **U3c** Historical enquiry backfill (junk-filtered) — needed for #5.
7. **#5** Admin reporting foundation (uses U3c data).
8. **#8 / U6** Final audit + cutover.
9. **#1** Card-hover polish (any time; cosmetic).

**Phase 2 (post-launch, per VISION):**
- **#3** Provider portal + change-request approval workflow (the big one) — after #6 lands and providers
  actually have assigned venues.
- **#10** Slug-history 301 redirects (small SEO unit; standalone — can ship before #3 if renames start).
- Remaining reporting enhancements; provider notifications/metrics.

### ✅ Sequencing decision (Jul 2026): **lean launch, portal first Phase-2 unit**
Samer chose to launch on the Phase-1 track above (through #8/U6 cutover) and ship the **provider portal (#3)
as the first post-launch unit** — matching `VISION.md` phasing. So #3 and reporting *enhancements* do NOT
block launch. The launch-track items that DO ship pre-launch: #2 (partner form), #6 (ownership fields), #4
(shortlist), #7 legal/contact/about, U3c (historical enquiries), #5 reporting *foundation*, #8/U6 audit.
Card-hover polish (#1) any time.

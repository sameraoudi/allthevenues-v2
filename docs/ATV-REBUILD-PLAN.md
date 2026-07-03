# All The Venues — Rebuild Plan (Phase 0 Blueprint)

*Working technical blueprint for the ATV revamp. Pairs with `PLATFORM-DIRECTION.md` (why) and
`REQUIREMENTS.md` (what). This document is the **how**: the build-vs-refactor decision, architecture,
data model + migration, phased build sequence, and the decisions we need to lock before Unit 1.*

*Status: draft for Samer's review — v0.1.*

---

## 1. Decision: rebuild the application, migrate the data

**Chosen path: full rebuild of the application on a clean, secure PHP architecture; migrate the venue
data and preserve URLs.** Rationale (grounded in the security audit + code review):

- The new model needs capabilities the legacy code fundamentally lacks — real **RBAC**
  (admin / editor / partner), a **partner portal**, **approval workflows** (venue claim, change-request
  with a proposed-changes diff), **enquiry → lead → routing** entities, and **audit logging**. The current
  admin is single-role CRUD with (until this week) cosmetic auth and no workflow abstraction. Layering all
  of this onto it is high-effort and high-risk.
- The audit exposed **systemic** debt: zero prepared statements, zero CSRF, MD5 passwords, cosmetic auth
  guards, an unauthenticated upload→RCE, predictable reset tokens. Remediating all of it end-to-end is
  effectively rewriting the data + auth layers anyway.
- Requirements §15.1's own test — *"if the existing code is difficult to maintain, a rebuild may be more
  efficient than layering complex workflows on top of old structure"* — and it is.

**What we keep:** the **venue data** (venues, providers, images, categories), the **content**, and the
**indexed URLs** (migrated + 301-redirected). Only the code is replaced.

**Key de-risker:** we don't start from scratch. `sameraoudi.com` already runs a disciplined, shared-hosting
PHP stack with exactly the building blocks ATV needs — a prepared-statement PDO layer, CSRF, rate-limiting,
session/role-gated auth, and an audit-log helper. We port and adapt those patterns, so the rebuild inherits
a proven security posture rather than reinventing it.

---

## 2. Architecture

**Constraint:** cPanel shared hosting behind LiteSpeed, PHP 8.x, MySQL — server-rendered PHP, **no build
step**, Bootstrap 5 front end. (Same stack family as sameraoudi, so patterns transfer directly.)

**Recommended shape: structured vanilla PHP (no heavy framework).** A light front-controller + a helper
library, mirroring sameraoudi. This keeps it shared-hosting-friendly, dependency-light, and maintainable,
and lets us reuse sameraoudi's libs almost verbatim. (A micro-framework like Slim is a possible alternative;
recommendation is structured vanilla PHP for consistency and zero build tooling — open decision D1.)

**Directory layout (proposed):**

```
public_html/            # docroot (new app)
  index.php             # front controller / router
  assets/               # css, js, img (self-hosted Bootstrap/jQuery/FA — no dead CDNs)
  uploads/              # venue images — PHP execution DISABLED via .htaccess (never web-executable)
config/                 # OUTSIDE docroot — db creds, app config, secrets
lib/                    # helpers (see below)
src/                    # controllers / page handlers / models
views/                  # templates (public, admin, partner)
db/                     # migrations + seed data (taxonomy)
```

**Helper library (ported/adapted from sameraoudi):**

- `config/db.php` — PDO factory, **prepared statements only**.
- `lib/auth.php` — login/session lifecycle + **RBAC** (roles: admin, editor, partner); fail-closed page
  gates; partner-scoping (a partner only ever sees their own venues/leads).
- `lib/csrf.php` — token issue/validate on every state-changing action.
- `lib/ratelimit.php` — throttle login, enquiry, reset, partner registration.
- `lib/audit.php` — write to the `audit_log` table on every admin/partner change.
- `lib/turnstile.php` — Cloudflare Turnstile spam protection on public forms (reuse sameraoudi's).
- `lib/upload.php` — **secure** image handling: MIME/extension allowlist (jpg/png/webp), `getimagesize()`
  validation, server-generated random filenames, resize/thumbnail, store under non-executable `uploads/`.
- `lib/mail.php` — PHPMailer (upgrade to 6.9.x) wrapper for the notification set.
- `lib/validate.php` — input validation/normalization helpers.

**Security baked in (closes the audit structurally):** prepared statements everywhere; CSRF on all writes;
context-correct output escaping + a sanitizer for rich-text fields; secure uploads (no exec dir);
`password_hash()` (bcrypt/argon2); reset = CSPRNG token hashed at rest + expiry + single-use + POST +
email-a-link; rate-limiting; audit log; HTTPS/HSTS. A **tailored CSP** for the new app (self + the CDNs we
actually use) — which lets us **retire the parent-CSP `unset` stopgap** at cutover.

---

## 3. Data model & migration

The target entities are specified in REQUIREMENTS §6 (Venue, Venue Layout Capacity, Event Type, Venue Type,
Venue Image, Partner, User Account, Venue Claim, Venue Change Request, Enquiry, Enquiry Venue, Lead Routing,
Audit Log). Build them as **utf8mb4**, InnoDB, with FKs + indexes, `slug` columns for SEO routing, and
`status` enums for the publish/approval workflow (Draft / Pending Review / Published / Needs Changes /
Archived).

**Migration = a first-class Phase-0 task, not an afterthought.** Steps:

1. **DB schema + data audit (first concrete task).** Export the current prod schema and profile the data —
   the legacy DBs are localhost-bound (unreachable from dev), so this needs a prod-side export
   (phpMyAdmin → Export, or `mysqldump --no-data` for structure + row counts + a sample). Deliverable: the
   real legacy schema for `sameraou_atv` (venues, providers, users, admin, categories, images metadata) and
   a data-quality read (dupes, blanks, encoding issues, orphaned images).
2. **Mapping.** Old tables/columns → new entities (e.g. legacy `venue` → `venues` + `venue_layout_capacity`
   + `venue_image`; legacy provider rows → `partners`; legacy categories → `venue_types`/`event_types`).
3. **Migration scripts** (idempotent, re-runnable) that transform + load legacy → new schema, generating
   slugs, normalizing taxonomy, and relocating/optimizing images.
4. **Content cleanup** folded in (dedupe venues, fix encoding — the legacy tables are latin1; new schema is
   utf8mb4).

**URL / SEO preservation.** Map legacy URLs → clean new routes and 301-redirect:

| Legacy | New |
|---|---|
| `venue-details.php?...` | `/venues/{slug}` |
| `provider.php?pid=...` | `/partners/{slug}` (or fold into venue pages) |
| `venues.php` / filters | `/venues` (+ query filters) |
| — (new) | `/venues/{event-type}-in-{city}` SEO landing pages |

Preserve the Google-indexed paths (there's a `google…​.html` Search Console verification + a sitemap today).

---

## 4. Taxonomy (seed data — from the direction doc, ready to lock)

- **Event types:** Wedding, Engagement, Corporate Event, Conference, Meeting, Training, Product Launch,
  Gala Dinner, Private Party, Birthday, Outdoor Event, Yacht Event, Exhibition, Networking Event, Other.
- **Venue types:** Hotel Ballroom, Banquet Hall, Restaurant, Private Dining Room, Outdoor Venue, Rooftop
  Venue, Beach Venue, Resort, Conference Centre, Meeting Room, Yacht, Island Venue, Garden Venue, Lounge,
  Unique Venue.
- **Locations:** Dubai, Abu Dhabi, Sharjah, Ajman, Ras Al Khaimah, Fujairah, Umm Al Quwain, Al Ain
  (area-level for Dubai/AD later: Downtown, Business Bay, JBR, Palm, Marina, Yas, Saadiyat, Corniche).
- **Guest ranges:** <25, 25–50, 51–100, 101–200, 201–500, 501–1000, 1000+.
- **Budget:** Price on request, Budget-friendly, Mid-range, Premium, Luxury, Minimum spend available,
  Package available.

These become `event_types` / `venue_types` / location + range reference data. **Needs Samer's sign-off**
(mapped against what the legacy categories actually contain — output of the data audit).

---

## 5. Phase-1 MVP — scope & build units

Phase 1 = a modern public lead-gen platform + the admin side to run it (REQUIREMENTS §5, Phase 1 items).
Proposed **build sequence** — each unit is independently shippable + reviewable via the CC loop:

- **U0 — Scaffold.** Repo structure, config-out-of-webroot, the `lib/` set (ported from sameraoudi), router,
  base layout (Bootstrap 5, self-hosted assets), DB connection, tailored CSP. *Milestone: skeleton runs.*
- **U1 — Schema + migration + data audit.** Target schema migrations, seed taxonomy, the legacy→new
  migration scripts, and the data-quality pass. *Milestone: real venue data loaded into the new schema on a
  staging path.*
- **U2 — Public read path.** Venue **search/listing** (filters: event type, city, venue type, guest count,
  budget, indoor/outdoor) + **venue detail** page (gallery, capacity table, facilities, best-for tags,
  enquiry CTA, similar venues) + reusable venue-card component. *Milestone: browse the migrated catalogue.*
- **U3 — Enquiry system + admin lead inbox.** The structured enquiry form (single, multi-venue, and
  "help me find a venue"), consent capture, Turnstile spam protection, DB persistence, user confirmation +
  admin notification emails, and the **admin lead dashboard** (list/filter/detail/status/assign/notes/CSV).
  *This is the commercial heart — REQUIREMENTS marks it Critical.* *Milestone: a submitted enquiry lands as
  a tracked lead an admin can action.*
- **U4 — Admin venue management.** Secure venue CRUD (create/edit/publish/archive, images via `lib/upload`,
  featured/verified flags, assign-to-partner), publish-status workflow, audit logging. *Milestone: admin
  runs the catalogue end-to-end.*
- **U5 — SEO landing pages.** The event×city landing pages (Wedding venues in Dubai, etc.) with unique
  intro + curated results + FAQ + internal links + enquiry CTA; clean routes; sitemap. *Milestone: SEO
  pages live + indexable.*
- **U6 — Launch hardening.** Notifications set, analytics events (GoatCounter — already live on ATV),
  redirects from legacy URLs, mobile QA, security check, backup + rollback plan. *Milestone: cutover-ready.*

**Cutover:** build on a **staging path** alongside the live legacy site; migrate data; test; then switch the
docroot / apply redirects and retire the legacy code. Legacy stays reachable until go-live; full backup +
rollback first. (Retire `bianca` or fold it into the new admin — open decision D3.)

**Phases 2–4** (partner portal → lead ops/monetization → advanced) proceed per REQUIREMENTS once the MVP is
validated; the U0 architecture (RBAC, approval-workflow abstraction, audit log) is built to accept them
without a rewrite.

---

## 6. Decisions

**Locked (30 Jun 2026):**
- **D1 — Stack: structured vanilla PHP**, porting sameraoudi's libs (no framework, no build step).
- **D2 — Build on a staging subdomain against a fresh DB**, migrate data into it, cut over when ready
  (clean separation; credential rotation is automatic at cutover).
- **D3 — bianca: decide after the data audit** (leave as-is for now; retire vs fold-in once we see usage
  and data).

**Still open:**
- **D4 — Taxonomy sign-off** against the data audit (Section 4).
- **D5 — Wireframes:** how much visual design up front — quick wireframes for the 6 Phase-1 page types, or
  design-in-code with Bootstrap and iterate.

---

## 7. Immediate next actions

1. **Data audit (unblocks everything):** export the prod `sameraou_atv` schema + row counts + a sample
   (phpMyAdmin → Export, or `mysqldump`), so we can finalize the migration mapping and confirm the taxonomy.
2. **Lock D1–D3** (stack, staging/DB, bianca) — small set, big leverage.
3. Then **U0 scaffold** as the first CC build unit.

*Fold-ins already decided: DB credentials handled fresh in the new config (D2); the parent-CSP `unset`
stopgap retires at cutover when the tailored CSP ships; the flagged rich-text XSS + `javascript:`-scheme
issues are closed structurally by U0's escaping/sanitizer + upload rules.*

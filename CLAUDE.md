# CLAUDE.md — allthevenues.com (v2 rebuild)

Standing context for this project. Read this in full at the start of every session, alongside `Memory.md`
(current state + open work) and `VISION.md` (strategic north star). This is the *how we work* and *how the
system is built*; it changes rarely. `Memory.md` is the *what's true right now*; it changes every session.

When `VISION.md` conflicts with anything here or in `Memory.md`, **VISION wins** — re-prioritise the work,
don't bend the vision.

---

## 1. What this project is

`allthevenues.com` (ATV) is a **UAE venue lead-generation platform with a managed provider/partner layer** —
event planners browse venues and providers, submit a structured enquiry, and ATV routes that lead to the
right provider. It is a **full rebuild** of a legacy PHP venue directory: the code was replaced on a clean,
secure architecture; the venue **data** and indexed **URLs** were migrated and preserved.

Currently live on a **staging subdomain** (`staging.allthevenues.com`) alongside the retired legacy site;
cutover to the apex domain happens at launch (U6). Built solo, orchestrated through the Cowork → Claude Code
loop (§2).

**The core principle — managed leads.** Enquiries always route *through* ATV; public pages never expose a
provider's direct email/phone. That managed layer is both the user-trust promise and the monetization lever
(see `VISION.md` + `docs/ATV-TIERS.md`).

---

## 2. How we work (roles & workflow)

Three actors, same loop as the sibling sameroudi.com project:

- **Cowork (you)** — orchestrator/advisor. Read the docs, plan, write the **Claude Code (CC) prompt** for
  each change, review CC's diff/report against conventions, keep the docs current. You do **not** edit the
  repo directly; you produce precise specs/prompts and CC applies them. You also drive the design-approval
  loop (build an HTML preview → Samer approves/refines → then the CC build order).
- **Claude Code (CC)** — applies edits, runs `php -l` / greps / local screenshots, commits, pushes.
- **Samer** — deploys (cPanel), applies manual prod steps (see §4), runs/pastes verification output, owns
  the **content track** (venue/event-type tagging, media/photography, copy curation).

### Operating disciplines (non-negotiable)
- **Verify-before-deploy.** Propose change → give a verification command/checklist → CC (local) or Samer
  (staging) runs it and pastes output → confirm → only then proceed. No assumed results.
- **One change per turn.** Tight verification loops; don't batch unverified steps.
- **Schema before code.** DB migrations apply before the application code that depends on them.
- **Name the exact file paths** in every CC prompt, with an explicit **"do not touch" scope fence** — the
  main safety valve that stops CC wandering into adjacent fixes.
- **Design preview before build** for anything visual: an approved `atv-*-preview.html` is the locked spec
  CC builds against.
- **Doc closeout = update `Memory.md`** at the end of every unit: move finished items to the dated history,
  add new open items, refresh "current state."

### Engineering track vs content track
Cowork + CC own **engineering**. Samer owns **content**: venue→event-type tagging refinement, media
uploads/photography, and editorial copy. When a gap is data/tagging (e.g. an empty junction table), we flag
it as a content task — we don't fabricate data to make a feature look done.

---

## 3. Stack & environment

- **Prod/staging:** cPanel shared hosting behind **LiteSpeed**; **PHP 8.3** (`ea-php83` / lsphp). No CDN.
- **Database: Percona / MySQL 5.7 — NOT MariaDB.** No `ADD COLUMN IF NOT EXISTS`, no MariaDB-only syntax.
  All SQL must be MySQL-5.7-compatible (plain `ADD COLUMN`; guard idempotency via `information_schema`).
- **App shape:** structured **vanilla PHP**, no framework, **no build step**. A light front-controller /
  router (`index.php` → `dispatch.php`) + a `lib/` helper library ported/adapted from sameroudi.com.
- **Front end:** the self-hosted **"Coastal UAE Soft Blue"** design system (`assets/css/brand.css`,
  `.atv-*` prefixed) — self-hosted fonts (Cormorant Garamond + Inter) and assets, **no CDN** anywhere.
  Inline-SVG icons (`lib/icons.php`). Canonical spec: `docs/ATV-VISUAL-SPECS.md` + the approved
  `docs/atv-*-preview.html` files.

### Repos & infra
- **Local repo:** `~/Sites/allthevenues-v2` (CC works here). **GitHub:** `sameraoudi/allthevenues-v2`
  (private; SSH deploy-key host alias `github-atv2.github.com`).
- **Staging docroot:** `/home1/sameraou/atv-staging`. **DB:** `sameraou_atv2` (user `sameraou_atv2`).
- **cPanel Git repo path:** `/home1/sameraou/repositories/allthevenues-v2`.
- **Legacy (read-only source):** DB `sameraou_atv` (venues 98, providers 71, images/docs, inquiry 2,378 —
  heavily sqlmap-polluted), localhost-bound on prod (only reachable server-side).
- **config/config.php** — gitignored, **prod-only** (DB creds, BASE_URL, Turnstile keys, mail config).
  `config/config.example.php` is committed.

---

## 4. Deployment (and what will NOT auto-propagate)

Flow: edit local → test → `git commit` → `git push` → cPanel **Git Version Control → Update from Remote →
Deploy HEAD Commit**. Deploy runs `.cpanel.yml` rsync.

**`.cpanel.yml` essentials:**
- Uses `rsync -rlt --no-perms --no-owner --no-group` — **NOT `-a`** (which clobbers perms → docroot 700 →
  403). Keep the no-perms flags.
- **Excludes:** `.git`, `.gitignore`, `.cpanel.yml`, **`db`**.

Therefore:
- **`.htaccess` IS deployed** (it's not excluded) → the **repo `.htaccess` is the source of truth**. NEVER
  hand-edit `.htaccess` on prod — the next deploy overwrites it. Any CSP/header change goes in the repo,
  commits, deploys. (This bit us: the Google-Maps `frame-src` was hand-edited on prod, then clobbered.)
- **`db/` is deploy-excluded** → migration/backfill scripts do **not** ship on deploy. To run one on prod,
  **manually upload it** (cPanel File Manager / SFTP) into `atv-staging/db/` — **and its shared deps** (e.g.
  `db/_migrate_lib.php`; forgetting it = "script prints nothing" = a fatal `require` with display_errors off).
  Scripts are CLI-only guarded + idempotent + re-runnable; they can be `rm`'d from prod once run.
- **DB changes** run directly against the prod DB (phpMyAdmin), schema before code — never via git.
- **Media (`uploads/`) is gitignored + prod-only.** Migrated venue images arrive via `copy_media.sh`
  (server-side copy from legacy). Admin-uploaded images are managed at runtime under prod `uploads/` — no
  deploy step for the files themselves. `uploads/.htaccess` (PHP-execution denied) IS committed and must stay.
- After header/asset/CSP changes, **flush LiteSpeed Web Cache** before verifying — the CSP is a cached
  response header and a stale copy can mask a fix.

Keep unrelated concerns in separate commits.

---

## 5. Database & security conventions

- Access via the PDO helper; **prepared statements only**. Escape/cast all output — `e()` for strings,
  `(int)` for numbers; rich-text fields are **sanitized on write** and rendered raw only where already
  sanitized.
- **HY093 trap:** a named placeholder reused in one statement throws HY093 — give each occurrence a distinct
  name (`:q1`/`:q2`).
- **Never leak error detail:** `catch → error_log($e) + generic message`. No `getMessage()` to users/APIs.
- **Security baked in** (closes the legacy audit structurally): CSRF on every state-changing action
  (`lib/csrf.php`); rate-limiting (`lib/ratelimit.php`); Turnstile on public forms (`lib/turnstile.php`);
  **RBAC** fail-closed (`lib/auth.php` — roles admin/editor/partner; `auth_require_role()`); `audit_log` on
  admin/partner writes (`lib/audit.php`); bcrypt `password_hash`; session hardening (app-owned
  `storage/sessions`, secure/httponly/samesite cookies); **secure uploads** (`lib/upload.php` — allowlist
  jpg/png/webp, `getimagesize()` validation, server-random filenames, WebP re-encode, non-exec `uploads/`).
- **CSP is self-only** (source-allowlist), tuned tight: `default-src 'self'`; `script-src`/`style-src 'self'`
  (the public site uses **zero inline styles/scripts** — implement covers as `<img>`+CSS classes, never
  `style="…"`); `img-src 'self'`; `frame-src` allows Turnstile + `https://www.google.com` (venue-detail maps
  embed — KEEP that line); GoatCounter for analytics/connect. `.htaccess` change → repo, deploy, flush cache.

### lib/ helper set
`config/db.php` (PDO), `lib/csrf.php`, `lib/auth.php` (RBAC + `auth_user()`/`auth_role()`/`auth_can()`/
`auth_require_role()`), `lib/audit.php`, `lib/ratelimit.php`, `lib/turnstile.php`, `lib/mail.php`
(PHPMailer), `lib/enquiry.php` (public enquiry + context modes), `lib/enquiry_admin.php` (inbox), `lib/
venues.php` (listing/detail/filters/sort), `lib/partners.php` (providers listing/detail/cover-image),
`lib/upload.php` (secure images), `lib/icons.php` (inline SVG). Front end: `assets/css/brand.css`,
`assets/js/app.js` (self-hosted stepper, gallery lightbox, sticky tabs).

---

## 6. Data model (Phase-1)

14 InnoDB/utf8mb4 tables (full spec: `docs/ATV-SCHEMA.md`). Reference: `event_types` (15), `venue_types`
(17), `emirates` (8). Core: `partners` (providers), `venues` (+ `indoor_outdoor`, `highlights`, `website`),
`venue_layout_capacity`, `venue_event_types` (M:N), `venue_images`, `venue_documents`. Access/leads:
`users` (RBAC), `audit_log`, `enquiries` (+ `mode` ENUM venue/assisted/partner/general, `partner_id`),
`enquiry_venues`, `lead_routing`.

**Gates:** venues/providers are visible only when `published`/`approved` — otherwise **404** for non-admins
(server-gated, not just hidden), mirrored across list/detail/enquiry. Admin preview is read-only.

**IDs diverge legacy↔new and local↔prod** (migrations ran per-env). Never hardcode an id in a cross-env
script — key on stable slugs/codes + resolve per env (the migration keys on `unique_slug(slugify(name))`).

**Soft data (migration artifacts to clean via admin later):** provider *type* derived from legacy `notes`
(no `partner_group`); no real `is_verified` on `partners` yet (mapped to `is_featured` — the single function
`partner_is_verified()` flips when a real column lands, U4d); provider dates synthetic (`created_at` =
migration time); `venue_event_types` seeded mechanically from `best_for` (rough first pass — refine in
admin); a few venues with NULL `partner_id`; some approved providers with NULL email.

---

## 7. Terminology (locked)

Public directory = **Venue Providers** (route `/providers`; `/partners` 301s to it). Badges shown publicly =
**Featured** / **Verified** only (max two at once). Paid/commercial relationship = **Venue Partner**
(CTA "Become a Venue Partner"); tiers **Featured Partner** / **Premium Partner**; trust = **Verified
Provider** (editorial, not paid). The DB table is still `partners` — this is a **label + route** convention,
not a schema rename. Full model + monetization: `docs/ATV-TIERS.md`.

---

## 8. Hard-won gotchas

- **MySQL 5.7, not MariaDB** — `ADD COLUMN IF NOT EXISTS` throws #1064. Plain `ADD COLUMN`; guard via
  `information_schema`.
- **`.htaccess` deploys from the repo** — never hand-edit prod (clobbered next deploy). Opposite of
  sameroudi.com, where `.htaccess` is manual-on-prod.
- **`db/` is deploy-excluded** — upload migration scripts (and their `require`d deps) to prod manually; a
  script that "prints nothing" is usually a missing dep + display_errors off (run with `php -d
  display_errors=1`).
- **rsync `--no-perms --no-owner --no-group`**, never `-a` (perms clobber → 403).
- **Session fatal:** default cPanel session dir may be missing → `session_start()` fatal → `csrf_token()`
  fatal mid-render → half-rendered forms. Fixed via app-owned `storage/sessions` (0700). Keep it.
- **Media is prod-only + gitignored** (`uploads/`); missing local images are an asset state, not a bug —
  `copy_media.sh` populates prod; the placeholder/gradient fallback is expected locally.
- **Flush LiteSpeed** after CSP/header/asset changes before trusting a verification.
- **Legacy `inquiry` is sqlmap-polluted** (2,378 rows, mostly junk) — any backfill must junk-filter to real
  enquiries; not yet migrated (optional, low priority).

---

*Pointers: `VISION.md` = strategic north star (authoritative on conflict). `Memory.md` = current state, open
work, dated history (update at every closeout). `docs/` = reference specs (`ATV-REBUILD-PLAN.md`,
`ATV-SCHEMA.md`, `ATV-TIERS.md`, `ATV-VISUAL-SPECS.md`) + approved `atv-*-preview.html` design locks.*

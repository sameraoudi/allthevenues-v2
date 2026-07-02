# Catalogue migration (U1b) — legacy `sameraou_atv` → `sameraou_atv2`

Migrates the legacy **catalogue** (partners, venues, layouts, images, docs,
admins) into the new schema. **Enquiries/leads are out of scope** — they
migrate in U3.

Both databases are localhost-only on the production server, so the transform
**runs on the server**. Develop and test locally against a loaded copy of the
dump.

---

## What it does

`db/migrate_catalogue.php` (idempotent — truncates the target catalogue tables
at the start, so every run yields identical results):

| # | Target ← legacy | Notes |
|---|---|---|
| 1 | `partners` ← `providers` (71) | slug from name (deduped); `status` 1→approved else draft; `is_featured` from `featured`; `emirate_id` from `city` (default Dubai); `about` sanitized; `logo_path` rewritten. `partner_group` left **NULL** (legacy has no group FK). Legacy `type` folded into `notes` (see below). |
| 2 | `venues` ← `venues` (98) | slug from name; `partner_id` resolved from legacy `provider` **text** (case-insensitive vs `partners.org_name`; NULL + logged if unmatched); `venue_type_id` re-tagged per §6; `indoor_outdoor` from `category`; `emirate_id` from `city`; rich text sanitized; capacities/price mapped; `pricing_level` derived; `status` 1→published else draft. |
| 3 | `venue_layout_capacity` | the 8 `capacity-*` columns → one row each where value > 0. |
| 4 | `venue_images` ← `venue_images` (257) | venue_id remapped; `file_path` rewritten; `is_primary` = the image matching the venue's `main-photo`, else the first; `sort_order` sequential. |
| 5 | `venue_documents` ← `venue_document` (144) | venue_id remapped; metadata only (not surfaced yet). |
| 6 | `users` ← `admin` (2) | `role=admin`; legacy **unsalted MD5 is NOT carried over** — an unusable-password sentinel is stored and the account must go through a password reset once the admin-login unit lands. |

**Skipped** (out of scope / archived): `user`, `reviews`, `rating`, `vendors`,
`favourite`, `subscriber`, `parternships`, `venue_events`, `inquiry`.

### Decisions worth knowing

- **Venue count is 98, not the spec's "~74".** The dump actually contains 98
  venue rows (91 published + 7 draft), all with names — all are migrated.
- **No `org_type` column.** The finalized schema has none, so the legacy
  provider `type` is written into `partners.notes` as a `Legacy org type: …`
  line (alongside any legacy notes). Revisit if a real column is wanted later.
- **`map_embed` is stored RAW.** Legacy `map` is a Google Maps `<iframe>`; it
  is **not** run through the prose sanitizer (that would strip it) and is
  **not** rendered in Phase 1 (the tight CSP has no maps `frame-src` yet).
  When maps land it must be rendered safely (extract `src` into a sandboxed
  iframe under an expanded CSP) — never echoed as-is.

### Sanitization (stored-XSS closed at the source)

Rich-text fields (`about`, `description`, `best_for`, `facilities`,
`food_beverage`, `av_support`, `restrictions`, `packages`, `special_offer`,
`atv_review`) are cleaned with a **strict DOMDocument allowlist** —
`p, br, strong, em, ul, ol, li, a` only; `<a href>` limited to
`http/https/mailto` (and given `rel="noopener nofollow"`); everything else
(script/style/iframe/on\* handlers/other tags) stripped, text kept.

DOMDocument (built into PHP) was chosen over HTML Purifier to stay
**dependency-free / no build step**, consistent with the rest of the stack.

### Media path scheme (served from `self`)

`file_path` / `main_image` / `logo_path` are rewritten to app-docroot-relative
paths:

```
uploads/venues/images/<filename>      venue images + venue main photo
uploads/venues/documents/<filename>   venue documents (PDF brochures)
uploads/partners/<filename>           partner logos
```

The transform only rewrites the **paths**; the files are copied by the
server step below.

---

## How to run on the server

### 1. Prerequisites

- The new schema is already imported into `sameraou_atv2`
  (`db/001_schema.sql` + `db/002_seed_taxonomy.sql`; `003` is the U1a live
  patch and is already folded into `001` for fresh imports).
- The legacy DB `sameraou_atv` is present and readable.

### 2. Configure DB credentials

The **new** (write) DB creds are read from `config/config.php`
(`DB_HOST/DB_NAME/DB_USER/DB_PASS`).

The **legacy** (read) creds are supplied to the script. Either edit the
`$LEGACY` block at the top of `db/migrate_catalogue.php`, or pass them as
environment variables:

```bash
export ATV_LEGACY_DB_HOST=localhost
export ATV_LEGACY_DB_NAME=sameraou_atv
export ATV_LEGACY_DB_USER=<legacy_read_user>
export ATV_LEGACY_DB_PASS=<legacy_read_pass>
```

### 3. Run the transform

```bash
php db/migrate_catalogue.php
```

It prints migrated counts and any venues whose `provider` text did not resolve
to a partner. Safe to re-run (it truncates + rebuilds the catalogue tables).

### 4. Copy the media files

```bash
export ATV_NEW_DB_PASS=<sameraou_atv2_password>
# Optional overrides (defaults shown):
# export LEGACY_IMAGES_ROOT=/home1/sameraou/public_html/allthevenues/images
# export NEW_APP_ROOT=/home1/sameraou/atv-staging
bash db/copy_media.sh
```

This reads the migrated file paths from `sameraou_atv2`, finds each file by
name under the legacy images tree, and copies it into the new app's
`uploads/` tree. Idempotent (existing files skipped); reports copied /
skipped / missing counts.

> `uploads/` already disables script execution (`uploads/.htaccess`), so
> copied files are served as static assets only.

---

## Local development / testing

Load the dump + new schema into a local **MySQL 5.7** (match the host — no
MariaDB/8.0-only syntax), then point the transform at it via env vars:

```bash
# with a local MySQL 5.7 holding sameraou_atv (dump) + sameraou_atv2 (schema)
ATV_LEGACY_DB_HOST=127.0.0.1 ATV_LEGACY_DB_PORT=3307 \
ATV_LEGACY_DB_NAME=sameraou_atv ATV_LEGACY_DB_USER=root ATV_LEGACY_DB_PASS=root \
ATV_NEW_DB_HOST=127.0.0.1 ATV_NEW_DB_PORT=3307 \
ATV_NEW_DB_NAME=sameraou_atv2 ATV_NEW_DB_USER=root ATV_NEW_DB_PASS=root \
php db/migrate_catalogue.php
```

Keep any legacy dump **outside** the repo (e.g. `~/atv-legacy/`); it is
gitignored if placed inside.

### Verified locally (MySQL 5.7.44)

Counts: partners **71**, venues **98**, layout rows **344**, images **257**,
documents **144**, admins **2**. Re-run produces identical results
(idempotent). 4 venues have an unresolved `provider` (partner_id NULL):
Majesty 101 Mega Yacht, A4 Space, Moana Island, The Empty Quarter Gallery.

---

## Admin password bootstrap (U3b-1)

The migrated admins (`users`, `role=admin`) have unusable passwords, so no one
can sign in to `/admin` until a password is set. Do this once per admin, on
the server (CLI only — the script refuses to run over HTTP, and `db/` is
excluded from deploy + web-denied):

```bash
php db/set_admin_password.php <admin-email> '<new-password>'
# e.g.
php db/set_admin_password.php samer@allthevenues.com 'a-long-strong-passphrase'
```

- Sets `users.password_hash = password_hash($pw, PASSWORD_DEFAULT)` (bcrypt)
  for the matching `role='admin'` user.
- Minimum password length: 10 characters.
- If the account's `status` isn't `active`, the script warns — set it to
  `active` to allow login.

Then sign in at **`/admin/login`**. Wrong credentials are rate-limited and
return a generic error (no user enumeration); the admin area fails closed
(any `/admin/*` while logged out → `/admin/login`).

# ATV-PORTAL-PLAN.md — Provider Portal (#3, Phase-2 unit 1)

*Planning spec for the provider self-serve portal. Pairs with `VISION.md` (Phase-2 authority),
`docs/ATV-BACKLOG.md` #3, `docs/ATV-TIERS.md` (provider model), and `docs/ATV-SCHEMA.md`. `Memory.md`
remains live current-state. This is the **architecture + sequencing**; each U-P* unit becomes its own
schema-before-code CC build order.*

---

## Decisions locked (6 Jul 2026, Samer)

1. **No isolated staging** — the portal builds on the current setup. Deploys hit **live prod directly**, so
   CC local verification is the only pre-deploy gate. → **Mitigation is mandatory: dark-launch behind a
   feature flag (U-P0).** Every unit ships with the portal invisible/inert on prod until the flag flips at
   the end (U-P9). No unit may expose an unfinished portal surface to the public or to providers.
2. **v1 scope = all four capabilities:** edit-requests on assigned venues, submit new venues, image upload
   requests, claim existing venues.
3. **Hybrid edit model:** low-risk fields apply **live** (audited); sensitive fields are **admin-approved
   change requests**; commercial/trust/ownership fields are **locked** (not even requestable). Full map below.

---

## Foundations already in place (do NOT rebuild)

- **RBAC** — `lib/auth.php` reserves `role='partner'`, fails it closed out of `/admin`, and `auth_login_attempt`
  denies partner at the staff login. `auth_capability_roles()` is the capability source of truth.
- **User→provider link** — `users.partner_id` FK already exists (null for staff); scopes a portal user to one
  provider. *(U-P1 verifies the column is present on prod before code.)*
- **Review states** — `venues.status` ENUM already includes `pending` and `needs_changes`.
- **Ownership provenance (#6)** — `venues.partner_id` + `management_source`
  (unassigned/admin_assigned/provider_created/provider_claimed/legacy_import) + `provider_assigned_at`/`_by`.
- **Image status** — `venue_images.status` exists (a pending/approved lane for portal uploads; coordinates
  with #9 image-rights `permission_status`).
- **Reusable infra** — `lib/csrf.php`, `lib/ratelimit.php`, `lib/turnstile.php`, `lib/mail.php`,
  `lib/upload.php` (secure images), `lib/audit.php`, `lib/venues.php` validation patterns in
  `views/admin/venues.php`.

---

## Security model (VISION: secure by construction)

- **Separate area:** the portal lives at **`/portal`**, NOT under `/admin`. Distinct router branch, distinct
  layout. `/admin` gates are untouched.
- **Partner auth:** new `/portal/login`. A partner login path authenticates `role='partner'` + `status='active'`
  + non-null `partner_id`. Staff login (`/admin/login`) stays partner-denied. New gate
  `auth_require_partner()` (fail-closed → `/portal/login`). Session regenerate on login (fixation).
- **Ownership scoping — the core rule:** every portal read/write is filtered server-side by
  `auth_user()['partner_id']`. A provider may only ever see/act on venues where `venues.partner_id` = their
  own id. Never trust a `venue_id` from the client without re-checking ownership. Fail closed on mismatch (404,
  not 403 — don't reveal existence).
- **Reused guards:** CSRF on every write, rate-limit + Turnstile on `/portal/login`, `lib/upload` for images
  (allowlist + `getimagesize` + WebP re-encode + random names + non-exec dir), `html_sanitize` on rich text,
  prepared statements + `e()`/`(int)` output escaping, generic errors (`error_log` + no `getMessage()` leak).
- **Audit:** every portal write (live edit, request submit, image upload, claim) writes `audit_log` with the
  partner user as actor. Admin approve/reject also audited.
- **Feature flag:** `PORTAL_ENABLED` (config constant, default **false** on prod). Router returns branded 404
  for `/portal/*` when off; the footer "Partner login" link stays "Coming soon" until U-P9. Flipping the flag
  is the launch switch.

---

## Field-permission map (hybrid edit model)

Enforced **server-side** (a single map function, e.g. `portal_venue_field_policy()`), never by hiding inputs
alone. Three tiers:

**LIVE — provider edits apply immediately to their own venue (audited):**
description/highlights/rich-text content, `capacity_min`/`max`, `floor_area`(+unit), layout capacities
(`venue_layout_capacity`), `pricing_level`, `minimum_spend`, `indoor_outdoor`, `area`, `address`, `website`,
`video_url`, `map_embed` (same iframe guard as admin), event-type tags (`venue_event_types`).

**REQUEST — sensitive; submitted as a `venue_change_requests` row, admin approves before it goes live:**
`name`, `slug`, `venue_type_id` (classification/SEO), `emirate_id` (primary emirate), and any future SEO
fields. Provider may *request* publish (draft/needs_changes → pending) but admin owns the transition to
`published`.

**LOCKED — provider can never change or request:**
`is_featured`, `is_verified`, `commission_rate`, `partner_id`/ownership, and direct `status='published'`.
These are commercial/trust/ownership — admin-only, always.

*(Internal venue contact `contact_name/email/phone` is admin-only routing data — NOT exposed in the portal at
all.)*

---

## New data model

**`venue_change_requests`** (new table, U-P1) — one row per pending provider action:
`id`, `venue_id` INT NULL (NULL for a new-venue submission), `partner_id` INT, `submitted_by` INT (users.id),
`type` ENUM('edit','new_venue','image','claim'), `proposed_changes_json` JSON/TEXT (the field diff, new-venue
payload, image ref, or claim target), `status` ENUM('pending','approved','rejected','needs_changes',
'withdrawn') DEFAULT 'pending', `review_note` TEXT NULL, `reviewed_by` INT NULL, `reviewed_at` DATETIME NULL,
`created_at`/`updated_at`. Indexes: `(partner_id, status)`, `(venue_id)`, `(status, created_at)`.
MySQL-5.7-safe (JSON type is fine on 5.7; guard idempotency via information_schema).

**Reused, not new:** new-venue submissions → a `venues` row `status='pending'`, `partner_id`=submitter,
`management_source='provider_created'`. Approved claim → set `partner_id` + `management_source='provider_claimed'`.
Portal image uploads → `venue_images` row `status='pending'` (+ #9 `permission_status`), promoted on approve.

---

## Build sequence (each unit = one schema-before-code CC order, dark on prod)

- **U-P0 — Feature flag + portal skeleton.** `PORTAL_ENABLED` config constant (false on prod);
  `config.example.php` documents it. Router `/portal/*` branch → branded 404 when off; empty gated shell when
  on. Nothing user-visible on prod. *Safe first deploy.*
- **U-P1 — Schema.** Migration for `venue_change_requests`; verify `users.partner_id` + `venue_images.status`
  exist on prod. Apply on prod DB first; no behavior change.
- **U-P2 — Partner auth.** `/portal/login` (partner role), `auth_require_partner()`, logout; CSRF + rate-limit
  + Turnstile. Testable locally with a seeded partner user; still dark on prod.
- **U-P3 — "My Venues" list + read-only detail,** scoped by `partner_id`, fail-closed ownership.
- **U-P4 — Live edit (low-risk fields).** Field-policy map enforced server-side; reuse admin validation;
  audit. No admin approval in this loop.
- **U-P5 — Sensitive-field change requests + admin review.** Submit → `venue_change_requests(type=edit)`;
  provider sees pending state; admin diff/approve(apply)/reject/needs_changes screen; email notifications
  (provider on decision, admin on submit).
- **U-P6 — Submit new venue** (type=new_venue → `venues` pending on approve).
- **U-P7 — Image upload requests** (`venue_images` pending; coordinate with #9 `permission_status` + consent).
- **U-P8 — Claim existing venue** (type=claim → assign `partner_id` + provenance on approve).
- **U-P9 — Launch.** Dashboard/status polish, notification copy, then flip `PORTAL_ENABLED=true` on prod +
  footer "Partner login" live. Provider onboarding (create partner users, send credentials) is a content step.

**Dependencies:** U-P0 → U-P1 → U-P2 → U-P3 gate the rest. U-P4/U-P5 are the core value; U-P6/7/8 are
additive capabilities; U-P9 is the switch. #9 (image rights) should ideally land alongside or before U-P7.

---

## Open questions to resolve as we reach them

- **Provider onboarding:** how do partner users get created initially — admin creates them in `/admin/users`
  (needs a partner-role + partner_id assignment path there) and emails credentials, or a self-registration
  request? (Recommend admin-created for v1.)
- **New-venue duplicate guard:** prevent a provider submitting a venue that already exists (name/slug match →
  suggest a claim instead).
- **Notification volume:** batch vs per-event admin emails once request traffic grows.
- **#9 coordination:** portal image uploads should carry the consent + `permission_status` from the start so
  we don't create a second review backlog.

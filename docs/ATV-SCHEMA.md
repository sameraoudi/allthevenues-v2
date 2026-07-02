# All The Venues — Target Schema & Migration Spec (U1)

*The Phase-1 data model for the rebuild, and how the legacy `sameraou_atv` data maps into it. Review this
before we build (schema-before-code). All tables: InnoDB, utf8mb4, FK-enforced, `slug` where routed.
CC turns this into `db/` migration SQL + the transform script in U1.*

---

## 1. Taxonomy / reference tables (seeded, not migrated)

**`event_types`** — `id`, `name`, `slug`, `sort_order`, `active`. Seed the 15 from the direction doc
(Wedding, Engagement, Corporate Event, Conference, Meeting, Training, Product Launch, Gala Dinner, Private
Party, Birthday, Outdoor Event, Yacht Event, Exhibition, Networking Event, Other).

**`venue_types`** — `id`, `name`, `slug`, `sort_order`, `active`. Seed **17**: the 15 (Hotel Ballroom,
Banquet Hall, Restaurant, Private Dining Room, Outdoor Venue, Rooftop Venue, Beach Venue, Resort,
Conference Centre, Meeting Room, Yacht, Island Venue, Garden Venue, Lounge, Unique Venue) **+ Villa +
Warehouse** (real types in the legacy data).

**`emirates`** — `id`, `name`, `slug`, `sort_order`. Seed the 8 (Dubai, Abu Dhabi, Sharjah, Ajman, Ras Al
Khaimah, Fujairah, Umm Al Quwain, Al Ain). *(Area-level sub-locations deferred; add an `areas` child table
in a later phase.)*

*Guest-count and budget ranges are fixed enums used by filters/enquiries — kept in app config, not tables
(they don't change): guests <25 / 25–50 / 51–100 / 101–200 / 201–500 / 501–1000 / 1000+; budget = Price on
request / Budget-friendly / Mid-range / Premium / Luxury / Minimum spend / Package.*

---

## 2. Core catalogue tables

**`partners`** (← legacy `providers`, 71) — `id`, `slug`, `org_name`, `partner_group` (from
`provider_goups`), `contact_name`, `email`, `phone`, `website`, `emirate_id` FK, `city_text`, `about`
(sanitized HTML), `logo_path`, `status` ENUM(draft/pending/approved/suspended), `is_featured`, `notes`
(admin-only), `created_at`, `approved_at`. *(Legacy commission/promo fields → `notes` or dropped — not in
the lead-gen scope.)*

**`venues`** (← legacy `venues`, 74) — the catalogue centre:
- Identity: `id`, `slug` (from name), `partner_id` FK (resolved from legacy `provider` text), `name`,
  `status` ENUM(draft/pending/published/needs_changes/archived) (from legacy `status` int), `is_featured`
  (from `mainpage`), `is_verified`.
- Classification: `venue_type_id` FK (mapped from legacy `type`), `indoor_outdoor`
  ENUM('indoor','outdoor','both') (mapped from legacy `category`), `emirate_id` FK (from `city`), `area`,
  `address`, `map_embed`/`lat`/`lng` (from `map`).
- Content: `description` (sanitized HTML, from `venue-theme`/`setting`/`style`), `best_for` (from
  `ideal-for`), `facilities`, `food_beverage` (from `food`/`beverages`), `av_support` (from
  `audio-/video-facility`/`lighting`), `restrictions`, `packages`, `special_offer` (+ `atv_special_offer`),
  `video_url`.
- Capacity/price: `capacity_max` (from `capacity`), `capacity_min` (from `minimum-guest`), `pricing_level`
  (derived), `minimum_spend` (from `minimum-spending`).
- ATV: `atv_rating`, `atv_review` (sanitized).
- `main_image` (from `main-photo`), `created_at`, `updated_at`, `published_at`.
- **Dropped:** the detailed seasonal/weekend pricing matrix (`hs/ls-we/wd-*`) — collapses to
  `pricing_level` + `minimum_spend` + `packages` (confirmed simplification).

**`venue_layout_capacity`** (← the 8 `capacity-*` columns → rows) — `id`, `venue_id` FK, `layout_type`
(Banquet, Reception, Theatre, Classroom, Cabaret, Boardroom, U-shape, H-shape), `capacity`. One row per
non-zero legacy capacity.

**`venue_event_types`** (M:N) — `venue_id` FK, `event_type_id` FK. Which event types a venue suits (seeded
from legacy `ideal-for`/`setting` where mappable; else admin-tagged later).

**`venue_images`** (← legacy `venue_images`, 257) — `id`, `venue_id` FK, `file_path`, `thumb_path`,
`alt_text` (from `title`/`message`), `is_primary`, `sort_order`, `status`, `created_at`.

**`venue_documents`** (← legacy `venue_document`, 144) — `id`, `venue_id` FK, `title`, `file_path`,
`created_at`.

---

## 3. People / access

**`users`** (← legacy `admin`, 2) — `id`, `name`, `email`, `password_hash` (**rehash MD5→bcrypt on first
login**, or force-reset), `role` ENUM(admin/editor/partner), `status`, `partner_id` FK (null for staff),
`created_at`, `last_login_at`. *(Public `user` table, 66 rows — archived, not migrated: no public accounts
in Phase 1.)*

**`audit_log`** (new) — `id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_value_json`,
`new_value_json`, `ip_address`, `created_at`.

---

## 4. Enquiries / leads

**`enquiries`** (← legacy `inquiry`, 2,378 — migrated in U3 when the form lands) — `id`, `reference`
(generated), `name` (←`username`), `email` (←`useremail`), `phone` (←`usermobile`), `company`,
`event_type_id` (mapped from `event-type`), `event_date`, `date_flexibility`, `emirate_id`/`city_pref`,
`guest_count` (←`guests`), `budget_range`, `venue_preference`, `indoor_outdoor`, `fb_requirements`,
`av_requirements`, `notes` (←`message`), `consent_to_share`, `source_page`, `status`
ENUM(new/reviewed/forwarded/accepted/contacted/won/lost/closed/spam), `created_at` (←`inquiry_date`),
`updated_at`.

**`enquiry_venues`** — `id`, `enquiry_id` FK, `venue_id` FK. Legacy `inquiry.venueid` → one row each;
new multi-venue enquiries → many.

**`lead_routing`** (new, admin forwards a lead) — `id`, `enquiry_id` FK, `venue_id` FK, `partner_id` FK,
`routed_to_email`, `status`, `routed_at`, `response_at`, `admin_note`.

---

## 5. Phase-2 (define later, not built in U1)

`venue_claims` and `venue_change_requests` (with `proposed_changes_json`) — built with the partner portal
in Phase 2. Noted here so the schema anticipates them.

---

## 6. Migration plan (legacy → new)

**Migrate in U1 (catalogue):**
- `providers` (71) → `partners`; `provider_goups` (6) → `partner_group`.
- `venues` (74) → `venues` + `venue_layout_capacity` (+ `venue_event_types` where mappable).
- `venue_images` (257) → `venue_images`; `venue_document` (144) → `venue_documents`.
- **Re-tag the 74 venues** with this confirmed mapping (legacy distinct values → new):
  - `category` (indoor/outdoor) → `venues.indoor_outdoor`: `Indoor`→indoor; `Outdoor`→outdoor; any
    `Indoor with Outdoor*`→both.
  - `type` → `venue_type_id`: Ballroom→Hotel Ballroom; Restaurant→Restaurant; Beach→Beach Venue;
    Garden→Garden Venue; Island→Island Venue; Meeting Room→Meeting Room; Yacht→Yacht; Villa→Villa;
    Warehouse→Warehouse; Art Gallery / Museum / Theater / Other → Unique Venue.
  - `city` → `emirate_id` 1:1 (normalize `Ras al Khaimah`→`Ras Al Khaimah`).
  - `providers.type` (Hotel/Resort/Restaurant/Art Space/Government/Warehouse Venue/Other) → optional
    `partners.org_type` free-text note (not a hard taxonomy).
- `admin` (2) → `users` (role=admin).

**Migrate in U3 (with the enquiry system):** `inquiry` (2,378) → `enquiries` + `enquiry_venues`. **The
`inquiry` table is heavily polluted with sqlmap/SQL-injection probe rows** (payloads stored in `event-type`
etc.), so the real lead count is far lower — the transform must **filter to genuine enquiries** (drop rows
whose fields match injection/junk signatures or have invalid emails) and map real `event-type` values to
`event_types` then.

**Archived / out of scope:** `user` (66, no public accounts P1), `reviews`/`rating` (defer P4),
`vendors` (13, out of scope), `favourite`/`subscriber`/`parternships`/`venue_events` (trivial/empty).

**Transformations:** generate slugs (venues/partners/taxonomy); **latin1 → utf8mb4** on import (fixes
smart-quote/encoding mojibake); the 8 `capacity-*` → layout rows; sanitize the rich-text HTML fields on
import (HTML Purifier or a strict allowlist — closes the deferred stored-XSS surface at the source);
resolve `venues.provider` text → `partner_id`.

**Image files:** the 257 images + 144 docs live on the legacy server under
`public_html/allthevenues/images/`. Migration is a **server-side copy** of the referenced files into the
new app's `uploads/` (or an `assets/venues/` tree) + path rewrite in `venue_images`/`venue_documents`
(optimize/thumbnail as we go). This runs on the server, not from git.

**Method:** idempotent, re-runnable transform scripts in `db/` that read the legacy DB (or the exported
`.sql`) and write into `sameraou_atv2`. Re-runnable so we can iterate on the mapping safely.

---

## 7. Open for your sign-off

- The **table set + field choices** above (esp. dropping the seasonal-pricing matrix, and
  archiving public `user`/`reviews`/`vendors`).
- The **rich-text handling**: sanitize-on-import (recommended) vs store-plain — affects venue descriptions,
  packages, offers.
- Whether **`venue_documents`** (floorplans/PDFs) stays a Phase-1 feature or defers (144 files).

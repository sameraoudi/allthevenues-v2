# ATV-U6-AUDIT.md — Pre-launch audit + apex cutover

*The launch runway for backlog #8 / U6. We tick through this together; it's the acceptance criteria for
going live on the apex domain. Pairs with `CLAUDE.md` (§4 deploy model), `VISION.md` (non-negotiables),
`Memory.md` (current state), and `docs/ATV-BACKLOG.md` (#8 + #9).*

**Owner tags:** `[Cowork]` I analyse / write the spec · `[CC]` a Claude Code build order · `[Samer]` a manual
prod / content / hosting action · `[verify]` paste evidence before ticking. **Nothing ticks on an assumed
result.** Launch = every **Critical** item passes + a tested backup/rollback in hand.

Status key: `[ ]` open · `[~]` in progress · `[x]` done (dated).

---

## Phase 0 — Blockers & pre-flight (do first; these gate everything)

- [x] **Legal sign-off** `[Samer]` (5 Jul 2026) — reviewed / decided **not blocking**; launching on the
  current Terms / Privacy / Cookie drafts. Placeholders still to confirm filled (date, contact email, address).
- [ ] **Image rights (#9) minimum** `[Samer]` **CRITICAL for provider registration** — onboarding consent
  live on the partner form + Terms §10 licence clause published. (Full provenance schema/admin is a separate
  #9 unit; only the consent wording gates launch.)
- [ ] **Prod config present** `[Samer][verify]` — `config/config.php` has prod DB creds, `BASE_URL`
  (apex), mail creds, **Turnstile keys valid for the apex domain**. Confirm it's the apex, not staging.
- [ ] **Backups captured** `[Samer]` **CRITICAL** — full `sameraou_atv2` DB dump + a docroot/`uploads/`
  snapshot, stored off-server, *before* cutover. (Rollback plan in Phase 9.)

---

## Phase 1 — Functional QA (core loops on staging)

- [ ] Home → venues browse; filters (event type, emirate multi, guests, indoor/outdoor, price), active-filter
  chips, sort, pagination, keyword search `q` (name / provider / location) `[verify]`
- [ ] Venue detail: gallery + lightbox, sticky tabs, Key Info, highlights, map embed, website link, similar
  venues `[verify]`
- [ ] Enquiry — every mode: venue, assisted, partner (`?provider=`), shortlist (multi-venue), contact,
  become-a-venue-partner → each lands in the admin inbox with the right mode badge `[verify]`
- [ ] Shortlist: heart toggle, `/shortlist` page, single enquiry → N `enquiry_venues` rows `[verify]`
- [ ] Providers directory + provider detail (cover/avatar, "Venues by this provider", managed enquiry panel);
  `/partners` → `/providers` 301 `[verify]`
- [ ] Event Types + Locations mosaics; gating on ≥1 published venue `[verify]`
- [ ] Admin: login/RBAC (admin vs editor), inbox list/filter/detail/status/notes/forward/CSV, delete
  (admin-only), venue create/edit/images, provider create/edit/cover, reports, users `[verify]`
- [ ] **Gates:** an unpublished venue / unapproved provider → **404 for non-admins** across list, detail,
  and enquiry (server-gated, not just hidden) `[verify]`
- [ ] Branded 404 on all not-found routes `[verify]`

## Phase 2 — Content QA `[Samer]`

- [ ] Published venue set is correct; no test/placeholder venues leaking
- [ ] Event-type tagging: the 2 untagged venues tagged; empty event types filled or their tiles stay hidden
- [ ] Provider data: remaining emails filled; ~4 NULL `partner_id` venues reconciled; types / Verified set
  where known
- [ ] Imagery: weak/placeholder venue + provider covers replaced where possible; image-rights pass underway
  (#9 content track)
- [ ] Legal pages read correctly from `docs/legal/*.md`; About/Contact accurate

## Phase 3 — SEO — ✅ (staging-verified 5 Jul 2026; apex-host items reconfirm at cutover)

- [x] Every page emits meta description + self-ref canonical (via `base_url`) + OG/Twitter (`views/layout.php`).
- [x] `/sitemap.xml` (hubs + published venues + approved providers + qualifying landings) + domain-agnostic
  `/robots.txt` (disallow `/admin`, sitemap pointer) — built in U5; **re-confirm they emit the apex host at
  cutover** (depends on apex `BASE_URL`).
- [x] FAQPage JSON-LD + landing gating (≥ `LANDING_MIN_VENUES`) — built in U5.
- [x] **Indexability flip** (commit `4a0f81b`) — staging (`staging.` host) forced `noindex,nofollow` +
  `X-Robots-Tag`; apex/other hosts emit `index,follow` (no hardcoded noindex → apex indexes day one); per-page
  overrides preserved on production; robots.txt left crawlable so Google can see the staging noindex. *Pending
  staging deploy + curl-verify.*
- [ ] Google Search Console verification file present on apex; submit the new sitemap post-cutover `[Samer]`

## Phase 4 — Legacy URL preservation → 301 map **CRITICAL** — ✅ built (commit `a7ea3c9`), pending prod apply

- [x] **Enumerate legacy URLs** (5 Jul 2026) — from GSC exports + legacy `sitemap.xml`. Actual patterns:
  `venue.php?venueid=N` (31 indexed), `provider.php?pid=N` (22), `venues.php`(+qs), `providers.php`,
  `about.php`, `legal.php`, `help.php`, `whydubai.php`. (~319 indexed total; 543 dup-no-canonical + ~2004
  crawled-not-indexed = old filter-combo/sqlmap junk → collapse to `/venues`, no per-URL redirects needed.)
- [x] **Mapping** — venue→`/venues/{slug}`, provider→`/providers/{slug}` (resolve by `legacy_id`);
  venues.php→`/venues`, providers.php→`/providers`, about.php→`/about`, legal.php→`/terms-of-use`,
  help.php→`/contact`, whydubai.php→`/about`. Unresolved/unpublished ID → 301 to the hub (not 404).
- [x] **Implement** — migration 016 (`legacy_id` on venues+partners, UNIQUE nullable); `db/backfill_legacy_ids.php`
  (name+owning-provider match, dry-run default, reports matched/ambiguous/unmatched); `lib/legacy_redirect.php`
  (early `index.php` hook, real 301s by `legacy_id`); `.htaccess` fixed-path 301s. Tested: ID 301s single-hop,
  statics on real httpd 2.4, hub fallbacks.
- [x] **PROD APPLY + verified on staging** (5 Jul 2026) — 016 applied; backfill dry-run **168/169** auto-matched
  (venues 98/98, providers 70/71, 0 ambiguous); `--commit` written. Only straggler = legacy #62 (Caesars Palace
  Dubai, since **renamed to Banyan Tree Dubai** slug `banyan-tree-dubai`) hand-set `legacy_id=62`. Deployed +
  LiteSpeed flushed. curl proof: `venueid=9`→`/venues/grand-ballroom`, `pid=1`→`/providers/jumeirah-zabeel-saray`,
  `venues.php?ideal=Wedding`→`/venues`, `legal.php`→`/terms-of-use`, `venueid=999999`→`/venues` (hub) — all
  single-hop 301. `pid=62`→`/providers` hub because that provider is `status=draft` (correct: never 301 to an
  unpublished page; not indexed anyway). **Content decision:** approve Banyan Tree Dubai if it should be public.

## Phase 5 — Analytics

- [ ] GoatCounter live on apex; key events fire: enquiry submit (per mode), shortlist add, primary CTAs
  `[verify]`
- [ ] CSP `connect-src`/`img-src` allows GoatCounter on the apex `[verify]`

## Phase 6 — Security review **CRITICAL** — ✅ swept 5 Jul 2026 (commit `589785e`)

- [x] **Tailored CSP** — already tight + domain-agnostic; added a defensive `Header always unset
  Content-Security-Policy` before the `set` (apex parent-docroot guard). `default-src 'self'`, no inline
  script/style, `frame-src` keeps Turnstile + `www.google.com`, GoatCounter allowed. *Staging shows exactly
  one CSP header. **Still open until cutover:** confirm the apex response yields one clean CSP (Phase 9).*
- [x] CSRF + rate-limit + Turnstile on every public form — enquiry (all modes via one handler), contact,
  become-partner each call `csrf_validate` + `ratelimit_hit` (IP+email) + `turnstile_verify`.
- [x] RBAC fail-closed on all `/admin/*` (every dispatch path → `auth_require_role`; users/settings admin-only;
  only login/logout ungated); admin layout emits `noindex,nofollow`; robots disallows `/admin`.
- [x] Uploads non-exec — `uploads/.htaccess` denies php/phtml/phar/cgi + `Options -ExecCGI`; `lib/upload.php`
  allowlist jpg/png/webp + `getimagesize` + `random_bytes` names + WebP re-encode.
- [x] No error-detail leakage — all app `getMessage()` calls sit inside `error_log`; 20 `catch(Throwable)` /
  41 `error_log`, generic user messages. Secrets: `config/config.php` gitignored; `/docs` + `*.md` + dotfiles
  403'd.
- [x] Session hardening intact (app-owned `storage/sessions` 0700, secure/httponly/samesite — from U0).
- [x] Flagged legacy XSS closed — `html_sanitize` allowlist unwraps `javascript:`/`data:`/protocol-relative
  anchors, strips all attributes + `on*` handlers, drops `<script>`/`<style>` with contents, adds
  `rel="noopener nofollow"`. XSS harness passed.

## Phase 7 — Performance

- [ ] LiteSpeed cache on; images WebP + sized (full ≤2000 / thumb ≤600); no CDN/external asset deps `[verify]`
- [ ] Reasonable page weight on venues list + detail; lazy-loaded images `[verify]`

## Phase 8 — Mobile QA `[Samer][verify]`

- [ ] Header nav (hamburger panel), venue cards, filters (mobile toggle), enquiry stepper, shortlist,
  admin tables — all usable at phone widths

## Phase 9 — Cutover (staging → apex)

- [ ] **Rollback plan written + tested** `[Samer]` **CRITICAL** — exact steps to restore legacy docroot +
  DB from the Phase-0 backup if launch goes wrong; know the DNS/docroot revert.
- [ ] Decide + document the cutover mechanic `[Samer][Cowork]` — how the apex is served today (legacy
  docroot) vs how ATV takes it over (docroot swap / DNS / vhost). **← needs your input (see below).**
- [ ] Swap `BASE_URL` + config to apex; apex Turnstile keys; canonical + sitemap emit the apex host
  `[Samer][verify]`
- [ ] Deploy HEAD to the apex docroot (`.cpanel.yml` rsync, `--no-perms`); **flush LiteSpeed** `[Samer]`
- [ ] Retire legacy code/docroot (keep the backup) `[Samer]`
- [ ] **Post-cutover smoke test** `[Samer][verify]` — home, a venue, an enquiry end-to-end (lands in inbox +
  mail sends), a legacy 301, sitemap/robots, HTTPS + security headers, no console/CSP errors
- [ ] Resubmit sitemap to Search Console; watch Coverage `[Samer]`

## Phase 10 — Post-launch watch (first 72h)

- [ ] Monitor `error_log`, 404s, Search Console crawl errors, first real leads + mail delivery `[Samer]`

---

## Open decisions (need Samer before we can finish Phase 4 & 9)

1. **Legacy URL inventory** — can you export the indexed legacy URLs (Search Console → Pages, and/or the old
   sitemap.xml)? That's the raw material for the 301 map.
2. **Cutover mechanic** — is the apex `allthevenues.com` currently served from the legacy docroot on the
   *same* cPanel account, and do we take over by pointing the apex docroot at the ATV repo (vs a DNS move)?
   This decides the exact cutover + rollback steps.
3. **Launch gate on legal** — is the UAE legal review already underway, or is that the critical-path blocker?

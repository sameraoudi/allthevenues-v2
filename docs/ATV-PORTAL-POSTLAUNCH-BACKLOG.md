# ATV-PORTAL-POSTLAUNCH-BACKLOG.md — provider portal, first QA pass (post go-live)

*Triaged from Samer's hands-on QA after the U-P9d go-live (portal LIVE, `PORTAL_ENABLED=true`).
20 items → grouped into build-sized workstreams with a recommended sequence. Priorities are
Samer's. Pairs with `Memory.md` and the space memory `atv-up9-launch`. Each item keeps its QA
number for traceability. "PU-*" = post-launch unit.*

---

## Verified working (document only)

- **#1 Partner onboarding flow — PASSED.** Admin create → invite email → set-password link → login all work.
- **#12 Partner disabling — PASSED.** Disabled partner can't log in; data intact; re-enable works; safe message.

---

## Workstream A — Portal shell, navigation & branding (PU-A)
*One design-led unit: rebuild the portal chrome so it feels like a real app, aligned to ATV branding.*

- **#7b (High) Label active requests in the My-Venues list** (Samer, post-delisting) — show a per-venue badge when
  a venue has an open change request / pending delist / needs-changes / under-review, so partners see state at a glance.
- **#7 (High) Dashboard + left sidebar** — admin-style simplified shell. Menu: Dashboard · My Venues · Add Venue ·
  Claims · Photo Submissions · Account · Guide · Sign out. Dashboard tiles: venues managed / pending review /
  open change requests / claims pending / photos awaiting review / recent decisions / next steps. Current page
  highlighted; mobile nav handled.
- **#2 (High) Logout button + header height** — logout is oversized, header too tall. Compact, balanced, consistent.
- **#4 (High) "Provider" → "Partner" copy** — partner-facing portal UI + partner emails use "partner". DB stays
  `provider`/`partners`; public "Venue Providers" stays. Review admin copy for consistency.
- **#5 (Med) Logo/wordmark alignment** — match the public site header; label the area "Partner Portal".
- **#6 (Low-Med) Minimal portal footer** — match site footer style; Privacy / Terms / Contact; dynamic year; no social.
- **#8 (Med) Guide/Help page** — plain-language: manage venues, submit a venue, how review works, upload photos,
  image-rights rules, claim a venue, what happens after you submit, what ATV reviews before publishing. A menu item.
- **#16 (Med) Style the "Choose Files" control** on the photo upload to match portal buttons (keep drag-drop + multi).

## Workstream B — Authentication (PU-B)

- **#3 (CRITICAL) Forgot-password / reset flow** — "Forgot password?" on login → enter email → if it exists, email a
  reset link (no enumeration) → one-time, 48h-expiry token → set new password → log in. Expired/used/invalid states;
  rate-limited. **Reuses the U-P9a `password_tokens` table (`purpose='reset'` already in the enum) + `lib/password_token.php`
  + the `/set-password` page pattern** — most infra exists. Cover partner AND admin/staff users.

## Workstream C — Claims polish (PU-C)

- **#10 (High) Preserve claim history** — proof submission must not overwrite the original claim. Keep a timeline:
  initial claim (role/email/message/proof) → admin requested proof (+note) → partner added proof → final decision,
  with timestamps. Both partner + admin see the full history. *(Store as an append-only events array in the claim's
  `proposed_changes_json`, or an audit-backed timeline.)*
- **#11 (High) Show rejected claims to partner** — "Your Claims" must show Rejected + review date + admin reason
  (not silently hidden). Statuses clear: Pending / Proof requested / Approved / Rejected / Withdrawn.
- **#9 (High) Proof submission UI** — compact venue summary; show admin's request note prominently; large proof-message
  textarea + clear proof-link field; comfortable resubmit. (Optional file upload later.)

## Workstream D — Add / Edit venue flow (PU-D)

- **#15 (CRITICAL) Three-step Add-Venue** — Step 1 details→draft (NOT submitted; UI says photos required),
  Step 2 → straight to photo upload (≥1 with rights confirmation), Step 3 submit for review. Don't create the
  admin review request until a photo exists. **MVP rule (Samer): allow submit after ≥1 photo uploaded, block admin
  "Approve & publish" until ≥1 image is approved** (the #9c publish gate already enforces the approved-image half).
- **#18 (High) Layout & capacity on Add-Venue** — same component as Edit (`venue_layout_capacity`): max capacity +
  per-layout capacities (+ notes); visible to admin at review.
- **#19 (High) Validate layout ≤ max capacity** — client + server; block submit; clear message; flag existing
  invalid data for admin.
- **#13 (High) "Best for" vs "Event types" duplication** — Event types is the structured taxonomy. Remove partner-facing
  "Best for" tagging OR redefine it as a short ATV-controlled editorial text field (not partner taxonomy). Make
  Add + Edit consistent. *(`best_for` is the legacy field the event-type tags were seeded from — now redundant on the
  portal form.)*
- **#17 (High) Event types on Edit for legacy/published venues** — the fieldset shows for new/draft but reads-only /
  absent on published (legacy) venues by the U-P9b governance rule. This IS the flagged fast-follow:
  **published-venue event-type CHANGE REQUEST** (extend U-P5 submit + U-P5b admin-apply to carry the event-type set),
  so partners can propose event-type changes on live venues for admin approval. Ensure the fieldset renders
  consistently (editable when non-public; request-to-change when published).
- **#14 (High) Error/failure banner** — a red error banner mirroring the success bar (e.g. invalid Google-Maps iframe):
  explain the failure, highlight the field, KEEP entered data. Applies across portal forms.

## Deferred / new (from PU-D1 testing)

- **Partner draft delete** — DONE in PU-D1-fix (draft-only hard delete; owner + status='draft' guarded).
- **Venue DELISTING (reversible)** — supersedes "request removal." Partner can't hard-delete a published venue;
  instead **request delisting** (reversible take-down) with re-list. Design LOCKED (space memory
  `atv-venue-delisting`): new `delisted` status + `delisted_at/by/reason`; `venue_change_requests type='delist'`
  (admin-approved); **self-serve re-list**; delisted → 404. Approved partners via the portal → Change Requests;
  non-approved via a new public contact menu item; admin can delist/re-list directly via the status dropdown.
  Build after PU-D1-fix (likely 2 sub-units).

## Workstream E — Admin review (PU-E)

- **#20 (High) Link new-venue review → image approval** — when publish is blocked on images, link admin straight to
  that venue's pending photos (filtered by venue id) with a CTA; if none uploaded, show "ask partner to upload"; refresh
  image-readiness after approval. Removes the manual hunt in Provider Photos.

---

## Recommended sequence

1. **PU-B #3 reset password (CRITICAL, quick)** — real gap for live users; reuses the U-P9a token infra, so high
   value / low cost. Do first.
2. **PU-D venue flow (#15 CRITICAL + #14/#18/#19/#13/#17)** — the submission flow is where partners spend real effort
   and where correctness matters (publish gate, capacity integrity, taxonomy). #15 anchors it.
3. **PU-A portal shell (#7 + #2/#4/#5/#6/#8/#16)** — one design-led pass; big perceived-quality uplift; everything
   here lives in the new shell so build it together.
4. **PU-C claims polish (#10/#11/#9)** + **PU-E #20** — quick, high-value correctness/visibility wins; can slot
   alongside the above.

Each unit keeps the loop: design preview (where visual) → approve → CC build order (schema-before-code) → deploy →
closeout. All of this is now on the LIVE portal, so verification rigor matters more than during the dark build.

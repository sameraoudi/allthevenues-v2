# All The Venues — Terminology, Tiering & Monetization Model

*Canonical spec for the commercial layer. Consolidates Samer's 3 Jul 2026 platform review. This is the
**what & why**; individual CC build units turn slices of it into code. Status: draft for sign-off —
labels + Phase-1 scope locked once Samer approves; the ranking formula + billing are Phase-2/3.*

---

## 1. Terminology (rename Partner → Provider/Partner split)

The word "Partner" is currently overloaded — it labels every provider whether paid or not. Split it:

| Public label | Internal meaning | Paid? |
|---|---|---|
| **Venue Provider** | Any hotel, resort, restaurant, operator or venue owner in the directory | No — neutral baseline |
| **Verified Provider** | ATV has reviewed/confirmed the provider | Trust layer — *editorial, not paid* (preserves trust) |
| **Venue Partner** | An approved paid/commercial relationship | Yes |
| **Featured Partner** | Paid provider-level visibility boost | Yes |
| **Premium Partner** | Top commercial package | Yes |

**Rules**
- Public **directory page** label → **"Venue Providers"** (not "Partners").
- Call a card a *Partner* only when the relationship is paid; unpaid = *Provider*.
- Public-facing badges stay simple: **Featured**, **Verified**, **Premium Partner**.
- Business CTA → **"Become a Venue Partner"**.

**Route decision (open):** keep `/partners` or move to `/providers` (301 the old path). Recommendation:
move to `/providers` for terminology consistency, with a 301 — low cost now, higher later.

---

## 2. Tiers

### Venue tiers (per venue)
| Tier | Public label | Placement behaviour |
|---|---|---|
| Standard | *(none)* / "Listed" | Ranked by relevance/completeness |
| Verified Venue | **Verified** | Trust badge + small ranking boost |
| Featured Venue | **Featured** | Boosted within top results; eligible for homepage featured slots |
| Premium Featured | **Featured** | Eligible for first-row boost + homepage rotation |
| Venue of the Month | editorial | Homepage editorial feature + landing-page promotion |

### Provider tiers (per provider) — kept more conservative
| Tier | Public label | Placement |
|---|---|---|
| Standard Provider | *(none)* | Normal listing |
| Verified Provider | **Verified** | Badge + modest ranking boost |
| Featured Partner | **Featured Partner** | Boosted in top results |
| Premium Partner | **Premium Partner** | First-row eligibility + venue-level benefits |

Internal tiers need not all be visible; surface only Featured / Verified / Premium Partner.

---

## 3. Placement model

**Homepage** (keep the current 3 cards, add an editorial slot):
- **Featured Venues** — 3 slots, monthly paid, rotated monthly.
- **Venue of the Month** — 1 larger editorial feature, premium monthly sponsorship.
- Rule: never show *only* paid venues with no relevance logic — the homepage must still feel curated.
  Subtle "Featured" disclosure where paid.

**Venues page** (hybrid ranking — the most trust-sensitive surface):
- Row 1 (3 slots): premium visibility — slot 1 best relevance match (boostable if premium), slots 2–3
  featured venues.
- Rows 2–4: relevance-led (filters + completeness + quality + light commercial boost).
- Do **not** sell all 12 positions equally — that makes the page feel like ads.

**Providers page** (credibility/SEO, secondary journey — conservative):
- 2 Premium Partner slots in row 1; up to 4 Featured Partner slots within the top 12; the rest by
  relevance/location/type.

---

## 4. Recommended ("relevance") sort — Phase-2 ranking

Default sort blends signals so monetization never makes results feel fake:

```
score = relevance_to_filters
      + profile_completeness
      + image_quality
      + verified_status
      + featured_boost
      + freshness (recently updated)
      + editorial_priority
```

Paid placement *increases visibility* but must not override relevance to the point of eroding trust.

---

## 5. Commercial packages (Phase-3 monetization)

| Package | Includes |
|---|---|
| **Free / Standard Provider** | Profile, venue listings, basic (admin-routed) enquiries, no prominent placement, no badge |
| **Verified Provider** | Verified badge, ATV-reviewed profile, small ranking boost, eligible for paid featuring *(consider free/editorial to preserve trust)* |
| **Featured Venue** (monthly) | Featured badge, recommended-sort boost, eligible for homepage featured slots, performance summary |
| **Featured Partner** (monthly/quarterly) | Featured Partner badge, providers-page boost, enhanced profile, multi-venue visibility, enquiry reporting |
| **Premium Partner** (quarterly/annual) | Provider first-row eligibility, homepage venue rotation, Venue-of-the-Month eligibility, featured badges on selected venues, priority lead routing, better reporting, quarterly editorial refresh |

**Billing:** monthly for homepage/venue featured slots + Venue of the Month; monthly/quarterly for
providers-page featuring; quarterly/annual for Premium Partner (reduces churn). Use flexible campaigns,
not everything calendar-locked.

---

## 6. Schema implications (to design when we build the commercial layer)

- **venues:** already has `is_featured`, `is_verified`. Add a `tier` ENUM (standard / verified /
  featured / premium_featured) or keep flag-based; add `venue_of_the_month` (bool or a dated campaign
  row); add `featured_from`/`featured_until` for rotated monthly slots; `editorial_priority` int.
- **partners (providers):** add a real **`is_verified`** (currently none — verification is inferred from
  `approved`), a `provider_tier` ENUM (standard / verified / featured_partner / premium_partner), and
  campaign date fields.
- **A campaigns/placements table** eventually (which venue/provider holds which paid slot, when) so
  rotation + billing are data-driven rather than flags.
- **Ranking** needs `profile_completeness` + `image_quality` to be computable (derive on write or a
  nightly job).

---

## 7. Phasing

- **Now (Phase 1 polish):** terminology rename (labels + CTA + page title, optional route move);
  homepage Featured Venues driven by `is_featured` (already a column) instead of any hardcoded set; the
  badge vocabulary (Featured / Verified). No paid mechanics yet.
- **Phase 2:** the recommended-sort ranking model; `tier`/`provider_tier` fields; Venue of the Month
  slot; admin controls to set tiers/featuring.
- **Phase 3:** the campaigns/placements table + billing + reporting; the partner portal self-serve.

Monetization mechanics are deliberately *not* Phase-1 — Phase 1 gets the language, the badge system, and
the DB-driven featured slot right so the commercial layer drops onto a clean foundation.

# VISION.md — allthevenues.com

*Strategic north star for ATV. Authoritative on conflict: when this and `CLAUDE.md`/`Memory.md` disagree,
**VISION wins** — re-prioritise the work, don't bend the vision. Changes rarely.*

---

## Mission

**Be the trusted way to find and book event venues in the UAE** — connecting event planners with the right
venue and provider, and giving venue providers qualified leads, through a curated marketplace that feels
premium and credible rather than like a listings dump.

Test every decision against one visitor: **a planner organising a wedding, corporate event, or party who
doesn't know where to start.** If a change helps them find the right venue faster and trust the result,
it's worth doing. If it clutters the page or erodes trust for short-term commercial gain, it isn't.

---

## The core promise — managed leads

ATV's defining mechanic: **enquiries route through ATV, not around it.** Public pages showcase venues and
providers but never expose direct contact details. One structured enquiry, and ATV connects the planner to
the right provider.

This is simultaneously:
- the **user promise** — share your details once, we help you reach the right people;
- the **provider value** — qualified, structured leads instead of scattered emails;
- the **business model** — because ATV owns the connection, it can monetize visibility and routing without
  charging users.

Protect this. Any feature that leaks provider contact or lets planners bypass the managed layer works
against the platform's reason to exist.

---

## Provider vs Partner — free directory, paid visibility

- Every venue operator is a **Venue Provider** — a free, neutral directory presence. The directory should be
  broad and credible on its own merits.
- **Verified Provider** is a **trust** layer, earned by ATV editorial review — kept **editorial, not paid**,
  so the badge stays meaningful.
- **Venue Partner** (Featured / Premium) is the **paid commercial** relationship — increased visibility and
  routing benefits.

Monetization must never make the results feel fake. Paid placement *boosts* visibility; it does not override
relevance to the point users stop trusting the page. Never sell every slot; the homepage and listings stay
curated and relevance-led. (Full model: `docs/ATV-TIERS.md`.)

---

## Design ethos

**Premium, calm, hospitality-grade.** The "Coastal UAE Soft Blue" system (Cormorant Garamond + Inter, soft
blue action colour, sand accents) should feel like a boutique hotel, not a SaaS dashboard. Lead with **real
imagery** — a provider's best venue photo over a placeholder, always. Gradients + initials are a rare
fallback, not the default. Restraint over decoration: a couple of meaningful badges, not a wall of them.

---

## Built clean

The rebuild exists because the legacy code carried systemic security debt (no prepared statements, no CSRF,
MD5 passwords, an unauth upload→RCE). ATV is held to a high hygiene bar from the ground up: prepared
statements everywhere, CSRF on every write, RBAC fail-closed, secure uploads, a tight self-only CSP, audit
logging, no error-detail leakage. Security is not a later phase — it's the foundation the commercial layer
sits on.

---

## Phasing (direction, not a schedule)

- **Phase 1 — public lead-gen + admin (MVP).** Browse venues/providers, structured enquiry, admin lead inbox
  + venue/provider management, SEO landing pages, launch on the apex domain. *(Nearly complete — see
  `Memory.md`.)*
- **Phase 2 — ranking + tiers + partner portal.** The relevance-led recommended sort, venue/provider tiers,
  Venue of the Month, and provider self-serve.
- **Phase 3 — monetization & lead ops.** Paid campaigns/placements, billing, lead routing/reporting, the
  commercial packages.
- **Phase 4 — advanced.** Reviews/ratings, richer analytics, area-level taxonomy, and beyond.

The Phase-1 architecture (RBAC, approval workflow, audit log, managed-lead entities) is built to accept
Phases 2–4 without a rewrite.

---

## Non-negotiables

1. **Trust > monetization.** Never trade user trust for a placement sale.
2. **Managed leads always.** No public provider contact; no bypass of the enquiry layer.
3. **Real over placeholder.** Prefer genuine imagery and honest data; flag data gaps, don't fake them.
4. **Secure by construction.** The site that brokers people's event enquiries holds its own hygiene to a
   high bar.
5. **Curated, not a dump.** Relevance-led surfaces; restraint in badges and CTAs.

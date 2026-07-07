# ATV-PORTAL-GOLIVE-RUNBOOK.md — U-P9d provider-portal go-live

*Short ops runbook for flipping the provider portal live. Pairs with `Memory.md` (state) and the
space memory `atv-up9-launch`. The portal has shipped inert behind `PORTAL_ENABLED` since U-P0; this
is the switch + smoke test + onboarding. Run it when U-P9a–c are deployed and verified.*

---

## 0. Pre-flight (confirm before flipping)

- [ ] **All portal units deployed:** U-P0→U-P8b + U-P9a/b/c on `origin/main`, deployed to the apex.
- [ ] **Migrations applied on prod:** `019` (change requests), `021` (image review), `022`
      (password_tokens). Quick check in phpMyAdmin: `SHOW TABLES` includes `venue_change_requests`,
      `password_tokens`; `SHOW COLUMNS FROM venue_images` shows `review_status`.
- [ ] **Turnstile:** `TURNSTILE_SITE_KEY`/`_SECRET_KEY` set in prod `config/config.php` and the widget's
      **Hostnames include `allthevenues.com`** (same host as the portal login — no extra hostname needed).
- [ ] **Mail works:** a recent admin/enquiry email arrived (invites + decision emails use the same
      `lib/mail.php`). Have an inbox ready to receive the test invite.
- [ ] **Flag is currently OFF:** `curl -sI https://allthevenues.com/portal/login` → **404** (branded).

---

## 1. Flip the flag (no deploy — config is prod-only)

`PORTAL_ENABLED` is read by `portal_enabled()`; undefined = OFF. Turn it on by editing the **prod-only,
gitignored** `config/config.php` (cPanel File Manager / SFTP) — do NOT commit this:

```php
define('PORTAL_ENABLED', true);
```

Then **flush LiteSpeed** (and if PHP OPcache is caching `config.php`, restart PHP / wait out the TTL so
the new constant is seen).

Verify the switch:

```
curl -sI https://allthevenues.com/portal/login      →  200 (login page, not 404)
```

Load the homepage → footer **"Partner login"** is now a real link to `/portal/login` (was
"Coming soon"). `/portal` while signed-out → redirects to `/portal/login` (not 404).

---

## 2. Smoke test the full loop (one throwaway provider) — BEFORE onboarding real providers

1. **Create a partner account:** Admin → Users → New account → role **Venue Provider** + pick a
   provider + a mailbox you control → *Create account & send invite*. Detail page shows
   **Active / Pending / Not set**.
2. **Invite email:** arrives (check spam); "Set your password" link opens `/set-password?token=…`.
3. **Set password:** complete the **Turnstile** + a valid password → lands signed-in in the portal;
   user detail now **Accepted / Set**.
4. **Provider actions:** in `/portal` — submit a test venue (with event types), upload a photo, submit
   a claim on another venue. Confirm they appear as **pending** and are NOT public.
5. **Admin review:** the new-venue request (Change Requests), the photo (Provider Photos), the claim
   (Change Requests) all show up → approve one end-to-end → confirm it goes live / reassigns as expected.
6. **Login hardening:** sign out → `/portal/login` shows Turnstile; a bad password is rate-limited +
   generic-errored.
7. **Clean up** the throwaway venue/claim/photo/account (or leave the account disabled).

If anything fails here, **roll back** (§4) and fix before onboarding.

---

## 3. Onboard real providers (content step)

For each provider: Admin → Users → New account (role Venue Provider + their provider) → invite is
emailed automatically. Do this **in batches** you can support; watch the first few complete set-password.
Remember: an admin **never** sees the provider's password (set-password link only).

---

## 4. Rollback (instant, safe)

Set the flag back off in prod `config/config.php` (`define('PORTAL_ENABLED', false);` or remove the
line) → flush LiteSpeed. `/portal/*` returns 404 again and the footer link reverts to "Coming soon".
**Data is preserved** — any partner accounts, pending venues, claims, and photo submissions remain in
the DB (just unreachable while dark) and resume when the flag is turned back on. No migration to undo.

---

## 5. First-week watch

- **error_log** for portal/mail/Turnstile errors.
- **Invite deliverability** (spam folder reports from providers) — adjust from-address/SPF if needed.
- **Queues:** Change Requests + Provider Photos actually get worked (SLA on provider submissions).
- **Turnstile** pass-rate on `/portal/login` (raise friction only if abuse appears).

---

## Known fast-follows (not blockers, track post-launch)

- **Published-venue event-type change request** (governed edit for live venues) — U-P9b shipped
  non-public editing only.
- **Admin event-type editor UI** (admins can't set event types except via the portal/seed).
- **`db/001_schema.sql`** never had 016–022 folded in (migrations are the source of truth) — a one-off
  "sync 001" task if fresh-import single-file parity is wanted.

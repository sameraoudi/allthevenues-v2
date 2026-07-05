# ATV Cutover Runbook — staging → apex (`allthevenues.com`)

*Phase 9 of `docs/ATV-U6-AUDIT.md`. Follow top-to-bottom. Do NOT skip a step; verify each before the next.
Every path/command is literal. If any check fails, STOP and use Rollback (bottom).*

---

## The approach (why this is safe)

`allthevenues.com` is an **addon domain** on this cPanel account. Today it serves the **legacy** site from
`/home1/sameraou/public_html/allthevenues` (legacy DB `sameraou_atv`). The **new app** already runs at
`/home1/sameraou/atv-staging` (DB `sameraou_atv2`, uploads inside it).

**Cutover = change the addon domain's Document Root** from the legacy folder to `/home1/sameraou/atv-staging`.

- **No data moves.** Same DB (`sameraou_atv2`), same `uploads/`, same code. Nothing is copied or migrated.
- **No DNS change.** The domain already resolves to this account — the switch is instant.
- **Fully reversible.** Rollback = point the Document Root back to the legacy folder. Legacy files + legacy
  DB `sameraou_atv` are never touched.

| Item | Value |
|---|---|
| Addon domain | `allthevenues.com` |
| Legacy docroot (rollback target) | `/home1/sameraou/public_html/allthevenues` |
| **New ATV docroot (cutover target)** | `/home1/sameraou/atv-staging` |
| App config file | `/home1/sameraou/atv-staging/config/config.php` |
| DB (reused, unchanged) | `sameraou_atv2` |
| Legacy DB (rollback, untouched) | `sameraou_atv` |

---

## Pre-flight — do + confirm BEFORE touching the docroot

- [ ] **P0. Backups in hand** — `atv2_db_2026-07-05.sql.gz` (1.6M) + `atv2_uploads_2026-07-05.tar.gz` (275M)
  taken and **downloaded to your computer**. ✅ (done 5 Jul 2026)
- [ ] **P1. Latest code deployed to `atv-staging`** — cPanel → Git Version Control → Update from Remote →
  Deploy HEAD. Confirm on the server:
  ```
  cd /home1/sameraou/atv-staging && git log --oneline -1
  ```
  Expect `8d54684` (forwarded-lead email fix) or newer.
- [ ] **P2. Pre-launch cleanup** — remove the leftover test file:
  ```
  rm -f /home1/sameraou/atv-staging/uploads/test.php
  ```
- [ ] **P3. Turnstile is valid for the apex** — in the Cloudflare **Turnstile** dashboard, open the widget
  whose keys are in `config.php` and confirm its **Hostnames** list includes `allthevenues.com` (add it if
  only `staging.allthevenues.com` is listed). If the keys are domain-locked to staging, the apex forms' bot
  check will fail. *(This is the most common cutover gotcha — verify it.)*
- [ ] **P4. Search Console verification survives the switch** — find how `allthevenues.com` is verified:
  ```
  ls -la /home1/sameraou/public_html/allthevenues/google*.html
  ```
  - If it lists a `google….html` file → that file lives only in the legacy folder; after cutover the apex
    serves `atv-staging`, so **copy it across** (Step C3 below) or GSC loses verification.
  - If the command finds nothing → verification is likely by DNS TXT (no file needed). Confirm in Search
    Console → Settings → Ownership verification.
- [ ] **P5. Locate the Document Root control** — in cPanel → **Domains**, find the `allthevenues.com` row and
  confirm you can see/edit its **Document Root** field (don't change it yet). If it's not editable there,
  STOP and tell me — we'll use the alternative.

---

## Cutover — execute in order

**C1. Back up the live config (so rollback is trivial):**
```
cp /home1/sameraou/atv-staging/config/config.php /home1/sameraou/atv-staging/config/config.php.bak-preapex
```

**C2. Point `BASE_URL` at the apex.** Edit `/home1/sameraou/atv-staging/config/config.php` (cPanel File
Manager → Edit, or `nano`). Find the `BASE_URL` line — currently the staging host — and change ONLY the host:
```
// before
define('BASE_URL', 'https://staging.allthevenues.com');
// after
define('BASE_URL', 'https://allthevenues.com');
```
Keep the exact same quoting/trailing-slash style as the original line. Verify:
```
grep BASE_URL /home1/sameraou/atv-staging/config/config.php
```
Expect to see `https://allthevenues.com`.

**C3. Copy the GSC verification file into the new docroot** (P4 confirmed `google5540984c536828b7.html`):
```
cp /home1/sameraou/public_html/allthevenues/google5540984c536828b7.html /home1/sameraou/atv-staging/
```
Verify: `ls -la /home1/sameraou/atv-staging/google5540984c536828b7.html`
*(Durable across deploys — the rsync has no `--delete`. Optionally commit it to the repo root later.)*

**C4. Switch the Document Root.** cPanel → **Domains** → `allthevenues.com` → **New Document Root** — the
field is HOME-RELATIVE (it currently shows `/public_html/allthevenues`), so enter exactly:
```
/atv-staging
```
Click **Update**. *(Rollback value, if needed: `/public_html/allthevenues`.)*

**C5. Flush LiteSpeed cache** (cPanel → LiteSpeed Web Cache Manager → Flush All, or the site's cache plugin).

---

## Smoke tests — run ALL, on the apex

```
# 1. Home: 200, exactly one CSP header, and NO x-robots-tag (apex is indexable)
curl -sI https://allthevenues.com/ | grep -iE 'HTTP/|content-security-policy|x-robots-tag'

# 2. Robots meta is index,follow on the apex
curl -s https://allthevenues.com/ | grep -i 'name="robots"'      # expect: index, follow

# 3. Legacy 301s work on the apex
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" "https://allthevenues.com/venue.php?venueid=9"
# expect: 301 -> https://allthevenues.com/venues/grand-ballroom

# 4. Sitemap + robots emit the APEX host
curl -s https://allthevenues.com/sitemap.xml | grep -m1 '<loc>'  # expect an https://allthevenues.com/... URL
curl -s https://allthevenues.com/robots.txt | grep -i sitemap    # expect https://allthevenues.com/sitemap.xml
```
Then in a browser on `https://allthevenues.com`:
- [ ] Home + a venue detail page load correctly (images, map).
- [ ] Submit a **real test enquiry** — the Turnstile check passes, it lands in `/admin` inbox, and the
  forward email sends with the full brief (F&B + AV).
- [ ] GoatCounter `count.js` loads (DevTools → Network) and a hit appears in the dashboard.
- [ ] `staging.allthevenues.com` still serves (now `noindex`) — harmless; can be left for future testing.

---

## Post-cutover

- [ ] Search Console: submit `https://allthevenues.com/sitemap.xml`; confirm ownership still verified.
- [ ] GoatCounter dashboard: turn **off** "ignore query strings" so `?submitted=1` conversions count.
- [ ] Leave the legacy folder (`public_html/allthevenues`) + legacy DB (`sameraou_atv`) in place for a few
  weeks as the fallback — do NOT delete yet.
- [ ] Monitor (first 72h): `error_log`, GSC Coverage (legacy URLs redirecting, new ones indexing), first
  real leads + mail delivery.

---

## Rollback (if any smoke test fails)

1. cPanel → Domains → `allthevenues.com` → **New Document Root** back to `/public_html/allthevenues` → Update.
2. Restore the staging config:
   ```
   cp /home1/sameraou/atv-staging/config/config.php.bak-preapex /home1/sameraou/atv-staging/config/config.php
   ```
3. Flush LiteSpeed.
4. Legacy site + `sameraou_atv` were never modified → the old site is instantly back. Then tell me what
   failed and we fix forward.

# All The Venues (v2)

A secure, from-scratch rebuild of allthevenues.com as a lead-generation
platform. Separate repository from the legacy `allthevenues` site.

## Stack

- Structured vanilla **PHP 8** — server-rendered, **no build step**
- **MySQL** (database `sameraou_atv2`)
- **Bootstrap 5**, self-hosted under `assets/` (no CDN)
- Hosted on cPanel / **LiteSpeed**
- Analytics: GoatCounter

## Architecture

The repository root **is** the deploy docroot. Requests are routed through
a single front controller:

```
index.php                 Front controller: hardened session + path router
.htaccess                 Security config (HTTPS, HSTS, CSP, headers, routing)
.cpanel.yml               cPanel Git deploy → /home1/sameraou/atv-staging
config/
  config.php              Secrets: DB creds + BASE_URL (gitignored, per-env)
  config.example.php      Committed template — copy to config.php
  db.php                  db_pdo() — hardened PDO factory
  .htaccess               Deny direct web access
lib/
  csrf.php                csrf_token / csrf_field / csrf_validate (session-based)
  helpers.php             e(), redirect(), base_url()
  .htaccess               Deny direct web access
assets/css|js|img         Self-hosted Bootstrap + site assets
views/
  layout.php              Base layout (header/footer partials)
  home.php                Placeholder homepage
  404.php                 Not-found page
  partials/, content/     Layout partials and page content
uploads/                  User uploads — script execution disabled (.htaccess)
db/                        Schema / migrations (added in U1)
```

## Local setup

```bash
cp config/config.example.php config/config.php   # then edit credentials
php -S localhost:8000 index.php                   # serve locally
```

## Deployment

Staging: **https://staging.allthevenues.com** (docroot
`/home1/sameraou/atv-staging`, outside `public_html`).

Push to `main`; cPanel Git Version Control runs `.cpanel.yml`, which
`rsync`s the working tree to the docroot. `config/config.php` is
gitignored and created once on the server.

> Deploy uses `rsync -rlt --no-perms --no-owner --no-group` — **never
> `-a`** (it clobbers permissions on this host).

## Security notes

- Hardened sessions: HttpOnly, Secure (on HTTPS), SameSite=Lax
- Tight CSP; only GoatCounter is allowed off-origin
- PDO with real prepared statements (`EMULATE_PREPARES = false`)
- Session-based CSRF tokens
- `config/` and `lib/` deny direct web access; `uploads/` disables script execution
- **No `php_flag` / `php_value`** in `.htaccess` (crashes this host)

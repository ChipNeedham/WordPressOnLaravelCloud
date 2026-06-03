# Spec: Foundation — Laravel-hosted WordPress skeleton

Sub-project #1 of "WordPress on Laravel Cloud" (see `../OVERVIEW.md`).
Date: 2026-06-03 · Status: Draft for review

## Purpose

Stand up the combined Laravel + WordPress skeleton so the site boots from a Laravel-style `.env`,
serves under a single front controller, answers Cloud health checks, and exposes `artisan`. This is
the base every later sub-project (object storage, cache, overlay) builds on. **No object storage in
this spec** — that is sub-project #2.

## Success criteria

A reviewer can verify all of these on the Herd dev environment (`http://wordpressonlaravelcloud.test/`):

1. `GET /up` and `GET /health` return `200` with body `OK`, **even with the database stopped**, and
   return *before* WordPress loads.
2. `php artisan --version` works from the repo root.
3. With valid `.env` DB credentials, the WordPress site front page and `/wp/wp-admin/` load with **no
   `setup-config.php` / install-wizard screen** ever appearing (config is always satisfied from env).
4. A one-shot `php artisan wp:install` creates the schema + admin user from env values, locale `en_US`.
5. Changing `DB_*` / salts / `WP_TABLE_PREFIX` in `.env` changes WordPress behavior accordingly — no
   WordPress core file contains credentials.
6. Static assets (e.g. `GET /wp/wp-includes/js/...`, `/wp-content/themes/.../style.css`) are served
   directly by the webserver (do not pass through PHP/WordPress).
7. WordPress core files under `public/wp/` are unmodified vs. upstream (verifiable by diff); all
   customization lives outside core.

## Target layout (end state)

```
/ (repo root)
  artisan
  composer.json  composer.lock
  .env  .env.example
  app/            # Laravel app code (minimal; our glue lands here in later specs)
  bootstrap/
    app.php
    laravel-boot.php   # NEW: idempotent partial-bootstrap helper (env+config+facades only)
  config/         # Laravel config
  routes/         # Laravel routes (unused by WP path in this spec)
  vendor/
  public/                     # WEB ROOT (Herd + Cloud document root)
    index.php                 # FRONT CONTROLLER
    wp/                        # WordPress core — VANILLA (moved from repo root)
      wp-admin/  wp-includes/  wp-load.php  wp-settings.php  index.php  wp-*.php
    wp-content/                # themes, plugins, mu-plugins, uploads (moved from repo root)
    wp-config.php              # thin shim (the only "wp-config" — replaces wp-config-sample)
```

WordPress core (`public/wp/`) stays byte-for-byte upstream. The two files we author are
`public/index.php` and `public/wp-config.php`, plus the Laravel skeleton and `bootstrap/laravel-boot.php`.

## Components

### C1. Composer + Laravel skeleton
- `composer.json` requires the **latest `laravel/framework`** (Laravel 12.x; PHP **8.2+**). WordPress
  7.1-alpha runs fine on PHP 8.2+. This satisfies Cloud's `composer.lock` check (Finding F1).
- Minimal standard Laravel skeleton: `bootstrap/app.php`, `config/`, `routes/`, `app/`, `artisan`.
- Autoloader at `vendor/autoload.php` is the shared autoloader for both Laravel and our shim.

### C2. Front controller — `public/index.php`
Responsibilities, in order:
1. **Health short-circuit, zero dependencies:** parse the request path; if it is `/up` or `/health`,
   send `200` + `OK` and `exit`. This happens *before* requiring the autoloader or booting anything,
   so health is immune to DB/dependency failures (Success #1).
2. Otherwise `require __DIR__.'/wp/index.php';` — hand the request to WordPress unchanged.

The Laravel container is **not** booted here; it is booted inside `wp-config.php` (C4), giving a single
boot site that also covers non-front-controller entry points (`wp-cron.php`, CLI). Future Laravel-routed
endpoints can be added here later without affecting this spec.

### C3. Partial Laravel bootstrap helper — `bootstrap/laravel-boot.php`
A function that boots **only** what we need and is **idempotent** (safe to call once per process):
- Creates the application from `bootstrap/app.php`.
- Runs a *curated* bootstrapper list via `$app->bootstrapWith([...])`:
  `LoadEnvironmentVariables`, `LoadConfiguration`, `RegisterFacades`, `RegisterProviders`, `BootProviders`.
- **Deliberately omits `HandleExceptions`** so Laravel does **not** take over PHP's error/exception/
  shutdown handlers — WordPress must retain control of error handling. (Decision recorded here; this is
  the key correctness constraint of the bootstrap.)
- Guards against double-boot with a static flag.
- Outcome: `env()`, `config()`, and facades (`Storage`, `Cache`) are available; nothing about request
  handling, routing, or middleware is engaged.

### C4. wp-config shim — `public/wp-config.php`
Runs when WordPress's `wp-load.php` locates it one level above `ABSPATH` (the standard "WordPress in its
own directory" mechanism — `ABSPATH` is `public/wp/`, set by `wp-load.php`; the shim lives at `public/`).
Steps:
1. `require dirname(__DIR__).'/vendor/autoload.php';`
2. Call `bootstrap/laravel-boot.php` (idempotent) to load `.env` + config.
3. **Fail fast on missing required config:** if `DB_DATABASE` (etc.) or `APP_KEY` is absent, throw a
   clear exception — never fall through to the install wizard (Success #3).
4. Define WordPress constants from env/config:
   - DB: `DB_NAME`←`DB_DATABASE`, `DB_USER`←`DB_USERNAME`, `DB_PASSWORD`, `DB_HOST`←`DB_HOST[:DB_PORT]`,
     `DB_CHARSET='utf8mb4'`, `DB_COLLATE=''`.
   - `$table_prefix = env('WP_TABLE_PREFIX', 'wp_');`
   - 8 salts from env (`AUTH_KEY` … `NONCE_SALT`).
   - URLs: `WP_HOME = APP_URL`, `WP_SITEURL = APP_URL.'/wp'`.
   - Content: `WP_CONTENT_DIR = dirname(ABSPATH).'/wp-content'`, `WP_CONTENT_URL = WP_HOME.'/wp-content'`.
   - `WP_DEBUG` from `APP_DEBUG`; `WP_ENVIRONMENT_TYPE` mapped from `APP_ENV`
     (`local|development|staging|production`).
   - Locale: `WPLANG`/site locale `en_US`.
5. `require_once ABSPATH.'wp-settings.php';` (standard tail).

### C5. Install command — `php artisan wp:install`
A thin Artisan command that boots WordPress far enough to run the native installer
(`wp-admin/includes/upgrade.php` → `wp_install()`) using env-provided values
(`WP_SITE_TITLE`, `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`, locale `en_US`). Idempotent:
detects an already-installed site and no-ops with a message. This replaces the interactive web install
and keeps setup reproducible in CI/Cloud (Success #4).

### C6. `.env.example`
Documents every variable used above with safe placeholders, including the 8 salts and the install
command's `WP_*` admin variables, plus `APP_URL=http://wordpressonlaravelcloud.test`.

## Data flow

```
Browser ─▶ webserver (root=public/)
   ├─ path is a real file (assets under public/wp, public/wp-content) ─▶ served directly
   └─ otherwise ─▶ public/index.php
        ├─ /up or /health ─▶ 200 "OK"  (no Laravel, no WP, no DB)
        └─ else ─▶ public/wp/index.php ─▶ wp-blog-header ─▶ wp-load ─▶ public/wp-config.php
                       └─ vendor/autoload ─▶ laravel-boot (env+config+facades) ─▶ define WP constants
                       └─ wp-settings.php ─▶ WordPress renders the request
```

## Error handling
- **Health is dependency-free** — never gated on DB/cache, so a downstream outage cannot trigger Cloud
  restart loops (Decision D5/D7 alignment).
- **Missing/invalid config fails loudly** in the shim rather than silently showing the setup wizard.
- **Laravel never owns PHP error handling** (C3) — WordPress's own error/debug behavior is preserved.
- **Idempotent bootstrap** prevents double-registration if multiple entry points boot in one process.

## Testing / verification
Automated (Pest/PHPUnit under Laravel) where it's cheap, manual curl for the integration points:
- **Unit:** `laravel-boot` loads env and exposes `config()`/facades; second call is a no-op; PHP's
  exception handler is unchanged after boot.
- **Unit:** given a fake env, `wp-config` maps every constant correctly and throws on missing DB creds.
- **Integration (curl vs Herd):** `/up` and `/health` → `200 OK` with MySQL stopped; front page → `200`
  after `wp:install`; `/wp/wp-admin/` reachable; a static asset under `public/wp/` → `200` without
  hitting PHP (verify via response headers / absence of WP cookies).
- **Command:** `php artisan --version` and `php artisan wp:install` (fresh DB → installs; re-run → no-op).
- **Vanilla check:** `git`-level confirmation that nothing under `public/wp/` differs from upstream.

## Out of scope (explicitly)
- Object storage / stream wrapper / MinIO (sub-project #2).
- Cache, object cache, sessions on Redis (sub-project #4).
- Plugin/theme/core overlay + hydration (sub-project #3).
- Octane / worker mode (ruled out for WP — Finding F3).
- Production Cloud deploy config (`build`/`deploy` commands, env provisioning) — documented when we
  first deploy; foundation only ensures the app is *shaped* to deploy (front controller, health, `artisan`).

## Open questions
- **Laravel skeleton acquisition:** scaffold a fresh `laravel/laravel` and merge, vs. add
  `laravel/framework` to a hand-written minimal skeleton. (Resolve in the implementation plan; end state
  is identical.)
- **Herd web root:** confirm Herd serves `public/` for this site automatically (expected for Laravel
  apps) or whether an explicit `herd` config is needed. (Verify during implementation.)

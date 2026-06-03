# Deploying to Laravel Cloud

This app is a hybrid Laravel + WordPress install. It runs vanilla WordPress on Laravel Cloud's
ephemeral, multi-replica filesystem by driving config from env, storing uploads + runtime code in
object storage, and putting cache/sessions/cron on Redis.

## 1. Attach resources (environment canvas)

- **MySQL database** — **required, and it must be MySQL (not Postgres).** The code overlay uses a
  MySQL named lock (`GET_LOCK`). Attaching injects `DB_*`.
- **Object Storage bucket — must be PUBLIC.** Media is served by direct bucket URL. Attaching injects
  `AWS_*` and `FILESYSTEM_DISK`. If the bucket's disk name is not `s3`, set `WP_UPLOADS_DISK` to that
  name. Laravel Cloud auto-adds your environment domains to the bucket CORS policy.
- **Valkey (KV store)** — for object cache + sessions + the scheduler lock. Attaching injects
  `CACHE_STORE`, `REDIS_HOST`, `REDIS_PASSWORD`.

## 2. Runtime

- **PHP 8.4.** Required extensions: **gd** (image thumbnails) and **redis** (object cache) — both are
  in Laravel Cloud's standard image.
- **Do NOT enable Octane.** WordPress is not request-stateless (it defines constants and uses heavy
  globals); run the default classic per-request runtime.

## 3. Environment variables (set the ones not injected by resources)

| Variable | Value |
|---|---|
| `APP_NAME` | your site name |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-domain` |
| `APP_KEY` | generate (`php artisan key:generate --show`) |
| `AUTH_KEY` … `NONCE_SALT` (8 salts) | unique random strings (e.g. from the WordPress.org salt API) |
| `WP_TABLE_PREFIX` | `wp_` |
| `WP_LOCALE` | `en_US` |
| `WP_SITE_TITLE` | your site title (used on first install) |
| `WP_ADMIN_USER` / `WP_ADMIN_PASSWORD` / `WP_ADMIN_EMAIL` | admin account (used only on first install) |
| `WP_REDIS_PREFIX` | a unique prefix, e.g. `yoursite:` |
| `REDIS_CACHE_DB` | `1` |
| `REDIS_SESSION_DB` | `2` |
| `WP_DISABLE_CRON` | `true` |
| `WP_CODE_READONLY` | `false` (set `true` to lock plugins/themes to the deploy artifact) |

Injected automatically by attached resources (do not set by hand): `DB_*`, `AWS_*`, `FILESYSTEM_DISK`,
`CACHE_STORE`, `REDIS_HOST`, `REDIS_PASSWORD`.

## 4. Build & deploy commands (environment settings)

- **Build command:** `php artisan optimize` (Laravel Cloud installs Composer dependencies automatically;
  there is no Node/asset build).
- **Deploy command:** `php artisan wp:deploy` — idempotent: installs WordPress (schema + admin) on the
  first deploy and hydrates the runtime code overlay on every deploy.

## 5. Scheduler (cron)

Enable the **Scheduler** toggle on the App compute cluster. Laravel Cloud then runs `schedule:run`
every minute, which triggers `wp:cron` (WordPress's due events). `WP_DISABLE_CRON=true` keeps WordPress
from running cron on page loads.

## 6. Health check

The app answers `GET /up` and `GET /health` with `200` (dependency-free). Laravel Cloud's default
health-check path is `/up`.

## 7. First deploy & verification

1. Connect the repo, pick the region, select PHP 8.4.
2. Attach the MySQL database, public bucket, and Valkey cache (section 1).
3. Set the manual env vars (section 3) and the build/deploy commands (section 4); enable the Scheduler.
4. Deploy. `wp:deploy` creates the WordPress schema + admin user and hydrates the overlay.
5. Verify: visit `https://your-domain/` (home renders), log in at `/wp/wp-admin/` with the admin env
   credentials, and upload a media item — confirm its URL points at the bucket and loads.

## 8. Notes

- **Scaling:** multiple replicas are safe — runtime plugins/themes hydrate from the bucket, cache and
  sessions live in Valkey, and the scheduler uses `onOneServer`.
- **Plugin/theme installs** done in wp-admin persist (written to the bucket, hydrated to other replicas).
  Set `WP_CODE_READONLY=true` to disable runtime installs and serve only git-managed code.
- **WordPress core** is updated by deploying a new commit, not via wp-admin (core auto-update is off).

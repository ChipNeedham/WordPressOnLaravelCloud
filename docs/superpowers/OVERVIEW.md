# WordPress on Laravel Cloud — Project Overview

> **Read this first.** This is the cumulative, agent-friendly summary of the whole effort:
> the goal, the architecture, every consequential decision (with rationale), findings discovered
> along the way, and the status of each sub-project. Individual sub-projects each have their own
> focused spec under `docs/superpowers/specs/`. Keep this file updated as work proceeds.

Last updated: 2026-06-03

---

## 1. Goal

Run a (near-)vanilla WordPress install on **Laravel Cloud**, whose runtime has an **ephemeral,
per-replica filesystem**. To do that we:

1. Drive all of `wp-config.php` from a **Laravel-style `.env`** (DB, salts, prefix, debug, URLs),
   with the install/setup wizard pre-satisfied and locale preset to English (`en_US`).
2. Make WordPress's filesystem writes land in **object storage (S3 / MinIO)** *invisibly* — WP and
   plugins keep calling normal `fopen` / `file_put_contents` / `file_exists`, unaware they aren't
   touching local disk.
3. Keep WordPress core as close to **vanilla and updatable** as possible — all customization lives
   in a Laravel layer (`app/`) and a must-use plugin, never in patched core files.

### Local dev environment
- **Laravel Herd** serving the app. Domain: `http://wordpressonlaravelcloud.test/`
- **MySQL** available via Herd.
- **MinIO** simulating S3 buckets.
- **Cache** (Redis/Valkey) available.

---

## 2. Architecture (settled)

A single repository that is simultaneously a minimal **Laravel app** and a **WordPress install**.

```
/ (repo root)
  artisan                      # real — Cloud requires laravel/framework; gives us commands
  composer.json                # requires laravel/framework (full)
  app/  bootstrap/  config/  routes/  vendor/   # Laravel skeleton + our glue
  public/                      # WEB ROOT (document root on Herd + Cloud)
    index.php                  # FRONT CONTROLLER: boots Laravel → /up & /health → else WordPress
    wp/                        # WordPress core (wp-admin, wp-includes, wp-*.php) — Bedrock-style
    wp-content/                # themes, plugins, mu-plugins, uploads
    wp-config.php              # thin shim: reads Laravel .env/config, defines WP constants
```

**Request lifecycle:** Web root is `public/`. The webserver serves real static files directly
(CSS/JS/images under `public/wp/` and `public/wp-content/`). Any request that isn't a real file
falls through to `public/index.php`, which:
1. Boots the Laravel container (so `Storage`, `Cache`, `config()` are live).
2. Returns `200` for `/up` and `/health`.
3. Otherwise hands the request to WordPress.

**Relationship model:** *Hybrid* — WordPress serves the site; a lightweight Laravel container is
booted first so Laravel facades/config are available app-wide. `artisan` works for commands.

---

## 3. Decision log

| # | Decision | Rationale |
|---|----------|-----------|
| D1 | **Hybrid** WP+Laravel: WP serves requests, Laravel container boots first; `artisan` available. | Get `Storage`/`Cache`/`config()` everywhere without forcing WP through Laravel's router. |
| D2 | Ship the **full `laravel/framework`** in `composer.json`/`composer.lock`. | Laravel Cloud's build pipeline inspects `composer.lock` for `laravel/framework` and **requires Laravel 9+**; also enables `artisan`. |
| D3 | **Laravel front controller wraps WP** (`public/index.php`), web root = `public/`. | Single entry point, clean health-check + future-route handling, standard for Cloud. |
| D4 | **Bedrock-style layout**: WP core under `public/wp/`, content under `public/wp-content/`. | Keeps all static assets under the web root (served directly/fast), keeps `/wp/wp-admin/` working, and leaves one PHP front controller. Avoids fighting the webserver over static files. |
| D5 | Serve **both `/up` and `/health`** returning `200`. | Cloud's health-check path is configurable; platform default follows Laravel's `/up`. Serving both removes the guesswork. |
| D6 | Storage target = **all writable areas of `wp-content`** routed to object storage, built in layers. | User goal is maximum transparency. But "data" vs "executable code" are different problems (see Findings F2), so we sequence it. |
| D7 | **Do NOT run WordPress under Octane / FrankenPHP worker mode** (run classic per-request). Storage caching uses **Redis/Valkey + request-scoped static cache**, not process memory. | WordPress is not stateless (constants via `define()`, huge globals) — unsafe in a persistent worker. A shared cache gives the "don't re-fetch every request" benefit *and* is multi-replica-safe. See Findings F3. |
| D8 | Build as **single-purpose specs**, one sub-project at a time, with this OVERVIEW as the cumulative index. | Keeps each spec/plan focused and reviewable. |

---

## 4. Findings

**F1 — Cloud requires `laravel/framework` (Laravel 9+).** The deploy build checks `composer.lock`
and rejects unsupported/old versions. → drives D2.

**F2 — "Everything writable" is two problems, not one.**
- *Mutable data* (`uploads`, cache, upgrade temp, languages): clean fit for a Flysystem stream
  wrapper → S3. WP reads/writes normally.
- *Executable code* (`plugins`, `themes`, core installable at runtime): you can't sanely `include`
  PHP straight off S3 each request. The robust pattern is an **overlay** — code on fast local disk,
  writes mirrored to S3 (write-through), and a fresh/ephemeral replica **re-hydrates from S3 on
  boot**. This is what lets "install a plugin in wp-admin" survive Cloud's ephemeral, multi-replica FS.

**F3 — Octane does not solve the storage problem and is unsafe for WP.**
- Octane = FrankenPHP worker mode = app kept in memory across requests; only resets *framework*
  state. WordPress's `define()` constants + globals make re-running its bootstrap in one process
  unsafe.
- The "pull from bucket once, keep warm" instinct is **already satisfied without Octane**: the
  per-replica ephemeral disk persists for the *life of the replica* (not per-request), and **OPcache**
  keeps compiled PHP warm across requests even in classic mode.
- The one thing a persistent process *could* add — in-memory caching of S3 metadata (`file_exists`,
  `filemtime`, …) — is better served by **Redis/Valkey + a request-static cache**, which works in
  classic mode and doesn't go stale across replicas the way per-worker memory would.
- We still write the stream wrapper to be **Octane-safe** (register once per process, reset
  request state, bounded caches) so future Laravel-route work could use Octane without breakage.

Sources: Laravel Octane docs, FrankenPHP worker-mode & performance docs, Laravel Cloud
environments/filesystem docs.

---

## 5. Sub-projects & status

| # | Sub-project | Status | Spec |
|---|-------------|--------|------|
| 1 | **Foundation** — Laravel skeleton, Bedrock layout, front controller, env-driven `wp-config`, health endpoints, container boot, English/setup preset. | Spec in progress | `specs/` (pending) |
| 2 | **Object storage for uploads** — `wpcloud://` Flysystem stream wrapper, URL rewriting to MinIO/CDN, Redis+static stat cache. | Not started | — |
| 3 | **Full writable overlay** — extend wrapper to all of `wp-content`; write-through + boot hydration for plugins/themes/core. | Not started | — |
| 4 | **Cache / sessions / cron** (as needed) — object cache + sessions on Redis; cron/queue handling. | Not started | — |

Each sub-project: brainstorm → spec (in `specs/`) → implementation plan → implement. Update the
table above and the decision log/findings as each completes.

---

## 6. Glossary / key paths

- **ABSPATH** — WordPress core dir; here `public/wp/`.
- **Front controller** — `public/index.php`; the only PHP entry point.
- **wp-config shim** — `public/wp-config.php`; reads Laravel env/config, defines WP constants.
- **Glue code** — Laravel-side customization under `app/` + a must-use plugin in
  `public/wp-content/mu-plugins/`. WP core stays vanilla.
- **Stream wrapper** — `wpcloud://` scheme (sub-project 2+) backed by Flysystem → S3/MinIO.

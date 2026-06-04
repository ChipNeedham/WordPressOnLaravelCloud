<?php
/**
 * Keep dynamic WordPress HTML out of Laravel Cloud's edge cache.
 *
 * Cloud's edge caches 200/301 responses for ~120 min when the app sets no
 * Cache-Control header, which serves stale pages after content edits. Static
 * assets are served directly by the web server (outside WordPress) and keep their
 * own long edge caching; this only marks WordPress-rendered (dynamic) responses as
 * non-cacheable so content is always immediately fresh. App-level performance still
 * comes from the Redis object cache. (wp-admin/wp-login already send no-cache.)
 */

add_action('send_headers', static function () {
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-cache, must-revalidate, max-age=0', true);
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT', true);
}, 1000);

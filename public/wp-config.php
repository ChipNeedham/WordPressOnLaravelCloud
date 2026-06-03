<?php
/**
 * WordPress configuration, driven by the Laravel-style environment.
 *
 * IMPORTANT: This file must NOT load the Composer autoloader (and therefore
 * Laravel's global helpers), because Laravel defines __() which collides with
 * WordPress's unguarded __() in wp-includes/l10n.php. We read env values with a
 * tiny standalone reader and the pure WpConfig::map(). Laravel itself is booted
 * later from a must-use plugin, AFTER WordPress has declared its functions.
 */

require_once dirname(__DIR__) . '/bootstrap/wp-env.php';
require_once dirname(__DIR__) . '/app/WordPress/WpConfig.php';

$__env = wp_env();

foreach (\App\WordPress\WpConfig::map($__env) as $wp_const_name => $wp_const_value) {
    if (! defined($wp_const_name)) {
        define($wp_const_name, $wp_const_value);
    }
}

// Warn loudly if any auth salt is empty — WordPress sessions are insecure without them.
$wp_empty_salts = array_filter(
    ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'],
    static fn ($k) => defined($k) && constant($k) === ''
);
if ($wp_empty_salts !== []) {
    error_log('WordPress on Laravel Cloud: empty authentication salts (' . implode(', ', $wp_empty_salts) . '). Set them in the environment; sessions are insecure without them.');
}
unset($wp_empty_salts);

$table_prefix = \App\WordPress\WpConfig::normalizePrefix($__env['WP_TABLE_PREFIX'] ?? 'wp_');

// Code-overlay hardening: no in-admin file editor, no core auto-update.
if (! defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}
if (! defined('AUTOMATIC_UPDATER_DISABLED')) {
    define('AUTOMATIC_UPDATER_DISABLED', true);
}
// Read-only kill switch: block ALL file mods when WP_CODE_READONLY is set.
if (! defined('DISALLOW_FILE_MODS')
    && filter_var(($__env['WP_CODE_READONLY'] ?? false), FILTER_VALIDATE_BOOL)) {
    define('DISALLOW_FILE_MODS', true);
}

// Redis object cache + sessions (sub-project #4). Defined before WordPress loads
// object-cache.php (wp_start_object_cache), which runs before the Laravel container.
if (! empty($__env['REDIS_HOST'])) {
    if (! defined('WP_REDIS_HOST')) {
        define('WP_REDIS_HOST', $__env['REDIS_HOST']);
    }
    if (! defined('WP_REDIS_PORT')) {
        define('WP_REDIS_PORT', (int) ($__env['REDIS_PORT'] ?? 6379));
    }
    if (! defined('WP_REDIS_PASSWORD') && ! empty($__env['REDIS_PASSWORD'])) {
        define('WP_REDIS_PASSWORD', $__env['REDIS_PASSWORD']);
    }
    if (! defined('WP_REDIS_DATABASE')) {
        define('WP_REDIS_DATABASE', (int) ($__env['REDIS_CACHE_DB'] ?? 1));
    }
    if (! defined('WP_REDIS_SESSION_DB')) {
        define('WP_REDIS_SESSION_DB', (int) ($__env['REDIS_SESSION_DB'] ?? 2));
    }
    if (! defined('WP_REDIS_PREFIX')) {
        define('WP_REDIS_PREFIX', $__env['WP_REDIS_PREFIX'] ?? 'wpcloud:');
    }
    if (! defined('WP_REDIS_CLIENT')) {
        define('WP_REDIS_CLIENT', 'phpredis');
    }
} elseif (! defined('WP_REDIS_DISABLED')) {
    define('WP_REDIS_DISABLED', true);
}

// Cron is driven by the Laravel scheduler, not WordPress's on-page-load loopback.
if (! defined('DISABLE_WP_CRON')) {
    define('DISABLE_WP_CRON', filter_var($__env['WP_DISABLE_CRON'] ?? true, FILTER_VALIDATE_BOOL));
}

unset($__env, $wp_const_name, $wp_const_value);

// ABSPATH is normally defined by wp-load.php (public/wp/). Guard for direct includes.
if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/wp/');
}

// Content lives beside core at public/wp-content (NOT inside public/wp).
if (! defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', dirname(ABSPATH) . '/wp-content');
}
if (! defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', WP_HOME . '/wp-content');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';

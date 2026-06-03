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

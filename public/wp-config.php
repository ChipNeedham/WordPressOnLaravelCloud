<?php
/**
 * WordPress configuration, driven by the Laravel environment.
 *
 * Loaded by wp-load.php from one level above ABSPATH (which is public/wp/).
 * Boots a partial Laravel container, then defines WordPress constants from
 * config/wordpress.php and config/database.php.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/laravel-boot.php';

wp_laravel_boot();

// Define DB / salt / URL / debug constants from framework config.
foreach (\App\WordPress\WpConfig::fromConfig() as $wp_const_name => $wp_const_value) {
    if (! defined($wp_const_name)) {
        define($wp_const_name, $wp_const_value);
    }
}

$table_prefix = \App\WordPress\WpConfig::tablePrefix();

unset($wp_const_name, $wp_const_value);

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

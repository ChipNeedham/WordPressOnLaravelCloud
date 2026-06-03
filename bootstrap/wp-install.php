<?php
/**
 * Standalone WordPress installer, run in its own PHP process by `wp:install`.
 *
 * Loading WordPress here is safe because Laravel is NOT booted first in this
 * process (it boots later via the mu-plugin, after WordPress declares __()).
 * Prints a machine-readable status line for the calling Artisan command.
 */

define('WP_INSTALLING', true);

$root = dirname(__DIR__);

require $root . '/public/wp/wp-load.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';

if (is_blog_installed()) {
    fwrite(STDOUT, "ALREADY_INSTALLED\n");
    exit(0);
}

require_once $root . '/bootstrap/wp-env.php';
$env = wp_env();

$password = $env['WP_ADMIN_PASSWORD'] ?? '';
if ($password === '') {
    fwrite(STDERR, "NO_PASSWORD\n");
    exit(1);
}

$result = wp_install(
    $env['WP_SITE_TITLE'] ?? 'WordPress on Laravel Cloud',
    $env['WP_ADMIN_USER'] ?? 'admin',
    $env['WP_ADMIN_EMAIL'] ?? 'admin@example.com',
    true,
    '',
    wp_slash($password)
);

fwrite(STDOUT, 'INSTALLED user_id=' . $result['user_id'] . "\n");
exit(0);

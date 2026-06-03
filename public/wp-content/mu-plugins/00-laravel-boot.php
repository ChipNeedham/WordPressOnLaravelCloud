<?php
/**
 * Boot the Laravel container from within WordPress.
 *
 * Must-use plugins load during wp-settings.php AFTER WordPress has declared its
 * global functions (l10n.php loads earlier), so Laravel's function_exists-guarded
 * helpers (notably __()) skip redeclaring them — avoiding a fatal collision.
 *
 * After this runs, Storage / Cache / config() are available for the rest of the
 * request (used by later sub-projects such as object storage).
 */

$__root = dirname(__DIR__, 3); // repository root

require_once $__root . '/vendor/autoload.php';
require_once $__root . '/bootstrap/laravel-boot.php';

wp_laravel_boot();

unset($__root);

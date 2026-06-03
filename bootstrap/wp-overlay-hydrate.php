<?php
/**
 * Hydrate the code overlay in a WordPress-first subprocess (no Laravel/__() collision).
 * Intended to be invoked by the wp:overlay-hydrate Artisan command (e.g. as a deploy command).
 */

$_SERVER['HTTP_HOST'] = getenv('APP_HOST') ?: 'wordpressonlaravelcloud.test';
$_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);

require dirname(__DIR__) . '/public/wp/wp-load.php';

$overlay = App\Overlay\CodeOverlay::fromConfig();
if (! $overlay->isActive()) {
    fwrite(STDOUT, "INACTIVE\n");
    exit(0);
}
$overlay->hydrate();
fwrite(STDOUT, 'HYDRATED version=' . $overlay->version() . "\n");
exit(0);

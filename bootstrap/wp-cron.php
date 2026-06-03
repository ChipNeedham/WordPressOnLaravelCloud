<?php
/**
 * Run due WordPress cron events in a WP-first subprocess (no Laravel/__() collision),
 * invoked by the wp:cron Artisan command via the Laravel scheduler.
 */

define('DOING_CRON', true);
$_SERVER['HTTP_HOST'] = getenv('APP_HOST') ?: 'wordpressonlaravelcloud.test';
$_SERVER['REQUEST_URI'] = '/wp-cron.php';

require dirname(__DIR__) . '/public/wp/wp-load.php';
require dirname(__DIR__) . '/bootstrap/wp-cron-run.php';

$ran = wpcloud_run_due_cron();
fwrite(STDOUT, "CRON ran={$ran}\n");
exit(0);

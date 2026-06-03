<?php
/**
 * Run due WordPress cron events in a WP-first subprocess (no Laravel/__() collision),
 * invoked by the wp:cron Artisan command via the Laravel scheduler.
 */

define('DOING_CRON', true);
// Give cron a sane host for any URL generation (emails, etc.). On Cloud APP_URL is
// injected; locally it falls back to the dev domain.
$_SERVER['HTTP_HOST'] = parse_url((string) (getenv('APP_URL') ?: ''), PHP_URL_HOST)
    ?: 'wordpressonlaravelcloud.test';
$_SERVER['REQUEST_URI'] = '/wp-cron.php';

require dirname(__DIR__) . '/public/wp/wp-load.php';
require dirname(__DIR__) . '/bootstrap/wp-cron-run.php';

$ran = wpcloud_run_due_cron();
fwrite(STDOUT, "CRON ran={$ran}\n");
exit(0);

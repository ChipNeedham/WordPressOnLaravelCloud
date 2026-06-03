<?php
/**
 * Object-cache + cron-loop smoke (WP-first; against local Redis).
 *   php bootstrap/wp-cache-smoke.php ext            -> EXT=yes|no
 *   php bootstrap/wp-cache-smoke.php set <key> <val> -> SET
 *   php bootstrap/wp-cache-smoke.php get <key>      -> GET=<val> (exit 2 if miss)
 *   php bootstrap/wp-cache-smoke.php flush          -> FLUSHED
 *   php bootstrap/wp-cache-smoke.php cron           -> CRON_FIRED|CRON_NOT_FIRED
 */

$_SERVER['HTTP_HOST'] = 'wordpressonlaravelcloud.test';
$_SERVER['REQUEST_URI'] = '/';
define('WP_USE_THEMES', false);

require dirname(__DIR__) . '/public/wp/wp-load.php';

$op = $argv[1] ?? 'ext';
$group = 'wpcloud-smoke';

switch ($op) {
    case 'ext':
        fwrite(STDOUT, wp_using_ext_object_cache() ? "EXT=yes\n" : "EXT=no\n");
        exit(wp_using_ext_object_cache() ? 0 : 2);

    case 'set':
        wp_cache_set($argv[2], $argv[3], $group);
        fwrite(STDOUT, "SET\n");
        exit(0);

    case 'get':
        $v = wp_cache_get($argv[2], $group);
        fwrite(STDOUT, 'GET=' . var_export($v, true) . "\n");
        exit($v === false ? 2 : 0);

    case 'flush':
        wp_cache_flush();
        fwrite(STDOUT, "FLUSHED\n");
        exit(0);

    case 'cron':
        require dirname(__DIR__) . '/bootstrap/wp-cron-run.php';
        $GLOBALS['__cron_fired'] = false;
        add_action('wpcloud_cron_smoke', static function () { $GLOBALS['__cron_fired'] = true; });
        wp_schedule_single_event(time() - 30, 'wpcloud_cron_smoke');
        wpcloud_run_due_cron();
        fwrite(STDOUT, $GLOBALS['__cron_fired'] ? "CRON_FIRED\n" : "CRON_NOT_FIRED\n");
        exit($GLOBALS['__cron_fired'] ? 0 : 2);

    default:
        fwrite(STDERR, "unknown op\n");
        exit(1);
}

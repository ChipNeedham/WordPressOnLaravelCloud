<?php
/**
 * Point PHP's native session handler at Redis so plugins that call session_start()
 * don't write to the ephemeral local disk. Loads after 00-laravel-boot (autoloader
 * available) and before regular plugins (which may start sessions on init).
 * No-ops when Redis isn't configured or the redis extension is missing.
 */

use App\Cache\RedisSessionPath;

if (! defined('WP_REDIS_HOST') || ! extension_loaded('redis')) {
    return;
}

$__sessionPath = RedisSessionPath::build(
    (string) WP_REDIS_HOST,
    (int) (defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379),
    (int) (defined('WP_REDIS_SESSION_DB') ? WP_REDIS_SESSION_DB : 0),
    defined('WP_REDIS_PASSWORD') ? (string) WP_REDIS_PASSWORD : '',
    defined('WP_REDIS_USERNAME') ? (string) WP_REDIS_USERNAME : '',
    defined('WP_REDIS_SCHEME') ? (string) WP_REDIS_SCHEME : 'tcp'
);

@ini_set('session.save_handler', 'redis');
@ini_set('session.save_path', $__sessionPath);

unset($__sessionPath);

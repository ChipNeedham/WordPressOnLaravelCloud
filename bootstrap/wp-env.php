<?php

if (! function_exists('wp_env')) {
    /**
     * Read environment values WITHOUT booting Laravel or the Composer autoloader.
     *
     * The WordPress config shim runs before Laravel boots (to avoid the global
     * __() collision), so it cannot use env()/config(). This reads the .env file
     * directly for local dev, and lets real environment variables (which Laravel
     * Cloud injects) take precedence.
     *
     * @return array<string,string>
     */
    function wp_env(): array
    {
        static $vars = null;
        if ($vars !== null) {
            return $vars;
        }

        $vars = [];

        $file = dirname(__DIR__) . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = ltrim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $val = trim(substr($line, $pos + 1));
                $len = strlen($val);
                if ($len >= 2
                    && (($val[0] === '"' && $val[$len - 1] === '"')
                        || ($val[0] === "'" && $val[$len - 1] === "'"))) {
                    $val = substr($val, 1, -1);
                }
                if ($key !== '' && ! array_key_exists($key, $vars)) {
                    $vars[$key] = $val;
                }
            }
        }

        // Real environment variables win (Laravel Cloud injects these at runtime),
        // including when explicitly set to an empty string (e.g. a passwordless DB).
        $keys = [
            'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_HOST', 'DB_PORT',
            'APP_KEY', 'APP_URL', 'APP_ENV', 'APP_DEBUG',
            'WP_TABLE_PREFIX', 'WP_LOCALE',
            'WP_CODE_READONLY', 'WP_OVERLAY_PREFIX',
            'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_CACHE_DB',
            'REDIS_SESSION_DB', 'WP_REDIS_PREFIX', 'WP_DISABLE_CRON',
            'WP_SITE_TITLE', 'WP_ADMIN_USER', 'WP_ADMIN_PASSWORD', 'WP_ADMIN_EMAIL',
            'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
        ];
        // Security-sensitive keys must never be downgraded to empty by the
        // environment: an empty real value falls back to the configured value
        // instead of silently producing insecure WordPress sessions.
        $sensitive = [
            'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',
            'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT',
        ];
        foreach ($keys as $k) {
            $real = getenv($k);
            if ($real === false) {
                continue;
            }
            if ($real === '' && in_array($k, $sensitive, true)) {
                continue;
            }
            $vars[$k] = $real;
        }

        return $vars;
    }
}

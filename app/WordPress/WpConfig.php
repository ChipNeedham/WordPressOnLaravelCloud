<?php

namespace App\WordPress;

use RuntimeException;

class WpConfig
{
    /**
     * Pure mapping from a normalized env array to WordPress constants.
     * Throws if a required value is missing.
     *
     * @param  array<string,mixed>  $env
     * @return array<string,mixed>
     */
    public static function map(array $env): array
    {
        foreach (['DB_DATABASE', 'DB_USERNAME', 'DB_HOST', 'APP_KEY', 'APP_URL'] as $key) {
            if (! isset($env[$key]) || $env[$key] === '' || $env[$key] === null) {
                throw new RuntimeException("WordPress configuration is missing required value: {$key}");
            }
        }

        $host = (string) $env['DB_HOST'];
        $port = $env['DB_PORT'] ?? null;
        if ($port !== null && $port !== '' && (int) $port !== 3306) {
            $host .= ':' . $port;
        }

        $appUrl = rtrim((string) $env['APP_URL'], '/');

        $valid = ['local', 'development', 'staging', 'production'];
        $envType = $env['APP_ENV'] ?? 'production';
        if (! in_array($envType, $valid, true)) {
            $envType = 'production';
        }

        return [
            'DB_NAME'     => (string) $env['DB_DATABASE'],
            'DB_USER'     => (string) $env['DB_USERNAME'],
            'DB_PASSWORD' => (string) ($env['DB_PASSWORD'] ?? ''),
            'DB_HOST'     => $host,
            'DB_CHARSET'  => 'utf8mb4',
            'DB_COLLATE'  => '',

            'AUTH_KEY'         => (string) ($env['AUTH_KEY'] ?? ''),
            'SECURE_AUTH_KEY'  => (string) ($env['SECURE_AUTH_KEY'] ?? ''),
            'LOGGED_IN_KEY'    => (string) ($env['LOGGED_IN_KEY'] ?? ''),
            'NONCE_KEY'        => (string) ($env['NONCE_KEY'] ?? ''),
            'AUTH_SALT'        => (string) ($env['AUTH_SALT'] ?? ''),
            'SECURE_AUTH_SALT' => (string) ($env['SECURE_AUTH_SALT'] ?? ''),
            'LOGGED_IN_SALT'   => (string) ($env['LOGGED_IN_SALT'] ?? ''),
            'NONCE_SALT'       => (string) ($env['NONCE_SALT'] ?? ''),

            'WP_HOME'    => $appUrl,
            'WP_SITEURL' => $appUrl . '/wp',

            'WP_DEBUG'            => filter_var($env['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
            'WP_ENVIRONMENT_TYPE' => $envType,
        ];
    }

    /**
     * Build the constants array from the framework's loaded config.
     *
     * @return array<string,mixed>
     */
    public static function fromConfig(): array
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}", []);

        return self::map([
            'DB_DATABASE' => $db['database'] ?? null,
            'DB_USERNAME' => $db['username'] ?? null,
            'DB_PASSWORD' => $db['password'] ?? '',
            'DB_HOST'     => $db['host'] ?? '127.0.0.1',
            'DB_PORT'     => $db['port'] ?? 3306,
            'APP_KEY'     => config('app.key'),
            'APP_URL'     => config('wordpress.home') ?: config('app.url'),
            'APP_ENV'     => config('wordpress.environment_type'),
            'APP_DEBUG'   => config('wordpress.debug'),
            'AUTH_KEY'         => config('wordpress.salts.AUTH_KEY'),
            'SECURE_AUTH_KEY'  => config('wordpress.salts.SECURE_AUTH_KEY'),
            'LOGGED_IN_KEY'    => config('wordpress.salts.LOGGED_IN_KEY'),
            'NONCE_KEY'        => config('wordpress.salts.NONCE_KEY'),
            'AUTH_SALT'        => config('wordpress.salts.AUTH_SALT'),
            'SECURE_AUTH_SALT' => config('wordpress.salts.SECURE_AUTH_SALT'),
            'LOGGED_IN_SALT'   => config('wordpress.salts.LOGGED_IN_SALT'),
            'NONCE_SALT'       => config('wordpress.salts.NONCE_SALT'),
        ]);
    }

    public static function tablePrefix(): string
    {
        return self::normalizePrefix((string) config('wordpress.table_prefix', 'wp_'));
    }

    public static function normalizePrefix(string $prefix): string
    {
        return $prefix !== '' ? $prefix : 'wp_';
    }
}

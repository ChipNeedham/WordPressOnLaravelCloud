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
        // 3306 is MySQL's default port; omit it from the host string.
        if ($port !== null && $port !== '' && (int) $port !== 3306) {
            $host .= ':' . (string) $port;
        }

        $appUrl = rtrim((string) $env['APP_URL'], '/');

        // WordPress-defined values for WP_ENVIRONMENT_TYPE (see WP core).
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

    public static function normalizePrefix(string $prefix): string
    {
        return $prefix !== '' ? $prefix : 'wp_';
    }
}

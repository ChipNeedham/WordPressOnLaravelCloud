<?php

return [

    // Database table prefix.
    'table_prefix' => env('WP_TABLE_PREFIX', 'wp_'),

    // Public site URL (WP_HOME). Core is served from "{home}/wp".
    'home' => env('APP_URL'),

    // Authentication keys and salts (WordPress secret keys).
    'salts' => [
        'AUTH_KEY'         => env('AUTH_KEY'),
        'SECURE_AUTH_KEY'  => env('SECURE_AUTH_KEY'),
        'LOGGED_IN_KEY'    => env('LOGGED_IN_KEY'),
        'NONCE_KEY'        => env('NONCE_KEY'),
        'AUTH_SALT'        => env('AUTH_SALT'),
        'SECURE_AUTH_SALT' => env('SECURE_AUTH_SALT'),
        'LOGGED_IN_SALT'   => env('LOGGED_IN_SALT'),
        'NONCE_SALT'       => env('NONCE_SALT'),
    ],

    // WP_DEBUG and WP_ENVIRONMENT_TYPE.
    'debug'            => env('APP_DEBUG', false),
    'environment_type' => env('APP_ENV', 'production'),

    // One-shot installer values (used by `php artisan wp:install`).
    'install' => [
        'title'          => env('WP_SITE_TITLE', 'WordPress on Laravel Cloud'),
        'admin_user'     => env('WP_ADMIN_USER', 'admin'),
        'admin_password' => env('WP_ADMIN_PASSWORD'),
        'admin_email'    => env('WP_ADMIN_EMAIL'),
        'locale'         => env('WP_LOCALE', 'en_US'),
    ],

];

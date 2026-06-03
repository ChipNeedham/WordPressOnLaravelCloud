<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;

if (! function_exists('wp_laravel_boot')) {
    /**
     * Boot just enough of Laravel for env(), config() and facades to work.
     *
     * Deliberately omits HandleExceptions so Laravel does NOT take over PHP's
     * error/exception/shutdown handlers — WordPress must keep control of those.
     * Idempotent and reuses an already-bootstrapped app (e.g. under `artisan`).
     */
    function wp_laravel_boot(): Application
    {
        $existing = Container::getInstance();
        if ($existing instanceof Application && $existing->hasBeenBootstrapped()) {
            return $existing;
        }

        /** @var Application $app */
        $app = require __DIR__ . '/app.php';

        $app->bootstrapWith([
            LoadEnvironmentVariables::class,
            LoadConfiguration::class,
            RegisterFacades::class,
            RegisterProviders::class,
            BootProviders::class,
        ]);

        return $app;
    }
}

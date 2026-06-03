<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WpInstall extends Command
{
    protected $signature = 'wp:install';

    protected $description = 'Run the WordPress installer using values from configuration.';

    public function handle(): int
    {
        // Tell WordPress we are installing so its "not installed" guards stand down.
        if (! defined('WP_INSTALLING')) {
            define('WP_INSTALLING', true);
        }

        // Boot WordPress (loads our wp-config shim, which reuses the running app).
        require base_path('public/wp/wp-load.php');
        require ABSPATH . 'wp-admin/includes/upgrade.php';

        if (is_blog_installed()) {
            $this->info('WordPress is already installed. Nothing to do.');

            return self::SUCCESS;
        }

        $install = config('wordpress.install');

        if (empty($install['admin_password'])) {
            $this->error('WP_ADMIN_PASSWORD is not set.');

            return self::FAILURE;
        }

        $result = wp_install(
            $install['title'],
            $install['admin_user'],
            $install['admin_email'],
            true, // search engine visible
            '',
            wp_slash($install['admin_password'])
        );

        $this->info("WordPress installed. Admin user id: {$result['user_id']}.");
        $this->line('Log in at ' . home_url('/wp/wp-login.php'));

        return self::SUCCESS;
    }
}

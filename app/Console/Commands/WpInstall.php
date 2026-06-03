<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class WpInstall extends Command
{
    protected $signature = 'wp:install';

    protected $description = 'Run the WordPress installer using values from configuration.';

    public function handle(): int
    {
        // WordPress must run in a separate process: this Artisan process has Laravel
        // (and its __() helper) loaded, which would collide with WordPress core.
        $script = base_path('bootstrap/wp-install.php');

        $result = Process::path(base_path())->timeout(120)->run([PHP_BINARY, $script]);

        $out = trim($result->output());
        $err = trim($result->errorOutput());

        if (str_contains($out, 'ALREADY_INSTALLED')) {
            $this->info('WordPress is already installed. Nothing to do.');

            return self::SUCCESS;
        }

        if ($result->failed() || ! str_contains($out, 'INSTALLED')) {
            $this->error('WordPress install failed.');
            if ($err !== '') {
                $this->line($err);
            }
            if ($out !== '') {
                $this->line($out);
            }

            return self::FAILURE;
        }

        $this->info('WordPress installed. ' . $out);

        $home = rtrim((string) (config('wordpress.home') ?: config('app.url')), '/');
        $this->line('Log in at ' . $home . '/wp/wp-login.php');

        return self::SUCCESS;
    }
}

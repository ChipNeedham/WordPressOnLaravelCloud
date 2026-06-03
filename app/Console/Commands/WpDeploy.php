<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WpDeploy extends Command
{
    protected $signature = 'wp:deploy';

    protected $description = 'Deploy step: install WordPress (idempotent) and hydrate the code overlay.';

    public function handle(): int
    {
        $this->info('Running WordPress deploy steps...');

        $code = $this->call('wp:install');
        if ($code !== self::SUCCESS) {
            $this->error('wp:install failed; aborting deploy.');

            return $code;
        }

        $code = $this->call('wp:overlay-hydrate');
        if ($code !== self::SUCCESS) {
            $this->error('wp:overlay-hydrate failed; aborting deploy.');

            return $code;
        }

        $this->info('WordPress deploy steps complete.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class WpCron extends Command
{
    protected $signature = 'wp:cron';

    protected $description = 'Run due WordPress cron events (scheduler-driven).';

    public function handle(): int
    {
        // Generous timeout for long-running cron hooks; concurrent runs are already
        // prevented by the scheduler's withoutOverlapping() lock.
        $result = Process::path(base_path())->timeout(300)
            ->run([PHP_BINARY, base_path('bootstrap/wp-cron.php')]);

        if ($result->failed()) {
            $this->error('WordPress cron run failed.');
            $this->line(trim($result->errorOutput()) ?: trim($result->output()));

            return self::FAILURE;
        }

        $this->info(trim($result->output()));

        return self::SUCCESS;
    }
}

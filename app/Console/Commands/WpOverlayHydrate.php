<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class WpOverlayHydrate extends Command
{
    protected $signature = 'wp:overlay-hydrate';

    protected $description = 'Hydrate runtime plugin/theme/language code from object storage.';

    public function handle(): int
    {
        $result = Process::path(base_path())->timeout(300)
            ->run([PHP_BINARY, base_path('bootstrap/wp-overlay-hydrate.php')]);

        $out = trim($result->output());

        if ($result->failed()) {
            $this->error('Overlay hydrate failed.');
            $this->line(trim($result->errorOutput()) ?: $out);

            return self::FAILURE;
        }

        $this->info($out === 'INACTIVE' ? 'Object storage not configured; nothing to hydrate.' : $out);

        return self::SUCCESS;
    }
}

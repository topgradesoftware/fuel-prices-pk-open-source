<?php

namespace App\Console\Commands;

use App\Services\Content\RefreshEngine;
use Illuminate\Console\Command;

class ContentRefreshCommand extends Command
{
    protected $signature = 'content:refresh';

    protected $description = 'Score published articles and refresh the stalest ones';

    public function handle(RefreshEngine $engine): int
    {
        $refreshed = $engine->processQueue();
        $this->info('Refreshed '.count($refreshed).' article(s).');

        return self::SUCCESS;
    }
}

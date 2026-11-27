<?php

namespace App\Console\Commands;

use App\Services\Fuel\FuelPriceSyncService;
use Illuminate\Console\Command;

class SyncFuelPricesCommand extends Command
{
    protected $signature = 'fuel:sync';

    protected $description = 'Sync latest fuel prices from all configured providers (PSO official + TrackMate)';

    public function handle(FuelPriceSyncService $sync): int
    {
        $this->info('Syncing fuel prices...');

        try {
            $result = $sync->sync();
            $this->info($result['message']);

            $this->callSilent('seo:publish-static');
            $this->call('seo:notify');
            $this->callSilent('og:generate');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

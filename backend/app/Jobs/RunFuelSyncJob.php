<?php

namespace App\Jobs;

use App\Services\Fuel\FuelPriceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunFuelSyncJob implements ShouldQueue
{
    use Queueable;

    public function handle(FuelPriceSyncService $sync): void
    {
        $sync->sync();
    }
}

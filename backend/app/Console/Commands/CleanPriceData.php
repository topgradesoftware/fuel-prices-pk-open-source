<?php

namespace App\Console\Commands;

use App\Services\Fuel\FuelPriceReconciler;
use Illuminate\Console\Command;

class CleanPriceData extends Command
{
    protected $signature = 'prices:clean';

    protected $description = 'Remove junk price rows and repair previous_price / history for accurate daily deltas';

    public function handle(FuelPriceReconciler $reconciler): int
    {
        $summary = $reconciler->reconcile();

        $this->info('composite_deleted='.$summary['composite_deleted']);
        $this->info('stale_trackmate_specialty_deleted='.($summary['stale_trackmate_specialty_deleted'] ?? 0));
        $this->info('history_pruned='.$summary['history_pruned']);
        $this->info('superseded_deleted='.$summary['superseded_deleted']);
        $this->info('previous_fixed='.$summary['previous_fixed']);

        return self::SUCCESS;
    }
}

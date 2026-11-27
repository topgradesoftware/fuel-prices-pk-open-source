<?php

namespace App\Listeners;

use App\Events\FuelPriceUpdated;
use App\Services\Content\ContentAutomationOrchestrator;
use App\Support\SafeError;
use Illuminate\Support\Facades\Log;

class RunContentAutomation
{
    public function __construct(
        private readonly ContentAutomationOrchestrator $orchestrator,
    ) {}

    public function handle(FuelPriceUpdated $event): void
    {
        try {
            $articles = $this->orchestrator->handlePriceSync($event->summary);
            if ($articles !== []) {
                Log::info('content.automation: planned articles after price sync', [
                    'count' => count($articles),
                    'ids' => collect($articles)->pluck('id')->all(),
                ]);
            }
        } catch (\Throwable $e) {
            // Never break price sync because content automation failed.
            SafeError::log($e, 'content.automation: listener failed');
        }
    }
}

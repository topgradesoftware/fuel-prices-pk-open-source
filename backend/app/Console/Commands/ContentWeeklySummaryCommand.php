<?php

namespace App\Console\Commands;

use App\Services\Content\ContentAutomationOrchestrator;
use App\Services\Content\PeriodPriceSnapshot;
use Illuminate\Console\Command;

class ContentWeeklySummaryCommand extends Command
{
    protected $signature = 'content:weekly-summary';

    protected $description = 'Plan and generate the weekly fuel price summary article (AI when budget allows)';

    public function handle(ContentAutomationOrchestrator $orchestrator, PeriodPriceSnapshot $snapshot): int
    {
        $articles = $orchestrator->handleScheduled('weekly_summary', [
            'period_label' => now()->format('Y-\\WW'),
            'effective_date' => now()->toDateString(),
            ...$snapshot->collect(),
        ]);
        $this->info('Weekly summary articles: '.count($articles));

        return self::SUCCESS;
    }
}

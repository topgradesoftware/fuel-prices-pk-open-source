<?php

namespace App\Console\Commands;

use App\Services\Content\ContentAutomationOrchestrator;
use App\Services\Content\PeriodPriceSnapshot;
use Illuminate\Console\Command;

class ContentMonthlySummaryCommand extends Command
{
    protected $signature = 'content:monthly-summary';

    protected $description = 'Plan and generate the monthly fuel price report (AI when budget allows)';

    public function handle(ContentAutomationOrchestrator $orchestrator, PeriodPriceSnapshot $snapshot): int
    {
        $articles = $orchestrator->handleScheduled('monthly_summary', [
            'period_label' => now()->format('Y-m'),
            'effective_date' => now()->toDateString(),
            ...$snapshot->collect(),
        ]);
        $this->info('Monthly summary articles: '.count($articles));

        return self::SUCCESS;
    }
}

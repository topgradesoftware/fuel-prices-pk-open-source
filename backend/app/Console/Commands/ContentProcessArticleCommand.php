<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\Content\ContentAutomationOrchestrator;
use App\Services\Content\PublishingService;
use Illuminate\Console\Command;

class ContentProcessArticleCommand extends Command
{
    protected $signature = 'content:process
        {article? : Article ID to process}
        {--publish : Approve and publish after pipeline}
        {--detect : Run change detection against current prices (no sync)}';

    protected $description = 'Run the content automation pipeline for an article or detect events now';

    public function handle(ContentAutomationOrchestrator $orchestrator, PublishingService $publishing): int
    {
        if ($this->option('detect')) {
            $articles = $orchestrator->handlePriceSync(['message' => 'manual detect']);
            $this->info('Detected/planned '.count($articles).' article(s).');

            return self::SUCCESS;
        }

        $id = $this->argument('article');
        if ($id === null) {
            $this->error('Provide an article ID or use --detect.');

            return self::FAILURE;
        }

        $article = Article::query()->findOrFail((int) $id);
        $article = $orchestrator->processArticle($article);
        if ($this->option('publish')) {
            $article = $publishing->approveAndPublish($article);
        }

        $this->info("Article #{$article->id} status={$article->status} slug={$article->slug}");

        return self::SUCCESS;
    }
}

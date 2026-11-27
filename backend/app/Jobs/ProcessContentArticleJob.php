<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\Content\ContentAutomationOrchestrator;
use App\Support\SafeError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessContentArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $articleId,
    ) {
        $this->onQueue((string) env('QUEUE_SYNC', 'default'));
    }

    public function handle(ContentAutomationOrchestrator $orchestrator): void
    {
        $article = Article::query()->find($this->articleId);
        if ($article === null) {
            return;
        }

        if (in_array($article->status, [Article::STATUS_PUBLISHED, Article::STATUS_ARCHIVED], true)
            && filled($article->body)) {
            return;
        }

        try {
            $orchestrator->processArticle($article);
        } catch (\Throwable $e) {
            SafeError::log($e, 'ProcessContentArticleJob failed', [
                'article_id' => $this->articleId,
            ]);
            throw $e;
        }
    }
}

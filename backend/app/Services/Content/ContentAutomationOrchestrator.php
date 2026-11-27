<?php

namespace App\Services\Content;

use App\Jobs\ProcessContentArticleJob;
use App\Models\Article;
use App\Models\ContentEvent;
use App\Support\SafeError;
use Illuminate\Support\Facades\Log;

/**
 * Price Sync → Change Detection → Planner → Draft → SEO → Links → Media → Review/Publish → Distribute
 *
 * Execution mode is config-driven (CONTENT_PIPELINE_MODE=inline|queue).
 */
class ContentAutomationOrchestrator
{
    public function __construct(
        private readonly ChangeDetector $detector,
        private readonly ContentPlanner $planner,
        private readonly ContentAuditLogger $audits,
    ) {}

    /**
     * @param  array<string, mixed>  $syncSummary
     * @return list<Article>
     */
    public function handlePriceSync(array $syncSummary = []): array
    {
        if (! config('content.enabled')) {
            return [];
        }

        $events = $this->detector->detectFromSync($syncSummary);
        if ($events === []) {
            Log::info('content.automation: no events after sync');

            return [];
        }

        return $this->planAndDispatch($events);
    }

    /**
     * @return list<Article>
     */
    public function handleScheduled(string $type, array $payload = []): array
    {
        if (! config('content.enabled')) {
            return [];
        }

        $event = $this->detector->recordScheduledEvent($type, $payload);
        if ($event === null) {
            return [];
        }

        return $this->planAndDispatch([$event]);
    }

    /**
     * @param  list<ContentEvent>  $events
     * @return list<Article>
     */
    public function planAndDispatch(array $events): array
    {
        $articles = $this->planner->plan($events);
        $mode = strtolower((string) config('content.pipeline_mode', 'inline'));

        foreach ($articles as $article) {
            if ($mode === 'queue') {
                ProcessContentArticleJob::dispatch($article->id);
                continue;
            }

            try {
                $this->processArticle($article);
            } catch (\Throwable $e) {
                SafeError::log($e, 'content.automation: inline pipeline failed — queued for retry', [
                    'article_id' => $article->id,
                ]);
                ProcessContentArticleJob::dispatch($article->id);
            }
        }

        $this->audits->log('pipeline_dispatched', meta: [
            'articles' => count($articles),
            'events' => count($events),
            'mode' => $mode,
        ]);

        return $articles;
    }

    /** @deprecated Use planAndDispatch */
    public function planAndQueue(array $events): array
    {
        return $this->planAndDispatch($events);
    }

    /**
     * Run the full article pipeline synchronously (used by the job + CLI).
     */
    public function processArticle(Article $article): Article
    {
        /** @var DraftGeneratorService $drafts */
        $drafts = app(DraftGeneratorService::class);
        /** @var SeoEnrichmentService $seo */
        $seo = app(SeoEnrichmentService::class);
        /** @var LinkRecommendationService $links */
        $links = app(LinkRecommendationService::class);
        /** @var MediaPipelineService $media */
        $media = app(MediaPipelineService::class);
        /** @var PublishingService $publishing */
        $publishing = app(PublishingService::class);

        $article = $drafts->generate($article);
        $article = $seo->enrich($article);
        $article = $links->apply($article);

        try {
            $article = $media->generate($article);
        } catch (\Throwable $e) {
            // Media must not block editorial review.
            SafeError::log($e, 'content.automation: media stage failed', [
                'article_id' => $article->id,
            ]);
        }

        // Per-type rules decide review vs auto-publish (daily templates can go live).
        if (config('content.auto_submit_review', true)) {
            $article = $publishing->submitForReview($article);
        }

        return $article;
    }
}

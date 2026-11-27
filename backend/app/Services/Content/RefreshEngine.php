<?php

namespace App\Services\Content;

use App\Models\Article;
use Illuminate\Support\Collection;


/**
 * Scores published articles for freshness and queues low-scoring ones for refresh.
 */
class RefreshEngine
{
    public function __construct(
        private readonly ContentAuditLogger $audits,
        private readonly DraftGeneratorService $drafts,
        private readonly SeoEnrichmentService $seo,
        private readonly LinkRecommendationService $links,
        private readonly MediaPipelineService $media,
        private readonly PublishingService $publishing,
    ) {}

    /**
     * @return Collection<int, array{article: Article, score: int}>
     */
    public function rankStale(): Collection
    {
        $staleDays = (int) config('content.refresh.stale_days', 45);

        return Article::query()
            ->published()
            ->get()
            ->map(function (Article $article) use ($staleDays) {
                $score = $this->score($article, $staleDays);
                $article->freshness_score = $score;

                return ['article' => $article, 'score' => $score];
            })
            ->sortBy('score')
            ->values();
    }

    public function score(Article $article, ?int $staleDays = null): int
    {
        $staleDays ??= (int) config('content.refresh.stale_days', 45);
        $score = 100;

        $updated = $article->last_refreshed_at ?? $article->updated_at ?? $article->published_at;
        $ageDays = $updated ? $updated->diffInDays(now()) : $staleDays;
        if ($ageDays > $staleDays) {
            $score -= min(50, (int) (($ageDays - $staleDays) * 2));
        } elseif ($ageDays > 14) {
            $score -= (int) ($ageDays / 2);
        }

        // Proxy for organic interest until Search Console metrics are wired.
        $views = (int) $article->view_count;
        if ($views < 10) {
            $score -= 10;
        } elseif ($views > 200) {
            $score += 5;
        }

        $links = count($article->link_recommendations ?? []);
        if ($links < 2) {
            $score -= 8;
        }

        $body = (string) $article->body;
        if (preg_match('/20(2[0-4]|19)/', $body)) {
            $score -= 15; // likely outdated year statistics
        }
        if (str_contains($body, 'href="http://')) {
            $score -= 5;
        }

        return max(0, min(100, $score));
    }

    /**
     * @return list<Article>
     */
    public function processQueue(): array
    {
        $min = (int) config('content.refresh.min_score_to_queue', 40);
        $limit = (int) config('content.refresh.daily_limit', 3);
        $ranked = $this->rankStale()->filter(fn (array $row) => $row['score'] <= $min)->take($limit);
        $refreshed = [];

        foreach ($ranked as $row) {
            /** @var Article $article */
            $article = $row['article'];
            $article->update(['freshness_score' => $row['score']]);

            if (! $article->content_event_id) {
                // Seed / manual articles: SEO + links + media only.
                $this->seo->enrich($article);
                $this->links->apply($article->refresh());
                $this->media->generate($article->refresh());
                $article->update([
                    'last_refreshed_at' => now(),
                    'freshness_score' => 90,
                ]);
                $this->audits->log('refreshed_lightweight', $article, after: ['score_before' => $row['score']]);
                $refreshed[] = $article->refresh();
                continue;
            }

            // Guide/article refreshes are low priority — AI only if plenty of budget remains.
            $this->drafts->regenerate($article, AiBudgetManager::PRIORITY_LOW);
            $this->seo->enrich($article->refresh());
            $this->links->apply($article->refresh());
            $this->media->generate($article->refresh());
            $article->update([
                'status' => Article::STATUS_PUBLISHED,
                'last_refreshed_at' => now(),
                'freshness_score' => 95,
            ]);
            $this->publishing->approveAndPublish($article->refresh());
            $this->audits->log('refreshed_full', $article, after: ['score_before' => $row['score']]);
            $refreshed[] = $article->refresh();
        }

        return $refreshed;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentAudit;
use App\Models\ContentDistribution;
use App\Models\ContentEvent;
use App\Models\ContentJob;
use App\Services\Content\AiBudgetManager;
use App\Services\Content\ContentAutomationOrchestrator;
use App\Services\Content\ContentAuditLogger;
use App\Services\Content\ContentAutomationRules;
use App\Services\Content\DraftGeneratorService;
use App\Services\Content\LinkRecommendationService;
use App\Services\Content\MediaPipelineService;
use App\Services\Content\PublishingService;
use App\Http\Requests\Admin\AutomationIndexRequest;
use App\Http\Requests\Admin\SaveArticleEditRequest;
use App\Services\Content\SeoEnrichmentService;
use App\Support\SafeError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(AutomationIndexRequest $request): Response
    {
        $today = now()->startOfDay();
        $filter = (string) ($request->validated('filter') ?? 'pending_review');

        $generated = Article::query()->whereNotNull('content_event_id')->count();
        $approved = Article::query()->whereNotNull('approved_at')->count();
        $published = Article::query()->where('status', Article::STATUS_PUBLISHED)->count();
        $distTotal = ContentDistribution::query()->whereIn('status', ['sent', 'failed'])->count();
        $distSent = ContentDistribution::query()->where('status', 'sent')->count();

        $reviewSamples = Article::query()
            ->whereNotNull('approved_at')
            ->whereNotNull('created_at')
            ->latest('approved_at')
            ->limit(30)
            ->get(['created_at', 'approved_at', 'published_at']);

        $avgReviewHours = $reviewSamples->isEmpty()
            ? 0.0
            : round((float) $reviewSamples->avg(fn (Article $a) => $a->created_at->diffInMinutes($a->approved_at) / 60), 1);

        $avgPublishMinutes = $reviewSamples
            ->filter(fn (Article $a) => $a->published_at && $a->created_at)
            ->avg(fn (Article $a) => $a->created_at->diffInMinutes($a->published_at));

        $stats = [
            'articles_generated' => $generated,
            'articles_approved' => $approved,
            'approval_rate' => $generated > 0 ? round(($approved / $generated) * 100, 1) : 0.0,
            'avg_review_hours' => $avgReviewHours,
            'avg_publish_minutes' => round((float) ($avgPublishMinutes ?? 0), 1),
            'distribution_success_rate' => $distTotal > 0 ? round(($distSent / $distTotal) * 100, 1) : 0.0,
            'ai_failures' => ContentAudit::query()->where('action', 'draft_generated_template_fallback')->count()
                + ContentJob::query()->where('stage', 'draft')->where('status', 'failed')->count(),
            'template_fallback_count' => ContentAudit::query()->where('action', 'draft_generated_template_fallback')->count(),
            'drafts_awaiting_review' => Article::query()->where('status', Article::STATUS_PENDING_REVIEW)->count(),
            'published_today' => Article::query()
                ->where('status', Article::STATUS_PUBLISHED)
                ->where('published_at', '>=', $today)
                ->count(),
            'queued' => Article::query()->where('status', Article::STATUS_QUEUED)->count(),
            'failed_jobs' => ContentJob::query()->where('status', 'failed')->count(),
            'distribution_queued' => ContentDistribution::query()->where('status', 'queued')->count(),
            'distribution_failed' => ContentDistribution::query()->where('status', 'failed')->count(),
            'indexnow_sent_today' => ContentDistribution::query()
                ->where('channel', 'indexnow')
                ->where('status', 'sent')
                ->where('sent_at', '>=', $today)
                ->count(),
            'ai_usage' => Article::query()->where('ai_provider', 'openai_compatible')->count(),
            'template_usage' => Article::query()->where('ai_provider', 'template')->whereNotNull('content_event_id')->count(),
            'avg_pipeline_seconds' => 0.0,
            'published_total' => $published,
        ];

        $sample = ContentJob::query()
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->where('stage', 'draft')
            ->latest('id')
            ->limit(20)
            ->get(['started_at', 'finished_at']);
        if ($sample->isNotEmpty()) {
            $stats['avg_pipeline_seconds'] = round((float) $sample->avg(
                fn (ContentJob $j) => $j->started_at->diffInSeconds($j->finished_at)
            ), 1);
        }

        $articles = $this->filteredArticles($filter)
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (Article $a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->title,
                'status' => $a->status,
                'event_type' => $a->event_type,
                'ai_provider' => $a->ai_provider,
                'freshness_score' => $a->freshness_score,
                'published_at' => optional($a->published_at)?->toDateTimeString(),
                'updated_at' => optional($a->updated_at)?->toDateTimeString(),
                'attention' => $this->needsAttention($a),
            ]);

        $events = ContentEvent::query()
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (ContentEvent $e) => [
                'id' => $e->id,
                'type' => $e->type,
                'severity' => $e->severity,
                'status' => $e->status,
                'skip_reason' => $e->skip_reason,
                'detected_at' => optional($e->detected_at)?->toDateTimeString(),
            ]);

        $distributions = ContentDistribution::query()
            ->with('article:id,slug,title')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (ContentDistribution $d) => [
                'id' => $d->id,
                'channel' => $d->channel,
                'status' => $d->status,
                'article' => $d->article?->title,
                'slug' => $d->article?->slug,
                'last_error' => $d->last_error
                    ? SafeError::storedMessage('Distribution failed. See server logs for details.')
                    : null,
                'sent_at' => optional($d->sent_at)?->toDateTimeString(),
            ]);

        $failedJobs = ContentJob::query()
            ->where('status', 'failed')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (ContentJob $j) => [
                'id' => $j->id,
                'article_id' => $j->article_id,
                'stage' => $j->stage,
                'error' => SafeError::storedMessage('Job failed. See server logs for details.'),
                'finished_at' => optional($j->finished_at)?->toDateTimeString(),
            ]);

        $audits = ContentAudit::query()
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ContentAudit $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'article_id' => $a->article_id,
                'actor_type' => $a->actor_type,
                'created_at' => optional($a->created_at)?->toDateTimeString(),
            ]);

        $filterCounts = [
            'pending_review' => Article::query()->where('status', Article::STATUS_PENDING_REVIEW)->count(),
            'failed' => ContentJob::query()
                ->where('status', 'failed')
                ->whereNotNull('article_id')
                ->pluck('article_id')
                ->unique()
                ->count(),
            'published' => Article::query()->where('status', Article::STATUS_PUBLISHED)->whereNotNull('content_event_id')->count(),
            'archived' => Article::query()->where('status', Article::STATUS_ARCHIVED)->count(),
            'ai_generated' => Article::query()->where('ai_provider', 'openai_compatible')->count(),
            'template_generated' => Article::query()->where('ai_provider', 'template')->whereNotNull('content_event_id')->count(),
            'requires_attention' => Article::query()
                ->where(function (Builder $q) {
                    $q->where('status', Article::STATUS_PENDING_REVIEW)
                        ->orWhereIn('id', ContentJob::query()->where('status', 'failed')->select('article_id'));
                })
                ->count(),
            'all' => Article::query()->whereNotNull('content_event_id')->count(),
        ];

        /** @var AiBudgetManager $budget */
        $budget = app(AiBudgetManager::class);
        /** @var ContentAutomationRules $rules */
        $rules = app(ContentAutomationRules::class);

        $ruleCards = collect(config('content.rules', []))
            ->except('default')
            ->map(fn (array $rule, string $type) => [
                'type' => $type,
                ...$rules->forEventType($type),
            ])
            ->sortBy(fn (array $r) => match ($r['ai_priority'] ?? 'none') {
                'high' => 0,
                'medium' => 1,
                'low' => 2,
                default => 3,
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Automation', [
            'stats' => $stats,
            'articles' => $articles,
            'filter' => $filter,
            'filter_counts' => $filterCounts,
            'events' => $events,
            'distributions' => $distributions,
            'failed_jobs' => $failedJobs,
            'audits' => $audits,
            'ai_budget' => $budget->snapshot(),
            'automation_rules' => $ruleCards,
            'config' => [
                'enabled' => (bool) config('content.enabled'),
                'auto_publish' => (bool) config('content.auto_publish'),
                'review_required' => (bool) config('content.review_required'),
                'pipeline_mode' => (string) config('content.pipeline_mode'),
                'ai_provider' => config('content.ai.provider'),
                'ai_fallback' => config('content.ai.fallback'),
                'prompt_version' => config('content.ai.prompt_version'),
                'template_version' => config('content.ai.template_version'),
                'daily_auto_publish' => (bool) config('content.rules.fuel_price_increase.auto_publish'),
            ],
            'auth_user' => [
                'name' => auth()->user()?->name,
                'email' => auth()->user()?->email,
                'is_owner' => (bool) auth()->user()?->is_owner,
            ],
        ]);
    }

    private function filteredArticles(string $filter): Builder
    {
        $q = Article::query();

        return match ($filter) {
            'pending_review' => $q->where('status', Article::STATUS_PENDING_REVIEW),
            'failed' => $q->whereIn('id', ContentJob::query()->where('status', 'failed')->select('article_id')),
            'published' => $q->where('status', Article::STATUS_PUBLISHED)->whereNotNull('content_event_id'),
            'archived' => $q->where('status', Article::STATUS_ARCHIVED),
            'ai_generated' => $q->where('ai_provider', 'openai_compatible'),
            'template_generated' => $q->where('ai_provider', 'template')->whereNotNull('content_event_id'),
            'requires_attention' => $q->where(function (Builder $inner) {
                $inner->where('status', Article::STATUS_PENDING_REVIEW)
                    ->orWhereIn('id', ContentJob::query()->where('status', 'failed')->select('article_id'));
            }),
            default => $q->whereNotNull('content_event_id'),
        };
    }

    private function needsAttention(Article $article): bool
    {
        if ($article->status === Article::STATUS_PENDING_REVIEW) {
            return true;
        }

        return ContentJob::query()
            ->where('article_id', $article->id)
            ->where('status', 'failed')
            ->exists();
    }

    public function detect(ContentAutomationOrchestrator $orchestrator): RedirectResponse
    {
        $articles = $orchestrator->handlePriceSync(['message' => 'admin manual detect']);

        return back()->with('success', 'Change detection finished — '.count($articles).' article(s) planned.');
    }

    public function publish(Article $article, PublishingService $publishing): RedirectResponse
    {
        try {
            $publishing->approveAndPublish($article, auth()->user());
        } catch (\Throwable $e) {
            SafeError::log($e, 'Admin publish failed', ['article_id' => $article->id]);

            return back()->with('error', SafeError::storedMessage('Publish failed. See server logs for details.'));
        }

        return back()->with('success', 'Published: '.$article->title);
    }

    public function archive(Article $article, PublishingService $publishing): RedirectResponse
    {
        $publishing->archive($article, auth()->user());

        return back()->with('success', 'Archived: '.$article->title);
    }

    public function regenerate(
        Article $article,
        DraftGeneratorService $drafts,
        SeoEnrichmentService $seo,
        LinkRecommendationService $links,
        MediaPipelineService $media,
        PublishingService $publishing,
    ): RedirectResponse {
        try {
            $drafts->regenerate($article);
            $seo->enrich($article->refresh());
            $links->apply($article->refresh());
            $media->generate($article->refresh());
            if (config('content.review_required') || config('content.auto_submit_review')) {
                $publishing->submitForReview($article->refresh());
            }
        } catch (\Throwable $e) {
            SafeError::log($e, 'Admin regenerate failed', ['article_id' => $article->id]);

            return back()->with('error', SafeError::storedMessage('Regenerate failed. See server logs for details.'));
        }

        return back()->with('success', 'Regenerated draft #'.$article->id);
    }

    public function saveHumanEdit(SaveArticleEditRequest $request, Article $article, ContentAuditLogger $audits): RedirectResponse
    {
        $data = $request->validated();

        $before = $article->only(['title', 'excerpt', 'body', 'meta_title', 'meta_description']);
        $article->fill($data + [
            'human_edited_at' => now(),
            'status' => $article->status === Article::STATUS_PUBLISHED
                ? Article::STATUS_PUBLISHED
                : Article::STATUS_PENDING_REVIEW,
        ])->save();

        $audits->log(
            'human_edit',
            $article,
            before: $before,
            after: $article->only(['title', 'excerpt', 'body', 'meta_title', 'meta_description']),
            actorType: 'user',
            actor: auth()->user(),
        );

        return back()->with('success', 'Human edits saved for #'.$article->id);
    }
}

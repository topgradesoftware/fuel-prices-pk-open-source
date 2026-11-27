<?php

namespace App\Services\Content;

use App\Contracts\AiDraftProvider;
use App\Models\Article;
use App\Models\ContentEvent;
use App\Models\ContentJob;
use App\Services\Content\Providers\GeminiDraftProvider;
use App\Services\Content\Providers\OpenAiCompatibleDraftProvider;
use App\Services\Content\Providers\TemplateDraftProvider;
use App\Support\SafeError;
use Illuminate\Support\Str;

class DraftGeneratorService
{
    public function __construct(
        private readonly TemplateDraftProvider $templates,
        private readonly OpenAiCompatibleDraftProvider $openai,
        private readonly GeminiDraftProvider $gemini,
        private readonly ContentAutomationRules $rules,
        private readonly AiBudgetManager $budget,
        private readonly ContentAuditLogger $audits,
    ) {}

    public function generate(Article $article, ?string $priorityOverride = null): Article
    {
        $event = $article->contentEvent ?? ContentEvent::query()->find($article->content_event_id);
        if ($event === null) {
            throw new \RuntimeException('Article '.$article->id.' has no content event');
        }

        $job = $this->startJob($article, $event, 'draft');
        $rule = $this->rules->forEventType($article->event_type ?: $event->type);
        if ($priorityOverride !== null) {
            $rule['ai_priority'] = strtolower($priorityOverride);
        }

        try {
            $draft = $this->resolveDraft($article, $event, $rule);
            $before = $article->only(['title', 'excerpt', 'body', 'status']);
            $usedFallback = (bool) data_get($draft, 'meta.used_fallback', false);
            $providerName = $usedFallback
                ? 'template'
                : (string) (data_get($draft, 'meta.provider') ?: 'template');

            $article->fill([
                'title' => $draft['title'],
                'excerpt' => Str::limit($draft['excerpt'], 480),
                'body' => $draft['body'],
                'tags' => $draft['tags'] ?? $article->tags,
                'faq' => $draft['faq'] ?? [],
                'status' => Article::STATUS_DRAFT,
                'ai_provider' => $providerName,
                'ai_model' => (string) (data_get($draft, 'meta.model')
                    ?: ($providerName === 'template'
                        ? 'template-'.config('content.ai.template_version', 'v1')
                        : config('content.ai.model'))),
                'prompt_version' => config('content.ai.prompt_version'),
                'template_version' => config('content.ai.template_version'),
                'reading_minutes' => max(2, (int) ceil(str_word_count(strip_tags($draft['body'])) / 200)),
            ])->save();

            $this->finishJob($job, 'completed', [
                'provider' => $providerName,
                'used_fallback' => $usedFallback,
                'fallback_reason' => data_get($draft, 'meta.fallback_reason'),
                'rule' => $rule,
            ]);
            $this->audits->log(
                $usedFallback ? 'draft_generated_template_fallback' : 'draft_generated',
                $article,
                $event,
                $before,
                [
                    'title' => $article->title,
                    'status' => $article->status,
                    'provider' => $providerName,
                    'used_fallback' => $usedFallback,
                    'use_ai_rule' => $rule['use_ai'],
                ],
            );

            return $article->refresh();
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.draft failed', ['article_id' => $article->id]);
            $this->finishJob($job, 'failed', error: SafeError::storedMessage('Draft generation failed. See server logs for details.'));
            throw $e;
        }
    }

    /**
     * @param  array{use_ai: bool, review_required: bool, auto_publish: bool, label: string, ai_priority: string}  $rule
     * @return array{title:string,excerpt:string,body:string,tags:list<string>,faq:list<array{question:string,answer:string}>,meta?:array<string,mixed>}
     */
    private function resolveDraft(Article $article, ContentEvent $event, array $rule): array
    {
        $priority = $rule['ai_priority'] ?? AiBudgetManager::PRIORITY_MEDIUM;
        $ai = $this->activeAiProvider();

        if (! $rule['use_ai'] || $priority === AiBudgetManager::PRIORITY_NONE) {
            $draft = $this->templates->generate($article, $event);
            $draft['meta'] = array_merge($draft['meta'] ?? [], [
                'provider' => 'template',
                'model' => 'template-'.config('content.ai.template_version', 'v1'),
                'reason' => 'content_type_uses_template',
                'ai_priority' => $priority,
            ]);

            return $draft;
        }

        $estimated = $this->budget->estimatePerRequestUsd();
        $gate = $this->budget->canUseAi($priority, $estimated);
        if (! $gate['allowed']) {
            $this->budget->record(
                status: 'blocked_budget',
                article: $article,
                event: $event,
                provider: $ai->name(),
                meta: [
                    'reason' => $gate['reason'] ?? 'budget',
                    'ai_priority' => $priority,
                    'estimated_cost_usd' => $estimated,
                ],
            );

            $draft = $this->templates->generate($article, $event);
            $draft['meta'] = array_merge($draft['meta'] ?? [], [
                'used_fallback' => true,
                'fallback_provider' => config('content.ai.fallback', 'template'),
                'fallback_reason' => $gate['reason'] ?? 'budget',
                'requested_provider' => $ai->name(),
                'provider' => 'template',
                'ai_priority' => $priority,
                'estimated_cost_usd' => $estimated,
            ]);

            return $draft;
        }

        $draft = $ai->generate($article, $event);
        $draft['meta'] = array_merge($draft['meta'] ?? [], [
            'ai_priority' => $priority,
            'estimated_cost_usd' => $estimated,
        ]);

        return $draft;
    }

    private function activeAiProvider(): AiDraftProvider
    {
        return match ((string) config('content.ai.provider')) {
            'gemini' => $this->gemini,
            'openai_compatible', 'openai' => $this->openai,
            default => $this->openai,
        };
    }

    public function regenerate(Article $article, ?string $priorityOverride = null): Article
    {
        $this->audits->log('draft_regenerate_requested', $article, meta: [
            'priority' => $priorityOverride,
        ]);

        return $this->generate($article, $priorityOverride);
    }

    private function startJob(Article $article, ContentEvent $event, string $stage): ContentJob
    {
        return ContentJob::query()->create([
            'content_event_id' => $event->id,
            'article_id' => $article->id,
            'stage' => $stage,
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function finishJob(ContentJob $job, string $status, ?array $meta = null, ?string $error = null): void
    {
        $job->update([
            'status' => $status,
            'meta' => $meta,
            'error' => $error,
            'finished_at' => now(),
        ]);
    }
}

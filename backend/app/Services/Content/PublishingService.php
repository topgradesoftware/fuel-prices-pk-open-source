<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentEvent;
use App\Models\User;
use App\Services\Content\Distribution\DistributionManager;
use RuntimeException;

class PublishingService
{
    public function __construct(
        private readonly DistributionManager $distribution,
        private readonly ContentAuditLogger $audits,
        private readonly DuplicateContentGuard $duplicates,
        private readonly ContentAutomationRules $rules,
    ) {}

    public function submitForReview(Article $article): Article
    {
        $rule = $this->rules->forEventType($article->event_type);

        if ($this->rules->shouldAutoPublish($article->event_type)) {
            $this->audits->log('auto_publish_by_rule', $article, after: $rule);

            return $this->approveAndPublish($article);
        }

        $before = ['status' => $article->status];
        $article->update(['status' => Article::STATUS_PENDING_REVIEW]);
        $this->audits->log('submitted_for_review', $article, before: $before, after: [
            'status' => $article->status,
            'rule' => $rule,
        ]);

        return $article->refresh();
    }

    public function approveAndPublish(Article $article, ?User $actor = null): Article
    {
        $dup = $this->duplicates->checkBeforePublish($article);
        if ($dup['duplicate'] && ($dup['reason'] ?? '') !== 'already_published') {
            $this->audits->log('publish_blocked_duplicate', $article, meta: $dup);
            throw new RuntimeException('Duplicate publish blocked: '.($dup['reason'] ?? 'duplicate'));
        }

        if (($dup['reason'] ?? null) === 'already_published') {
            $this->distribution->queueAndDispatch($article);

            return $article->refresh();
        }

        $before = ['status' => $article->status, 'published_at' => optional($article->published_at)?->toIso8601String()];

        $article->fill([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => $article->published_at ?? now(),
            'approved_at' => now(),
            'approved_by' => $actor?->id,
            'freshness_score' => 100,
            'last_refreshed_at' => now(),
        ])->save();

        if ($article->content_event_id) {
            ContentEvent::query()->whereKey($article->content_event_id)->update([
                'status' => ContentEvent::STATUS_PROCESSED,
                'processed_at' => now(),
            ]);
        }

        $this->audits->log(
            'published',
            $article,
            before: $before,
            after: ['status' => $article->status, 'published_at' => optional($article->published_at)?->toIso8601String()],
            actorType: $actor ? 'user' : 'system',
            actor: $actor,
        );

        $this->distribution->queueAndDispatch($article->refresh());

        return $article->refresh();
    }

    public function archive(Article $article, ?User $actor = null): Article
    {
        $before = ['status' => $article->status];
        $article->update(['status' => Article::STATUS_ARCHIVED]);
        $this->audits->log('archived', $article, before: $before, after: ['status' => Article::STATUS_ARCHIVED], actorType: $actor ? 'user' : 'system', actor: $actor);

        return $article->refresh();
    }
}

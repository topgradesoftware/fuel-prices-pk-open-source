<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentAudit;
use App\Models\ContentEvent;
use App\Models\User;

class ContentAuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        string $action,
        ?Article $article = null,
        ?ContentEvent $event = null,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
        string $actorType = 'system',
        ?User $actor = null,
    ): ContentAudit {
        return ContentAudit::query()->create([
            'article_id' => $article?->id,
            'content_event_id' => $event?->id ?? $article?->content_event_id,
            'actor_type' => $actorType,
            'actor_id' => $actor?->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'meta' => $meta,
        ]);
    }
}

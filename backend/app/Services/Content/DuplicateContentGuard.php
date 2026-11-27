<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentEvent;
use Illuminate\Support\Str;

/**
 * Blocks duplicate publication / planning for the same story.
 */
class DuplicateContentGuard
{
    /**
     * @return array{duplicate: bool, reason?: string, article_id?: int}
     */
    public function checkEvent(ContentEvent $event, string $workingTitle, string $slug): array
    {
        if ($event->fingerprint !== '') {
            $byFingerprint = Article::query()
                ->where('source_fingerprint', $event->fingerprint)
                ->whereNotIn('status', [Article::STATUS_ARCHIVED])
                ->first(['id', 'status', 'slug']);
            if ($byFingerprint) {
                return [
                    'duplicate' => true,
                    'reason' => 'same_fingerprint',
                    'article_id' => $byFingerprint->id,
                ];
            }
        }

        // content:test-ai marks payload.test — only fingerprint uniqueness applies.
        if (data_get($event->payload, 'test') === true) {
            return ['duplicate' => false];
        }

        $bySlug = Article::query()
            ->where('slug', $slug)
            ->whereNotIn('status', [Article::STATUS_ARCHIVED])
            ->first(['id']);
        if ($bySlug) {
            return ['duplicate' => true, 'reason' => 'same_slug', 'article_id' => $bySlug->id];
        }

        $titleKey = Str::lower(trim($workingTitle));
        if ($titleKey !== '') {
            $byTitle = Article::query()
                ->whereRaw('LOWER(title) = ?', [$titleKey])
                ->whereNotIn('status', [Article::STATUS_ARCHIVED])
                ->first(['id']);
            if ($byTitle) {
                return ['duplicate' => true, 'reason' => 'same_title', 'article_id' => $byTitle->id];
            }
        }

        $effectiveDate = data_get($event->payload, 'effective_date');
        $primaryFuel = data_get($event->payload, 'primary_fuel');
        if (is_string($effectiveDate) && $effectiveDate !== '' && is_string($primaryFuel) && $primaryFuel !== '') {
            $byDate = Article::query()
                ->where('event_type', $event->type)
                ->whereNotIn('status', [Article::STATUS_ARCHIVED])
                ->where('created_at', '>=', now()->subDays(3))
                ->get(['id', 'content_event_id'])
                ->first(function (Article $article) use ($effectiveDate, $primaryFuel) {
                    $payload = $article->contentEvent?->payload
                        ?? ContentEvent::query()->whereKey($article->content_event_id)->value('payload');
                    if (is_string($payload)) {
                        $payload = json_decode($payload, true);
                    }
                    if (! is_array($payload)) {
                        return false;
                    }

                    return ($payload['effective_date'] ?? null) === $effectiveDate
                        && ($payload['primary_fuel'] ?? null) === $primaryFuel;
                });

            if ($byDate) {
                return [
                    'duplicate' => true,
                    'reason' => 'same_effective_fuel_date',
                    'article_id' => $byDate->id,
                ];
            }
        }

        return ['duplicate' => false];
    }

    /**
     * Extra publish-time guard (race / manual republish).
     *
     * @return array{duplicate: bool, reason?: string, article_id?: int}
     */
    public function checkBeforePublish(Article $article): array
    {
        if ($article->status === Article::STATUS_PUBLISHED && $article->published_at) {
            return ['duplicate' => true, 'reason' => 'already_published', 'article_id' => $article->id];
        }

        if (filled($article->source_fingerprint)) {
            $other = Article::query()
                ->where('source_fingerprint', $article->source_fingerprint)
                ->where('id', '!=', $article->id)
                ->where('status', Article::STATUS_PUBLISHED)
                ->first(['id']);
            if ($other) {
                return [
                    'duplicate' => true,
                    'reason' => 'fingerprint_already_published',
                    'article_id' => $other->id,
                ];
            }
        }

        $otherSlug = Article::query()
            ->where('slug', $article->slug)
            ->where('id', '!=', $article->id)
            ->where('status', Article::STATUS_PUBLISHED)
            ->first(['id']);
        if ($otherSlug) {
            return ['duplicate' => true, 'reason' => 'slug_already_published', 'article_id' => $otherSlug->id];
        }

        return ['duplicate' => false];
    }
}

<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentEvent;
use App\Models\ContentJob;
use Illuminate\Support\Str;

/**
 * Classifies events and creates queued article shells with the right template tone.
 */
class ContentPlanner
{
    public function __construct(
        private readonly ContentAuditLogger $audits,
        private readonly DuplicateContentGuard $duplicates,
    ) {}

    /**
     * @param  list<ContentEvent>  $events
     * @return list<Article>
     */
    public function plan(array $events): array
    {
        $max = (int) config('content.max_articles_per_sync', 2);
        $planned = [];

        foreach ($events as $event) {
            if (count($planned) >= $max) {
                $event->update([
                    'status' => ContentEvent::STATUS_SKIPPED,
                    'skip_reason' => 'max_articles_per_sync',
                ]);
                continue;
            }

            if ($event->status === ContentEvent::STATUS_SKIPPED) {
                continue;
            }

            $meta = config('content.event_types.'.$event->type, [
                'category' => 'fuel-news',
                'tone' => 'clear',
            ]);

            $title = $this->workingTitle($event);
            $slugBase = Str::slug(Str::limit($title, 70, ''));
            $slug = $slugBase !== '' ? $slugBase : $event->type.'-'.now()->format('Ymd');

            $dup = $this->duplicates->checkEvent($event, $title, $slug);
            if ($dup['duplicate']) {
                $event->update([
                    'status' => ContentEvent::STATUS_SKIPPED,
                    'skip_reason' => $dup['reason'] ?? 'duplicate',
                    'processed_at' => now(),
                ]);
                $this->audits->log('article_skipped_duplicate', event: $event, meta: $dup);
                continue;
            }

            $slug = $this->uniqueSlug($slug);

            $article = Article::query()->create([
                'slug' => $slug,
                'status' => Article::STATUS_QUEUED,
                'title' => $title,
                'excerpt' => 'Draft queued by content planner.',
                'body' => '',
                'category' => $meta['category'],
                'event_type' => $event->type,
                'content_event_id' => $event->id,
                'tags' => [$event->type, 'automation'],
                'author_name' => 'Fuel Prices PK Editorial',
                'prompt_version' => config('content.ai.prompt_version'),
                'template_version' => config('content.ai.template_version'),
                'source_fingerprint' => $event->fingerprint,
                'reading_minutes' => 3,
            ]);

            ContentJob::query()->create([
                'content_event_id' => $event->id,
                'article_id' => $article->id,
                'stage' => 'plan',
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => [
                    'tone' => $meta['tone'] ?? 'clear',
                    'category' => $meta['category'],
                ],
            ]);

            $event->update([
                'status' => ContentEvent::STATUS_PLANNED,
                'planned_at' => now(),
            ]);

            $this->audits->log('article_planned', $article, $event, after: [
                'slug' => $slug,
                'event_type' => $event->type,
                'tone' => $meta['tone'] ?? null,
            ]);

            $planned[] = $article;
        }

        return $planned;
    }

    private function workingTitle(ContentEvent $event): string
    {
        $payload = $event->payload ?? [];
        $fuel = Str::headline((string) ($payload['primary_fuel'] ?? 'fuel'));
        $delta = isset($payload['primary_delta']) ? (float) $payload['primary_delta'] : null;
        $price = isset($payload['primary_price']) ? number_format((float) $payload['primary_price'], 2) : null;
        $date = $payload['effective_date'] ?? now()->toDateString();

        return match ($event->type) {
            'fuel_price_increase' => sprintf(
                '%s price rises%s in Pakistan — Rs %s/L (effective %s)',
                $fuel,
                $delta !== null ? ' by Rs '.number_format(abs($delta), 2) : '',
                $price ?? '—',
                $date,
            ),
            'fuel_price_decrease' => sprintf(
                '%s price cut%s in Pakistan — Rs %s/L (effective %s)',
                $fuel,
                $delta !== null ? ' by Rs '.number_format(abs($delta), 2) : '',
                $price ?? '—',
                $date,
            ),
            'government_notification' => 'Government fuel price notification — '.$date,
            'weekly_summary' => filled($payload['period_label'] ?? null)
                ? 'Weekly fuel price summary — Pakistan ('.$payload['period_label'].')'
                : 'Weekly fuel price summary — Pakistan (week of '.$date.')',
            'monthly_summary' => filled($payload['period_label'] ?? null)
                ? 'Monthly fuel price report — Pakistan ('.$payload['period_label'].')'
                : 'Monthly fuel price report — Pakistan ('.$date.')',
            'market_analysis' => 'Pakistan fuel market analysis — '.$date,
            'guide_update' => 'Updated fuel guide — '.$date,
            'opec_event' => 'OPEC-related fuel market update — '.$date,
            default => 'Fuel update — '.$date,
        };
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentJob;
use App\Support\SafeError;
use App\Support\Seo;
use Illuminate\Support\Str;

class SeoEnrichmentService
{
    public function __construct(
        private readonly ContentAuditLogger $audits,
    ) {}

    public function enrich(Article $article): Article
    {
        $job = ContentJob::query()->create([
            'content_event_id' => $article->content_event_id,
            'article_id' => $article->id,
            'stage' => 'seo',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now(),
        ]);

        try {
            $fuel = $this->primaryFuelLabel($article);
            $metaTitle = strlen($article->title) <= 60
                ? $article->title
                : Str::limit($article->title, 57, '…');
            $metaDescription = Str::limit($article->excerpt ?: strip_tags((string) $article->body), 155, '…');
            $keywords = array_values(array_unique(array_filter([
                ...($article->tags ?? []),
                'pakistan fuel prices',
                strtolower($fuel).' price pakistan',
                'petrol price today',
                'diesel price pakistan',
            ])));

            $toc = $article->toc();
            $suggestedCities = [
                ['name' => 'Karachi', 'path' => '/petrol-price/karachi'],
                ['name' => 'Lahore', 'path' => '/petrol-price/lahore'],
                ['name' => 'Islamabad', 'path' => '/petrol-price/islamabad'],
                ['name' => 'Rawalpindi', 'path' => '/petrol-price/rawalpindi'],
                ['name' => 'Faisalabad', 'path' => '/petrol-price/faisalabad'],
            ];
            $suggestedCalculators = [
                ['name' => 'Fuel cost calculator', 'path' => '/fuel-cost-calculator'],
                ['name' => 'Trip cost calculator', 'path' => '/trip-cost-calculator'],
                ['name' => 'Mileage calculator', 'path' => '/mileage-calculator'],
                ['name' => 'Monthly fuel budget', 'path' => '/monthly-fuel-budget-calculator'],
            ];

            $breadcrumbs = [
                ['name' => 'Home', 'path' => '/'],
                ['name' => 'Guides', 'path' => '/guides'],
                ['name' => $article->title, 'path' => $article->urlPath()],
            ];

            $related = Article::query()
                ->published()
                ->where('id', '!=', $article->id)
                ->where(function ($q) use ($article) {
                    $q->where('category', $article->category);
                    foreach ($article->tags ?? [] as $tag) {
                        $q->orWhereJsonContains('tags', $tag);
                    }
                })
                ->latest('published_at')
                ->take(4)
                ->get(['slug', 'title', 'category'])
                ->map(fn (Article $a) => [
                    'slug' => $a->slug,
                    'title' => $a->title,
                    'path' => $a->urlPath(),
                    'category' => $a->category,
                ])
                ->values()
                ->all();

            $seoPayload = [
                'keywords' => $keywords,
                'toc' => $toc,
                'breadcrumbs' => $breadcrumbs,
                'related_articles' => $related,
                'suggested_calculators' => $suggestedCalculators,
                'suggested_cities' => $suggestedCities,
                'canonical' => Seo::absoluteUrl($article->urlPath()),
                'reading_minutes' => $article->reading_minutes,
                'json_ld_ready' => true,
            ];

            $slug = $article->slug;
            if ($slug === '' || str_starts_with($slug, 'queued-')) {
                $slug = $this->uniqueSlug(Str::slug(Str::limit($article->title, 70, '')));
            }

            $article->fill([
                'slug' => $slug,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'seo_payload' => $seoPayload,
                'reading_minutes' => max(2, (int) ceil(str_word_count(strip_tags((string) $article->body)) / 200)),
            ])->save();

            $job->update(['status' => 'completed', 'finished_at' => now(), 'meta' => ['keywords' => count($keywords)]]);
            $this->audits->log('seo_enriched', $article, after: [
                'meta_title' => $metaTitle,
                'slug' => $slug,
            ]);

            return $article->refresh();
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.seo failed', ['article_id' => $article->id]);
            $job->update([
                'status' => 'failed',
                'error' => SafeError::storedMessage('SEO enrichment failed. See server logs for details.'),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    private function primaryFuelLabel(Article $article): string
    {
        $payload = $article->contentEvent?->payload ?? [];

        return Str::headline((string) ($payload['primary_fuel'] ?? 'petrol'));
    }

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'fuel-update-'.now()->format('Ymd');
        $slug = $base;
        $i = 2;
        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

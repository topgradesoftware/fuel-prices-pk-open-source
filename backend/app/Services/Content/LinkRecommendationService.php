<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\City;
use App\Models\ContentJob;
use App\Support\SafeError;
use Illuminate\Support\Str;

/**
 * Ranks internal destinations and injects a capped "Related links" block.
 */
class LinkRecommendationService
{
    public function __construct(
        private readonly ContentAuditLogger $audits,
    ) {}

    public function apply(Article $article): Article
    {
        $job = ContentJob::query()->create([
            'content_event_id' => $article->content_event_id,
            'article_id' => $article->id,
            'stage' => 'links',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now(),
        ]);

        try {
            $candidates = $this->rank($article);
            $max = (int) config('content.linking.max_links', 6);
            $minScore = (float) config('content.linking.min_score', 0.25);
            $picked = array_slice(
                array_values(array_filter($candidates, fn (array $c) => $c['score'] >= $minScore)),
                0,
                $max,
            );

            $body = (string) $article->body;
            if (! str_contains($body, 'id="related-links"') && $picked !== []) {
                $body .= $this->renderBlock($picked);
            }

            $article->fill([
                'body' => $body,
                'link_recommendations' => $picked,
            ])->save();

            $job->update([
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => ['links' => count($picked)],
            ]);
            $this->audits->log('links_applied', $article, after: ['count' => count($picked)]);

            return $article->refresh();
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.links failed', ['article_id' => $article->id]);
            $job->update([
                'status' => 'failed',
                'error' => SafeError::storedMessage('Link recommendations failed. See server logs for details.'),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * @return list<array{title:string,path:string,type:string,score:float}>
     */
    public function rank(Article $article): array
    {
        $text = Str::lower($article->title.' '.$article->excerpt.' '.strip_tags((string) $article->body));
        $fuel = Str::lower((string) ($article->contentEvent?->payload['primary_fuel'] ?? 'petrol'));
        $fuelPath = match ($fuel) {
            'hsd', 'diesel' => 'diesel',
            'lpg' => 'lpg',
            'hobc', 'hi_octane', 'premium' => 'hobc',
            default => 'petrol',
        };

        $candidates = [
            ['title' => 'Live fuel prices', 'path' => '/', 'type' => 'home', 'score' => 0.55],
            ['title' => 'Fuel price history', 'path' => '/price-history', 'type' => 'history', 'score' => 0.7],
            ['title' => 'Fuel cost calculator', 'path' => '/fuel-cost-calculator', 'type' => 'calculator', 'score' => 0.75],
            ['title' => 'Trip cost calculator', 'path' => '/trip-cost-calculator', 'type' => 'calculator', 'score' => 0.65],
            ['title' => 'Mileage calculator', 'path' => '/mileage-calculator', 'type' => 'calculator', 'score' => 0.5],
            ['title' => 'Monthly fuel budget', 'path' => '/monthly-fuel-budget-calculator', 'type' => 'calculator', 'score' => 0.7],
            ['title' => 'Guides hub', 'path' => '/guides', 'type' => 'guides', 'score' => 0.4],
            ['title' => ucfirst($fuelPath).' price in Karachi', 'path' => "/{$fuelPath}-price/karachi", 'type' => 'city', 'score' => 0.8],
            ['title' => ucfirst($fuelPath).' price in Lahore', 'path' => "/{$fuelPath}-price/lahore", 'type' => 'city', 'score' => 0.78],
            ['title' => ucfirst($fuelPath).' price in Islamabad', 'path' => "/{$fuelPath}-price/islamabad", 'type' => 'city', 'score' => 0.76],
        ];

        if (str_contains($text, 'diesel') || $fuel === 'hsd') {
            $candidates[] = ['title' => 'Diesel price Pakistan', 'path' => '/diesel-price', 'type' => 'fuel', 'score' => 0.85];
        }
        if (str_contains($text, 'lpg') || $fuel === 'lpg') {
            $candidates[] = ['title' => 'LPG price Pakistan', 'path' => '/lpg-price', 'type' => 'fuel', 'score' => 0.85];
        }
        if (str_contains($text, 'hobc') || str_contains($text, 'octane')) {
            $candidates[] = ['title' => 'HOBC price Pakistan', 'path' => '/hobc-price', 'type' => 'fuel', 'score' => 0.85];
        }

        $news = Article::query()
            ->published()
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->take(8)
            ->get(['title', 'slug', 'category', 'tags']);

        foreach ($news as $related) {
            $overlap = 0;
            foreach ($article->tags ?? [] as $tag) {
                if (in_array($tag, $related->tags ?? [], true) || $related->category === $article->category) {
                    $overlap = 0.6;
                    break;
                }
            }
            $candidates[] = [
                'title' => $related->title,
                'path' => $related->urlPath(),
                'type' => 'news',
                'score' => 0.45 + $overlap,
            ];
        }

        // Optional city boost from DB if present.
        $city = City::query()->whereIn('slug', ['karachi', 'lahore', 'islamabad'])->value('slug');
        if ($city) {
            foreach ($candidates as &$c) {
                if ($c['type'] === 'city' && str_contains($c['path'], $city)) {
                    $c['score'] += 0.05;
                }
            }
            unset($c);
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        // Dedupe by path.
        $seen = [];
        $unique = [];
        foreach ($candidates as $c) {
            if (isset($seen[$c['path']])) {
                continue;
            }
            $seen[$c['path']] = true;
            $unique[] = $c;
        }

        return $unique;
    }

    /**
     * @param  list<array{title:string,path:string,type:string,score:float}>  $links
     */
    private function renderBlock(array $links): string
    {
        $items = '';
        foreach ($links as $link) {
            $title = e($link['title']);
            $path = e($link['path']);
            $items .= "<li><a href=\"{$path}\">{$title}</a></li>";
        }

        return '<h2 id="related-links">Related tools &amp; pages</h2><ul>'.$items.'</ul>';
    }
}

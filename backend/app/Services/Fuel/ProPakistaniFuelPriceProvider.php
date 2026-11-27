<?php

namespace App\Services\Fuel;

use App\Contracts\FuelPriceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * News RSS fast path for daily petrol/HSD (+ optional SKO) announcements.
 *
 * OGRA PDFs currently publish petrol/HSD only; kerosene often appears in news
 * wires (ProPakistani, Daily Pakistan) hours before OMC sites update.
 *
 * Strategy:
 *  1. Scan configured RSS feeds (stories drop off page 1 within hours)
 *  2. For fuel-titled items, parse the RSS body; if incomplete, fetch article HTML
 *  3. Accumulate products across articles — petrol+HSD required, SKO optional
 *
 * Only trusted when the article is fresh (< 36 h) and within sane price bounds.
 * First price match per product wins so "Last week…" figures are ignored.
 */
class ProPakistaniFuelPriceProvider implements FuelPriceProvider
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const MAX_AGE_HOURS = 36;

    private const FEED_PAGES = 5;

    /** @var list<string> */
    private readonly array $feedUrls;

    /**
     * @param  string|list<string>|null  $feedUrls
     */
    public function __construct(
        string|array|null $feedUrls = null,
    ) {
        if (is_string($feedUrls) && $feedUrls !== '') {
            $this->feedUrls = [$feedUrls];
        } elseif (is_array($feedUrls) && $feedUrls !== []) {
            $this->feedUrls = array_values(array_filter($feedUrls, fn ($u) => is_string($u) && $u !== ''));
        } else {
            $this->feedUrls = [
                'https://propakistani.pk/category/business/feed/',
                'https://en.dailypakistan.com.pk/feed',
            ];
        }
    }

    public function name(): string
    {
        return 'propakistani';
    }

    public function latestPrices(): array
    {
        $byProduct = [];

        foreach ($this->feedUrls as $feedUrl) {
            for ($page = 1; $page <= self::FEED_PAGES; $page++) {
                $xml = $this->fetchFeed($feedUrl, $page);
                $this->mergeFeedInto($byProduct, $xml, fetchArticles: true, fallbackLink: $feedUrl);

                if (isset($byProduct['petrol'], $byProduct['hsd'], $byProduct['kerosene'])) {
                    return array_values($byProduct);
                }
            }
        }

        if (isset($byProduct['petrol'], $byProduct['hsd'])) {
            return array_values($byProduct);
        }

        return [];
    }

    /**
     * Parse a raw RSS body. Public so fixture tests can cover the regexes
     * without hitting the network.
     *
     * @return list<array<string, mixed>>
     */
    public function parseFeed(string $xml, bool $fetchArticles = false): array
    {
        $byProduct = [];
        $this->mergeFeedInto(
            $byProduct,
            $xml,
            fetchArticles: $fetchArticles,
            fallbackLink: $this->feedUrls[0] ?? 'https://propakistani.pk/category/business/feed/',
        );

        if (isset($byProduct['petrol'], $byProduct['hsd'])) {
            return array_values($byProduct);
        }

        return [];
    }

    /**
     * Parse WordPress REST API post payloads (used by unit tests / local).
     *
     * @param  list<array<string, mixed>>  $posts
     * @return list<array<string, mixed>>
     */
    public function parseApiPosts(array $posts): array
    {
        $byProduct = [];

        foreach ($posts as $post) {
            if (! is_array($post)) {
                continue;
            }

            $published = null;
            if (! empty($post['date_gmt'])) {
                try {
                    $published = Carbon::parse((string) $post['date_gmt'], 'UTC');
                } catch (\Throwable) {
                    $published = null;
                }
            } elseif (! empty($post['date'])) {
                try {
                    $published = Carbon::parse((string) $post['date'], 'Asia/Karachi');
                } catch (\Throwable) {
                    $published = null;
                }
            }

            $html = (string) data_get($post, 'content.rendered', '');
            if ($html === '') {
                $html = (string) data_get($post, 'excerpt.rendered', '');
            }
            $content = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
            $link = (string) ($post['link'] ?? ($this->feedUrls[0] ?? ''));

            foreach ($this->pricesFromContent($content, $published, $link) as $row) {
                $product = (string) ($row['product'] ?? '');
                if ($product !== '' && ! isset($byProduct[$product])) {
                    $byProduct[$product] = $row;
                }
            }

            if (isset($byProduct['petrol'], $byProduct['hsd'], $byProduct['kerosene'])) {
                break;
            }
        }

        if (isset($byProduct['petrol'], $byProduct['hsd'])) {
            return array_values($byProduct);
        }

        return [];
    }

    public function history(string $product, ?string $source = null, int $days = 30): array
    {
        return [];
    }

    public function health(): array
    {
        try {
            $rows = $this->latestPrices();

            return [
                'healthy' => $rows !== [],
                'last_successful_run' => Carbon::now()->toIso8601String(),
                'ok_sources' => $rows !== [] ? ['propakistani'] : [],
                'failed_sources' => $rows !== [] ? [] : ['propakistani'],
                'raw' => ['count' => count($rows), 'feeds' => $this->feedUrls, 'pages' => self::FEED_PAGES],
            ];
        } catch (\Throwable) {
            return [
                'healthy' => false,
                'last_successful_run' => null,
                'ok_sources' => [],
                'failed_sources' => ['propakistani'],
            ];
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $byProduct
     */
    private function mergeFeedInto(array &$byProduct, string $xml, bool $fetchArticles, string $fallbackLink): void
    {
        if ($xml === '' || ! preg_match_all('/<item>([\s\S]*?)<\/item>/i', $xml, $items)) {
            return;
        }

        foreach ($items[1] as $item) {
            $title = preg_match('/<title>([^<]+)<\/title>/i', $item, $tm)
                ? html_entity_decode(trim($tm[1]), ENT_QUOTES | ENT_HTML5)
                : '';

            if ($title !== '' && ! $this->looksLikePriceArticle($title)) {
                continue;
            }

            $published = $this->parsePubDate($item);
            $link = preg_match('/<link>([^<]+)<\/link>/i', $item, $lm)
                ? trim($lm[1])
                : $fallbackLink;
            $content = html_entity_decode($item, ENT_QUOTES | ENT_HTML5);

            $this->mergeRows($byProduct, $this->pricesFromContent($content, $published, $link));

            $needsEnrichment = ! isset($byProduct['petrol'], $byProduct['hsd'])
                || ! isset($byProduct['kerosene']);
            if ($fetchArticles && $link !== $fallbackLink && $needsEnrichment) {
                $html = $this->fetchArticle($link);
                if ($html !== '') {
                    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
                    $this->mergeRows($byProduct, $this->pricesFromContent($text, $published, $link));
                }
            }

            if (isset($byProduct['petrol'], $byProduct['hsd'], $byProduct['kerosene'])) {
                return;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $byProduct
     * @param  list<array<string, mixed>>  $rows
     */
    private function mergeRows(array &$byProduct, array $rows): void
    {
        foreach ($rows as $row) {
            $product = (string) ($row['product'] ?? '');
            if ($product !== '' && ! isset($byProduct[$product])) {
                $byProduct[$product] = $row;
            }
        }
    }

    private function fetchFeed(string $feedUrl, int $page = 1): string
    {
        $url = $page <= 1
            ? $feedUrl
            : rtrim($feedUrl, '/').'/?paged='.$page;

        try {
            return Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/rss+xml, text/xml, */*',
                ])
                ->get($url)
                ->throw()
                ->body();
        } catch (\Throwable) {
            return '';
        }
    }

    private function fetchArticle(string $url): string
    {
        try {
            return Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url)
                ->throw()
                ->body();
        } catch (\Throwable) {
            return '';
        }
    }

    private function looksLikePriceArticle(string $title): bool
    {
        if (! preg_match('/petrol|diesel|fuel|hsd|petroleum|kerosene|sko/i', $title)) {
            return false;
        }

        // Prefer announcement headlines; still allow broad fuel titles through
        // so RSS-body matches can win without an HTML fetch.
        return (bool) preg_match(
            '/price|rs\.?|increase|decrease|reduced|raised|hike|cut|litre|liter|revised|announc|jumps?/i',
            $title,
        );
    }

    private function parsePubDate(string $item): ?Carbon
    {
        if (! preg_match('/<pubDate>([^<]+)<\/pubDate>/i', $item, $m)) {
            return null;
        }

        try {
            return Carbon::parse(trim($m[1]));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pricesFromContent(string $content, ?Carbon $published, string $link): array
    {
        if ($published === null || $published->diffInHours(now()) > self::MAX_AGE_HOURS) {
            return [];
        }

        // Announcements apply on the same day they are published.
        $effective = $published->copy()->timezone('Asia/Karachi')->toDateString();

        $otherProduct = 'petrol|diesel|hsd|kerosene|sko';
        $petrol = null;
        $hsd = null;
        $kerosene = null;

        // Do not allow the window to cross another product name — otherwise
        // "petrol and high-speed diesel were increased… petrol to Rs X"
        // makes diesel steal X (both became 320.73 on 22 Jul 2026).
        if (preg_match(
            '/\bpetrol\b(?:(?!\b(?:'.$otherProduct.')\b).){0,120}?to\s+(?:sell\s+for\s+)?Rs\.?\s*([0-9]{2,4}\.[0-9]{1,2})\s*(?:per\s+litre|\/\s*litre|per\s+liter)/iu',
            $content,
            $pm,
        ) && $this->plausible((float) $pm[1])) {
            $petrol = (float) $pm[1];
        }

        if (preg_match(
            '/\b(?:diesel|hsd)\b(?:(?!\b(?:'.$otherProduct.')\b).){0,120}?to\s+(?:sell\s+for\s+)?Rs\.?\s*([0-9]{2,4}\.[0-9]{1,2})\s*(?:per\s+litre|\/\s*litre|per\s+liter)/iu',
            $content,
            $dm,
        ) && $this->plausible((float) $dm[1])) {
            $hsd = (float) $dm[1];
        }

        if (preg_match(
            '/\b(?:kerosene(?:\s+oil)?|sko)\b(?:(?!\b(?:'.$otherProduct.')\b).){0,160}?(?:to\s+(?:sell\s+for\s+)?|at\s+|reaching\s+|rate\s+at\s+)Rs\.?\s*([0-9]{2,4}\.[0-9]{1,2})\s*(?:per\s+litre|\/\s*litre|per\s+liter)?/iu',
            $content,
            $km,
        ) && $this->plausible((float) $km[1])) {
            $kerosene = (float) $km[1];
        }

        $rows = [];

        if ($petrol !== null && $hsd !== null) {
            // Identical petrol/HSD is almost always a cross-product parse error.
            if ($petrol === $hsd) {
                return [];
            }
            $rows[] = $this->priceRow('petrol', $petrol, $effective, $link);
            $rows[] = $this->priceRow('hsd', $hsd, $effective, $link);
            if ($kerosene !== null) {
                $rows[] = $this->priceRow('kerosene', $kerosene, $effective, $link);
            }

            return $rows;
        }

        // SKO-only enrichment from a wire that quotes kerosene without a full table.
        if ($kerosene !== null && $petrol === null && $hsd === null) {
            return [$this->priceRow('kerosene', $kerosene, $effective, $link)];
        }

        return [];
    }

    private function plausible(float $price): bool
    {
        return $price >= 100 && $price <= 2000;
    }

    /**
     * @return array<string, mixed>
     */
    private function priceRow(string $product, float $price, string $effective, string $sourceUrl): array
    {
        return [
            'source' => 'pso',
            'provider' => $this->name(),
            'product' => $product,
            'price_pkr' => $price,
            'unit' => 'litre',
            'city' => null,
            'effective_date' => $effective,
            'scraped_at' => Carbon::now()->toIso8601String(),
            'source_url' => $sourceUrl,
        ];
    }
}

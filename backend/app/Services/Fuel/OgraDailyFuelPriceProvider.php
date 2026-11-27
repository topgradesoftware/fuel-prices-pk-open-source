<?php

namespace App\Services\Fuel;

use App\Contracts\FuelPriceProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * OGRA daily price publication (PDF) — the authoritative source since Pakistan
 * moved to daily fuel pricing (July 2026). OGRA notifies petrol/HSD/SKO/LDO
 * ex-depot prices every working day, hours before OMC websites and aggregators
 * update.
 *
 * The notified price is uniform nationwide, so rows are emitted under the
 * headline company (PSO) which the app shows by default.
 */
class OgraDailyFuelPriceProvider implements FuelPriceProvider
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; OilPriceCalculator/1.0; +https://oilprice.topgradesoftware.com)';

    /**
     * Product slug => PDF "Total … Price" label aliases (first match wins).
     *
     * @var array<string, list<string>>
     */
    private const PRODUCT_LABELS = [
        'petrol' => ['Petrol'],
        'hsd' => ['Diesel'],
        'kerosene' => ['Kerosene', 'Superior Kerosene', 'SKO'],
        'ldo' => ['Light Diesel Oil', 'Light Diesel', 'LDO'],
    ];

    public function __construct(
        private readonly string $listUrl = 'https://ogra.org.pk/price-publications',
    ) {}

    public function name(): string
    {
        return 'ogra_daily';
    }

    public function latestPrices(): array
    {
        $publication = $this->latestPublication();
        if ($publication === null) {
            return [];
        }

        [$pdfUrl, $labelDate] = $publication;

        $pdf = Http::timeout(40)
            ->withHeaders(['User-Agent' => self::USER_AGENT])
            ->get($pdfUrl)
            ->throw()
            ->body();

        $text = (new PdfParser)->parseContent($pdf)->getText();

        return $this->pricesFromText($text, $labelDate, $pdfUrl);
    }

    /**
     * Parse the already-extracted PDF text. Public so fixture tests can cover
     * the regexes without downloading a real publication.
     *
     * @return list<array<string, mixed>>
     */
    public function pricesFromText(string $text, ?string $labelDate, string $pdfUrl): array
    {
        $effective = $this->extractEffectiveDate($text) ?? $labelDate ?? now()->toDateString();

        $rows = [];
        foreach (self::PRODUCT_LABELS as $product => $labels) {
            $price = $this->extractTotalForLabels($text, $labels);
            if ($price !== null) {
                $rows[] = $this->priceRow($product, $price, $effective, $pdfUrl);
            }
        }

        return $rows;
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
                'ok_sources' => $rows !== [] ? ['ogra'] : [],
                'failed_sources' => $rows !== [] ? [] : ['ogra'],
                'raw' => ['count' => count($rows), 'page' => $this->listUrl],
            ];
        } catch (\Throwable) {
            return [
                'healthy' => false,
                'last_successful_run' => null,
                'ok_sources' => [],
                'failed_sources' => ['ogra'],
            ];
        }
    }

    /**
     * Find the newest "Price Publication Effective Dated …" entry on the list page.
     *
     * @return array{0: string, 1: ?string}|null [pdfUrl, effectiveDate Y-m-d]
     */
    private function latestPublication(): ?array
    {
        $html = Http::timeout(30)
            ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html'])
            ->get($this->listUrl)
            ->throw()
            ->body();

        if (! preg_match_all(
            '/<p>\s*(Price\s+Publication[^<]*?Dated\s+([^<]+?))\s*<\/p>[\s\S]{0,600}?href="([^"]*\/download\/\d+)"/iu',
            $html,
            $matches,
            PREG_SET_ORDER,
        )) {
            return null;
        }

        $best = null;
        $bestDate = null;
        foreach ($matches as $match) {
            $date = $this->parseDate(trim($match[2]));
            if ($bestDate === null || ($date !== null && $date > $bestDate)) {
                $bestDate = $date ?? $bestDate;
                $best = [html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5), $date];
            }
        }

        return $best;
    }

    private function extractEffectiveDate(string $text): ?string
    {
        if (preg_match('/Price\s+Effective\s+(\d{1,2}\s+[A-Za-z]+\s+\d{4})/iu', $text, $m)) {
            return $this->parseDate($m[1]);
        }

        return null;
    }

    /**
     * @param  list<string>  $labels
     */
    private function extractTotalForLabels(string $text, array $labels): ?float
    {
        foreach ($labels as $label) {
            $price = $this->extractTotal($text, $label);
            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * Pull the "Total Petrol Price … 315.80" figure. Anchors on the formula
     * cell first; falls back to the last plausible number in the block.
     */
    private function extractTotal(string $text, string $product): ?float
    {
        $productPattern = preg_replace('/\s+/', '\s+', preg_quote($product, '/'));

        if (preg_match(
            '/Total\s+'.$productPattern.'\s+Price[\s\S]{0,200}?T=\{[^}]*\}\s*([0-9]{2,4}\.[0-9]{1,2})/iu',
            $text,
            $m,
        )) {
            return $this->plausible((float) $m[1]);
        }

        if (preg_match('/Total\s+'.$productPattern.'\s+Price([\s\S]{0,220})/iu', $text, $m)
            && preg_match_all('/([0-9]{2,4}\.[0-9]{1,2})/', $m[1], $nums)) {
            foreach (array_reverse($nums[1]) as $candidate) {
                $value = $this->plausible((float) $candidate);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function plausible(float $price): ?float
    {
        return ($price >= 100 && $price <= 2000) ? $price : null;
    }

    private function parseDate(string $raw): ?string
    {
        try {
            return Carbon::parse(trim($raw))->toDateString();
        } catch (\Throwable) {
            return null;
        }
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

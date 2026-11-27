<?php

namespace App\Services\Fuel;

use App\Contracts\FuelPriceProvider;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Official PSO retail price page scraper (HTML tables).
 *
 * Under daily OGRA pricing (July 2026+), petrol (Premier) and HSD on this page
 * often lag the regulator by hours/days and are intentionally omitted — OGRA /
 * news providers own them. SKO/LDO are still emitted as a last-resort fallback
 * because current OGRA daily PDFs only publish petrol/HSD; Composite + sync
 * still prefer ogra_daily / propakistani whenever those supply SKO/LDO.
 * Octane Euro 5, JP-1, LPG and similar PSO-only rows also come from here.
 */
class PsoOfficialFuelPriceProvider implements FuelPriceProvider
{
    public function __construct(
        private readonly string $pageUrl = 'https://psopk.com/en/fuels/fuel-prices',
    ) {}

    public function name(): string
    {
        return 'pso_official';
    }

    public function latestPrices(): array
    {
        $response = Http::timeout(25)
            ->withHeaders([
                'User-Agent' => 'OilPriceCalculator/1.0 (+https://oilprice.topgradesoftware.com)',
                'Accept' => 'text/html',
            ])
            ->get($this->pageUrl)
            ->throw();

        return $this->parseHtml((string) $response->body());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function parseHtml(string $html): array
    {
        $rows = [];
        $today = now()->toDateString();

        if (preg_match_all(
            '/<tr[^>]*>\s*<td[^>]*>\s*([^<]+?)\s*<\/td>\s*<td[^>]*>\s*([^<]+?)\s*<\/td>\s*<\/tr>/iu',
            $html,
            $matches,
            PREG_SET_ORDER,
        )) {
            foreach ($matches as $match) {
                $label = html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);
                $rawPrice = html_entity_decode(trim($match[2]), ENT_QUOTES | ENT_HTML5);
                $mapped = $this->mapRow($label, $rawPrice, $today);
                if ($mapped !== null) {
                    $rows[] = $mapped;
                }
            }
        }

        // National LPG sometimes appears outside the simple 2-column product table.
        if (! $this->hasProduct($rows, 'lpg')) {
            if (preg_match('/LPG[^<]{0,40}Rs\.?\s*([0-9]+(?:\.[0-9]+)?)\s*\/?\s*KG/iu', $html, $m)
                || preg_match('/Rs\.?\s*([0-9]+(?:\.[0-9]+)?)\s*\/?\s*KG[^<]{0,40}LPG/iu', $html, $m)) {
                $rows[] = $this->priceRow('lpg', (float) $m[1], 'kg', null, $today);
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
                'healthy' => count($rows) > 0,
                'last_successful_run' => Carbon::now()->toIso8601String(),
                'ok_sources' => count($rows) > 0 ? ['pso'] : [],
                'failed_sources' => count($rows) > 0 ? [] : ['pso'],
                'raw' => ['count' => count($rows), 'page' => $this->pageUrl],
            ];
        } catch (RequestException) {
            return [
                'healthy' => false,
                'last_successful_run' => null,
                'ok_sources' => [],
                'failed_sources' => ['pso'],
            ];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapRow(string $label, string $rawPrice, string $today): ?array
    {
        if ($rawPrice === '' || $rawPrice === '--' || str_contains($rawPrice, '--')) {
            return null;
        }

        if (! preg_match('/([0-9]+(?:\.[0-9]+)?)/', str_replace(',', '', $rawPrice), $pm)) {
            return null;
        }

        $price = (float) $pm[1];
        if ($price <= 0 || $price > 10000) {
            return null;
        }

        $normalized = Str::lower(trim($label));
        $unit = str_contains(Str::lower($rawPrice), 'kg') ? 'kg' : 'litre';

        if (str_contains($normalized, 'octane euro')) {
            $city = null;
            if (preg_match('/\(([^)]+)\)/', $label, $cm)) {
                $city = trim($cm[1]);
            }

            return $this->priceRow('octane_plus', $price, $unit, $city, $today);
        }

        // Regulated daily products — never emit from this lagging page.
        if (str_contains($normalized, 'premier')) {
            return null;
        }

        if (str_contains($normalized, 'hi-cetane') || (str_contains($normalized, 'diesel') && str_contains($normalized, 'euro'))) {
            return null;
        }

        if ($normalized === 'ldo' || str_contains($normalized, 'light diesel')) {
            return $this->priceRow('ldo', $price, $unit, null, $today);
        }

        if ($normalized === 'sko' || str_contains($normalized, 'kerosene')) {
            return $this->priceRow('kerosene', $price, $unit, null, $today);
        }

        if (str_contains($normalized, 'jp-1') || $normalized === 'jp1' || str_contains($normalized, 'jp 1')) {
            return $this->priceRow('jp1', $price, $unit, null, $today);
        }

        if (preg_match('/^lpg\b/i', $normalized) || $normalized === 'lpg') {
            return $this->priceRow('lpg', $price, str_contains(Str::lower($rawPrice), 'kg') ? 'kg' : $unit, null, $today);
        }

        if (str_contains($normalized, 'e10') || str_contains($normalized, 'e-10')) {
            return $this->priceRow('e10', $price, $unit, null, $today);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function priceRow(string $product, float $price, string $unit, ?string $city, string $today): array
    {
        return [
            'source' => 'pso',
            'provider' => $this->name(),
            'product' => $product,
            'price_pkr' => $price,
            'unit' => $unit,
            'city' => $city,
            'effective_date' => $today,
            'scraped_at' => Carbon::now()->toIso8601String(),
            'source_url' => $this->pageUrl,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function hasProduct(array $rows, string $product): bool
    {
        foreach ($rows as $row) {
            if (($row['product'] ?? '') === $product) {
                return true;
            }
        }

        return false;
    }
}

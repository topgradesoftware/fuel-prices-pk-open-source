<?php

namespace App\Services\Fuel;

use App\Contracts\FuelPriceProvider;
use App\Support\SafeError;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Merges multiple upstream providers, preferring official OMC pages over aggregators.
 */
class CompositeFuelPriceProvider implements FuelPriceProvider
{
    /** Higher = preferred when company+product+city collide. */
    private const PROVIDER_PRIORITY = [
        'ogra_daily' => 200,
        'propakistani' => 150,
        'pso_official' => 100,
        'trackmate' => 50,
    ];

    /**
     * @param  list<FuelPriceProvider>  $providers
     */
    public function __construct(
        private readonly array $providers,
    ) {}

    public function name(): string
    {
        return 'composite';
    }

    public function latestPrices(): array
    {
        $merged = [];
        $disagreements = [];
        /** @var array<string, true> company|product keys owned by OGRA/news */
        $authorityOwned = [];

        foreach ($this->providers as $provider) {
            try {
                $rows = $provider->latestPrices();
            } catch (\Throwable $e) {
                SafeError::log($e, 'Fuel provider failed', [
                    'provider' => $provider->name(),
                ], 'warning');

                continue;
            }

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $row['provider'] = $row['provider'] ?? $provider->name();
                $providerName = (string) $row['provider'];
                $product = Str::slug((string) ($row['product'] ?? ''));
                $key = $this->dedupeKey($row);
                $authorityKey = Str::slug((string) ($row['source'] ?? 'unknown')).'|'.$product;

                // Lagging OMC/aggregator regulated fuels must not compete once an
                // authority source already supplied that company+product.
                $authorityProducts = ['petrol', 'hsd', 'kerosene', 'ldo'];
                if (
                    in_array($product, $authorityProducts, true)
                    && isset($authorityOwned[$authorityKey])
                    && ! in_array($providerName, ['ogra_daily', 'propakistani'], true)
                ) {
                    continue;
                }

                if (in_array($providerName, ['ogra_daily', 'propakistani'], true)
                    && in_array($product, $authorityProducts, true)) {
                    $authorityOwned[$authorityKey] = true;
                }

                $incomingPriority = self::PROVIDER_PRIORITY[$providerName] ?? 10;

                if (! isset($merged[$key])) {
                    $merged[$key] = $row;

                    continue;
                }

                $existing = $merged[$key];
                $existingPrice = (float) ($existing['price_pkr'] ?? 0);
                $incomingPrice = (float) ($row['price_pkr'] ?? 0);
                if ($existingPrice > 0 && $incomingPrice > 0) {
                    $diffPct = abs($incomingPrice - $existingPrice) / $existingPrice * 100;
                    if ($diffPct > 2.0) {
                        $disagreements[] = [
                            'key' => $key,
                            'a' => ($existing['provider'] ?? '?').' Rs '.number_format($existingPrice, 2),
                            'b' => ($row['provider'] ?? '?').' Rs '.number_format($incomingPrice, 2),
                            'diff_pct' => round($diffPct, 1),
                        ];
                    }
                }

                $existingPriority = self::PROVIDER_PRIORITY[$existing['provider'] ?? ''] ?? 10;
                if ($incomingPriority > $existingPriority) {
                    $merged[$key] = $row;
                }
            }
        }

        if ($disagreements !== []) {
            Log::warning('Fuel source disagreement', ['rows' => $disagreements]);
        }
        // Watchdog picks this up and emails the owner (12h TTL covers its cadence).
        Cache::put('fuel:source_disagreements', $disagreements, now()->addHours(12));

        return array_values($merged);
    }

    public function history(string $product, ?string $source = null, int $days = 30): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->name() === 'trackmate') {
                return $provider->history($product, $source, $days);
            }
        }

        foreach ($this->providers as $provider) {
            $rows = $provider->history($product, $source, $days);
            if ($rows !== []) {
                return $rows;
            }
        }

        return [];
    }

    public function health(): array
    {
        $ok = [];
        $failed = [];
        $healthy = false;
        $last = null;

        foreach ($this->providers as $provider) {
            try {
                $h = $provider->health();
            } catch (\Throwable) {
                $failed[] = $provider->name();

                continue;
            }

            if ($h['healthy'] ?? false) {
                $healthy = true;
                $ok[] = $provider->name();
                $last = $h['last_successful_run'] ?? $last;
            } else {
                $failed[] = $provider->name();
            }
        }

        return [
            'healthy' => $healthy,
            'last_successful_run' => $last,
            'ok_sources' => $ok,
            'failed_sources' => $failed,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function dedupeKey(array $row): string
    {
        $company = Str::slug((string) ($row['source'] ?? 'unknown'));
        $product = Str::slug((string) ($row['product'] ?? 'unknown'));
        $city = Str::slug((string) ($row['city'] ?? 'national'));

        return $company.'|'.$product.'|'.$city;
    }
}

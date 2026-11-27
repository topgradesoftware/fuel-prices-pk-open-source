<?php

namespace App\Services\Fuel;

use App\Contracts\FuelPriceProvider;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TrackMateFuelPriceProvider implements FuelPriceProvider
{
    public function __construct(
        private readonly string $baseUrl = 'https://fuel.trackmate.page/api',
    ) {}

    public function name(): string
    {
        return 'trackmate';
    }

    public function latestPrices(): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/prices')
            ->throw();

        $body = $response->json();
        $prices = $body['prices'] ?? $body['data'] ?? $body;

        if (! is_array($prices)) {
            return [];
        }

        /** @var list<array<string, mixed>> $prices */
        $prices = array_values(array_filter($prices, 'is_array'));

        return array_map(function (array $row): array {
            $row['provider'] = $this->name();
            $row['source_url'] = rtrim($this->baseUrl, '/').'/prices';

            return $row;
        }, $prices);
    }

    public function history(string $product, ?string $source = null, int $days = 30): array
    {
        $days = min(max($days, 1), 365);

        $response = Http::timeout(30)
            ->acceptJson()
            ->get(rtrim($this->baseUrl, '/').'/history', array_filter([
                'product' => $product,
                'source' => $source,
                'days' => $days,
            ], fn ($v) => $v !== null && $v !== ''))
            ->throw();

        $body = $response->json();
        $rows = $body['prices'] ?? $body['history'] ?? $body['data'] ?? $body;

        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $price = $row['price_pkr'] ?? $row['price'] ?? null;
            $when = $row['scraped_at'] ?? $row['effective_date'] ?? $row['date'] ?? null;
            if ($price === null || $when === null) {
                continue;
            }

            try {
                $date = Carbon::parse((string) $when)->toDateString();
            } catch (\Throwable) {
                continue;
            }

            $out[] = [
                'date' => $date,
                'price' => (float) $price,
                'source' => (string) ($row['source'] ?? $source ?? 'unknown'),
                'product' => (string) ($row['product'] ?? $product),
                'city' => $row['city'] ?? null,
                'unit' => $row['unit'] ?? null,
            ];
        }

        return $out;
    }

    public function health(): array
    {
        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->get(rtrim($this->baseUrl, '/').'/health')
                ->throw();

            $body = $response->json() ?? [];
            $last = $body['last_scrape'] ?? [];

            return [
                'healthy' => ($body['status'] ?? '') === 'ok',
                'last_successful_run' => $last['ran_at'] ?? $body['last_successful_run'] ?? null,
                'ok_sources' => array_values($last['ok_sources'] ?? $body['ok_sources'] ?? []),
                'failed_sources' => array_values($last['failed_sources'] ?? $body['failed_sources'] ?? []),
                'raw' => $body,
            ];
        } catch (RequestException) {
            return [
                'healthy' => false,
                'last_successful_run' => null,
                'ok_sources' => [],
                'failed_sources' => [],
            ];
        }
    }
}

<?php

namespace App\Contracts;

/**
 * Spec §4.2 — provider-agnostic fuel data contract.
 *
 * @phpstan-type RawPrice array{
 *     source: string,
 *     product: string,
 *     price_pkr: float|int|string,
 *     unit?: string|null,
 *     city?: string|null,
 *     effective_date?: string|null,
 *     scraped_at?: string|null
 * }
 */
interface FuelPriceProvider
{
    /**
     * @return list<RawPrice>
     */
    public function latestPrices(): array;

    /**
     * @return list<array{date: string, price: float}>
     */
    public function history(string $product, ?string $source = null, int $days = 30): array;

    /**
     * @return array{healthy: bool, last_successful_run: ?string, ok_sources: list<string>, failed_sources: list<string>, raw?: array<string, mixed>}
     */
    public function health(): array;

    public function name(): string;
}

<?php

namespace App\Services\Fuel;

use App\Models\FuelPrice;
use App\Models\FuelPriceHistory;
use App\Models\FuelType;
use Illuminate\Support\Collection;

/**
 * Builds a single national price series for charts / 7-day trends.
 *
 * Pulls all national OMC rows for a product, then picks one price per day via
 * source rank (never averages OMCs). Averaging previously turned a bad diesel
 * row (320.73) + correct (367.21) into 343.97. PSO-only was too sparse for HSD
 * when PSO only had today's OGRA row and older history lived on other OMCs.
 */
class FuelHistorySeries
{
    /** @var array<string, int> */
    private const SOURCE_RANK = [
        'manual' => 300,
        'ogra_daily' => 200,
        'propakistani' => 150,
        'pso_official' => 100,
        'trackmate' => 50,
        'composite' => 10,
    ];

    /**
     * @return list<array{date: string, price: float}>
     */
    public function points(string $productSlug, int $days = 365, ?string $companySlug = null): array
    {
        $fuelType = FuelType::query()->where('slug', $productSlug)->first();
        if ($fuelType === null) {
            return [];
        }

        $priceIds = $this->priceIds((int) $fuelType->id, $companySlug);
        if ($priceIds->isEmpty()) {
            return [];
        }

        $since = now()->subDays(max($days, 1))->startOfDay();

        $points = FuelPriceHistory::query()
            ->with('fuelPrice:id,source')
            ->whereIn('fuel_price_id', $priceIds)
            ->where('effective_at', '>=', $since)
            ->orderBy('effective_at')
            ->get()
            ->groupBy(fn (FuelPriceHistory $h) => $h->effective_at->toDateString())
            ->map(fn (Collection $group, string $date) => [
                'date' => $date,
                'price' => $this->bestPrice($group),
            ])
            ->values()
            ->all();

        if ($points !== []) {
            return $points;
        }

        $current = FuelPrice::query()
            ->whereIn('id', $priceIds)
            ->get();
        if ($current->isEmpty()) {
            return [];
        }

        $best = $current->sortByDesc(
            fn (FuelPrice $p) => self::SOURCE_RANK[(string) $p->source] ?? 0,
        )->first();

        $avg = round((float) $best->price, 2);
        $prev = round((float) ($best->previous_price ?: $avg), 2);

        return [
            ['date' => now()->subDays(14)->toDateString(), 'price' => $prev],
            ['date' => now()->toDateString(), 'price' => $avg],
        ];
    }

    /**
     * @return Collection<int, int|string>
     */
    private function priceIds(int $fuelTypeId, ?string $companySlug): Collection
    {
        $query = FuelPrice::query()
            ->where('fuel_type_id', $fuelTypeId)
            ->whereNull('city_id');

        if ($companySlug !== null && $companySlug !== '') {
            return $query
                ->whereHas('company', fn ($c) => $c->where('slug', $companySlug))
                ->pluck('id');
        }

        // All national rows — bestPrice() picks the authoritative point per day.
        return $query->pluck('id');
    }

    /**
     * @param  Collection<int, FuelPriceHistory>  $group
     */
    private function bestPrice(Collection $group): float
    {
        // Authoritative source first; within that, newest row wins so a
        // same-day correction (bad 320.73 → real 367.21) shows the fix.
        $best = $group->sort(function (FuelPriceHistory $a, FuelPriceHistory $b) {
            $rankA = self::SOURCE_RANK[(string) ($a->fuelPrice?->source ?? '')] ?? 0;
            $rankB = self::SOURCE_RANK[(string) ($b->fuelPrice?->source ?? '')] ?? 0;
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            return ($b->id ?? 0) <=> ($a->id ?? 0);
        })->first();

        return round((float) ($best?->new_price ?? 0), 2);
    }
}

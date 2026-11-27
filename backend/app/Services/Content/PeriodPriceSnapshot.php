<?php

namespace App\Services\Content;

use App\Models\FuelPrice;
use App\Models\FuelType;
use Illuminate\Support\Collection;

/**
 * Collects current vs previous national prices for weekly/monthly AI briefs.
 */
class PeriodPriceSnapshot
{
    /**
     * @return array{moves: list<array<string, mixed>>, biggest_increase: ?array<string, mixed>, biggest_decrease: ?array<string, mixed>}
     */
    public function collect(array $fuels = ['petrol', 'hsd', 'lpg', 'hobc', 'kerosene']): array
    {
        $moves = [];
        foreach ($fuels as $slug) {
            $typeId = FuelType::query()->where('slug', $slug)->value('id');
            if ($typeId === null) {
                continue;
            }

            $current = FuelPrice::query()
                ->where('fuel_type_id', $typeId)
                ->where('price', '>', 0)
                ->whereNull('city_id')
                ->whereHas('company', fn ($q) => $q->where('slug', 'pso'))
                ->orderByDesc('effective_date')
                ->first()
                ?? FuelPrice::query()
                    ->where('fuel_type_id', $typeId)
                    ->where('price', '>', 0)
                    ->whereNull('city_id')
                    ->orderByDesc('effective_date')
                    ->first();

            if ($current === null || $current->previous_price === null) {
                continue;
            }

            $price = (float) $current->price;
            $prev = (float) $current->previous_price;
            $moves[] = [
                'fuel' => $slug,
                'price' => $price,
                'previous' => $prev,
                'delta' => round($price - $prev, 2),
                'effective_date' => optional($current->effective_date)?->toDateString(),
            ];
        }

        /** @var Collection<int, array<string, mixed>> $collection */
        $collection = collect($moves);
        $biggestIncrease = $collection->sortByDesc('delta')->first();
        $biggestDecrease = $collection->sortBy('delta')->first();

        return [
            'moves' => $moves,
            'biggest_increase' => $biggestIncrease && ($biggestIncrease['delta'] ?? 0) > 0 ? $biggestIncrease : null,
            'biggest_decrease' => $biggestDecrease && ($biggestDecrease['delta'] ?? 0) < 0 ? $biggestDecrease : null,
        ];
    }
}

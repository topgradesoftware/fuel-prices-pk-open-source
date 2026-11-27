<?php

namespace App\Services\Fuel;

use App\Models\FuelPrice;
use App\Models\FuelPriceHistory;
use App\Models\FuelType;
use App\Repositories\PriceRepository;
use App\Support\CountryScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps daily national prices trustworthy after every sync.
 *
 * Stops recurring bugs:
 * - composite / seed junk rows resurfacing in the home cards
 * - previous_price jumping Rs 30+ because history skipped intermediate revisions
 * - superseded older OMC rows confusing "latest" selection
 */
class FuelPriceReconciler
{
    /** Fortnight OGRA moves rarely exceed this; larger deltas are treated as contaminated. */
    public const MAX_SANE_DELTA = 25.0;

    /** @var list<string> */
    private const PRIMARY_FUELS = ['petrol', 'hsd', 'lpg', 'kerosene', 'ldo'];

    public function __construct(
        private readonly PriceRepository $prices,
    ) {}

    /**
     * @return array{composite_deleted: int, stale_trackmate_specialty_deleted: int, history_pruned: int, previous_fixed: int, superseded_deleted: int}
     */
    public function reconcile(): array
    {
        $compositeDeleted = $this->deleteJunkSources();
        $staleTrackmateSpecialty = $this->deleteStaleTrackmateSpecialty();
        $historyPruned = $this->pruneImplausibleHistory();
        $supersededDeleted = $this->deleteSupersededRows();
        $previousFixed = $this->repairPreviousPrices();

        $this->prices->forgetLatestCache();

        $summary = [
            'composite_deleted' => $compositeDeleted,
            'stale_trackmate_specialty_deleted' => $staleTrackmateSpecialty,
            'history_pruned' => $historyPruned,
            'previous_fixed' => $previousFixed,
            'superseded_deleted' => $supersededDeleted,
        ];

        Log::info('fuel:reconcile', $summary);

        return $summary;
    }

    private function deleteJunkSources(): int
    {
        return (int) FuelPrice::query()
            ->whereIn('source', ['composite', 'seed'])
            ->delete();
    }

    /**
     * Drop TrackMate SKO/LDO placeholders once an official PSO/OGRA (or news)
     * national rate exists — aggregators lag notified ex-depot specialty prices.
     */
    private function deleteStaleTrackmateSpecialty(): int
    {
        $typeIds = FuelType::query()
            ->whereIn('slug', ['kerosene', 'ldo', 'lsd'])
            ->pluck('id');

        if ($typeIds->isEmpty()) {
            return 0;
        }

        $hasOfficial = FuelPrice::query()
            ->whereIn('fuel_type_id', $typeIds)
            ->whereIn('source', ['ogra_daily', 'pso_official', 'propakistani'])
            ->exists();

        if (! $hasOfficial) {
            return 0;
        }

        return (int) FuelPrice::query()
            ->where('source', 'trackmate')
            ->whereIn('fuel_type_id', $typeIds)
            ->delete();
    }

    private function pruneImplausibleHistory(): int
    {
        return (int) FuelPriceHistory::query()
            ->where(function ($q) {
                $q->where('change_amount', '>', self::MAX_SANE_DELTA)
                    ->orWhere('change_amount', '<', -1 * self::MAX_SANE_DELTA);
            })
            ->delete();
    }

    private function deleteSupersededRows(): int
    {
        // MySQL-safe: collect ids then delete.
        $ids = DB::table('fuel_prices as p')
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('fuel_prices as n')
                    ->whereColumn('n.company_id', 'p.company_id')
                    ->whereColumn('n.fuel_type_id', 'p.fuel_type_id')
                    ->whereRaw('IFNULL(n.city_id, 0) = IFNULL(p.city_id, 0)')
                    ->whereColumn('n.effective_date', '>', 'p.effective_date');
            })
            ->pluck('p.id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return (int) FuelPrice::query()->whereIn('id', $ids)->delete();
    }

    private function repairPreviousPrices(): int
    {
        $fixed = 0;
        $pakistanId = CountryScope::pakistan()->id;

        $rows = FuelPrice::query()
            ->with('fuelType:id,slug')
            ->where(function ($q) use ($pakistanId) {
                $q->where('country_id', $pakistanId)->orWhereNull('country_id');
            })
            ->whereNull('city_id')
            ->whereHas('fuelType', fn ($q) => $q->whereIn('slug', self::PRIMARY_FUELS))
            ->get();

        foreach ($rows as $row) {
            $sane = $this->resolvePrevious($row);
            $currentPrev = $row->previous_price !== null ? (float) $row->previous_price : null;

            if ($sane === $currentPrev) {
                continue;
            }
            if ($sane !== null && $currentPrev !== null && abs($sane - $currentPrev) < 0.009) {
                continue;
            }

            $row->previous_price = $sane;
            $row->save();
            $fixed++;
        }

        return $fixed;
    }

    public function resolvePrevious(FuelPrice $row): ?float
    {
        $price = (float) $row->price;
        $stored = $row->previous_price !== null ? (float) $row->previous_price : null;
        if ($stored !== null && $stored > 0 && $this->isSaneDelta($price, $stored)) {
            return round($stored, 2);
        }

        $fromHistory = $this->previousFromHistory((int) $row->id, $price);
        if ($fromHistory !== null) {
            return $fromHistory;
        }

        $fromPeers = $this->previousFromPeerNationals($row, $price);
        if ($fromPeers !== null) {
            return $fromPeers;
        }

        // Contaminated stored value — prefer null (UI shows "no prior") over a fake spike.
        return null;
    }

    private function previousFromHistory(int $fuelPriceId, float $currentPrice): ?float
    {
        $rows = FuelPriceHistory::query()
            ->where('fuel_price_id', $fuelPriceId)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get(['previous_price', 'new_price', 'change_amount', 'effective_at']);

        foreach ($rows as $h) {
            $change = $h->change_amount !== null ? abs((float) $h->change_amount) : null;
            if ($change !== null && $change > self::MAX_SANE_DELTA) {
                continue;
            }

            $prev = $h->previous_price !== null ? (float) $h->previous_price : null;
            if ($prev !== null && $prev > 0 && $this->isSaneDelta($currentPrice, $prev)) {
                return round($prev, 2);
            }

            $new = (float) $h->new_price;
            if ($new > 0 && abs($new - $currentPrice) > 0.009 && $this->isSaneDelta($currentPrice, $new)) {
                // A prior day's settled price.
                return round($new, 2);
            }
        }

        return null;
    }

    private function previousFromPeerNationals(FuelPrice $row, float $currentPrice): ?float
    {
        $effective = $row->effective_date
            ? Carbon::parse($row->effective_date)->toDateString()
            : null;
        if ($effective === null) {
            return null;
        }

        $pakistanId = CountryScope::pakistan()->id;
        $peers = FuelPrice::query()
            ->where('fuel_type_id', $row->fuel_type_id)
            ->where(function ($q) use ($pakistanId) {
                $q->where('country_id', $pakistanId)->orWhereNull('country_id');
            })
            ->whereNull('city_id')
            ->whereNotNull('effective_date')
            ->whereDate('effective_date', '<', $effective)
            ->whereNotIn('source', ['composite', 'seed'])
            ->orderByDesc('effective_date')
            ->limit(40)
            ->get(['price', 'effective_date', 'source']);

        if ($peers->isEmpty()) {
            return null;
        }

        $bestDay = Carbon::parse((string) $peers->first()->effective_date)->toDateString();
        $sameDay = $peers->filter(function (FuelPrice $p) use ($bestDay) {
            return Carbon::parse((string) $p->effective_date)->toDateString() === $bestDay;
        });

        $ranked = $sameDay->sortByDesc(function (FuelPrice $p) {
            return match ((string) $p->source) {
                'ogra_daily' => 200,
                'propakistani' => 150,
                'pso_official' => 100,
                'trackmate' => 50,
                default => 10,
            };
        })->first();

        $prev = $ranked ? (float) $ranked->price : null;
        if ($prev !== null && $prev > 0 && $this->isSaneDelta($currentPrice, $prev)) {
            return round($prev, 2);
        }

        return null;
    }

    private function isSaneDelta(float $current, float $previous): bool
    {
        $delta = abs($current - $previous);

        return $delta <= self::MAX_SANE_DELTA || ($previous > 0 && ($delta / $previous) <= 0.08);
    }
}

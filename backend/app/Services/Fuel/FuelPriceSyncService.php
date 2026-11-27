<?php

namespace App\Services\Fuel;

use App\Contracts\FuelPriceProvider;
use App\Events\FuelPriceUpdated;
use App\Models\City;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelPriceHistory;
use App\Models\FuelType;
use App\Models\SyncLog;
use App\Repositories\PriceRepository;
use App\Repositories\StationRepository;
use App\Support\CountryScope;
use App\Support\SafeError;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FuelPriceSyncService
{
    /** @var array<string, string> upstream product → fuel_types.slug */
    private const PRODUCT_MAP = [
        'petrol' => 'petrol',
        'pmg' => 'petrol',
        'ms' => 'petrol',
        'mogas' => 'petrol',
        'premier' => 'petrol',
        'premier_euro_5' => 'petrol',
        'hsd' => 'hsd',
        'diesel' => 'hsd',
        'hi_cetane' => 'hsd',
        'hi_cetane_diesel' => 'hsd',
        'lsd' => 'ldo',
        'ldo' => 'ldo',
        'lpg' => 'lpg',
        'cng' => 'cng',
        'kerosene' => 'kerosene',
        'sko' => 'kerosene',
        'octane_plus' => 'octane_plus',
        'octane_euro_5' => 'octane_plus',
        'hi_octane' => 'hi_octane',
        'hobc' => 'hobc',
        'premium' => 'premium',
        'super' => 'super',
        'e10' => 'e10',
        'e_10' => 'e10',
        'furnace_oil' => 'furnace_oil',
        'fo' => 'furnace_oil',
        'jp1' => 'jp1',
        'jp_1' => 'jp1',
        'jet' => 'jp1',
    ];

    /**
     * PSO publishes one high-octane retail rate (Octane Euro 5). Map it to the
     * common high-octane labels users expect — same official figure, not invented.
     *
     * @var list<string>
     */
    private const PSO_HIGH_OCTANE_ALIASES = ['hi_octane', 'hobc', 'premium'];

    /**
     * Fuels the product must always have a fresh price for. A sync that returns
     * rows but leaves any of these stale/absent is flagged 'partial'.
     *
     * @var list<string>
     */
    private const MANDATORY_FUELS = ['petrol', 'hsd', 'lpg', 'kerosene'];

    /** A price older than this (in days) counts as stale for coverage checks. */
    private const STALE_AFTER_DAYS = 2;

    /**
     * Higher = more authoritative. A lower-ranked provider must never overwrite
     * a fresher higher-ranked price (stale PSO was flipping OGRA petrol/HSD).
     *
     * @var array<string, int>
     */
    private const SOURCE_RANK = [
        'ogra_daily' => 200,
        'propakistani' => 150,
        'pso_official' => 100,
        'trackmate' => 50,
        'manual' => 300,
    ];

    /** @var array<string, array{name: string, short: string}> */
    private const COMPANY_MAP = [
        'pso' => ['name' => 'Pakistan State Oil', 'short' => 'PSO'],
        'shell' => ['name' => 'Shell Pakistan', 'short' => 'Shell'],
        'wafi' => ['name' => 'Wafi Energy (Shell licensee)', 'short' => 'Wafi'],
        'pakwheels' => ['name' => 'PakWheels Aggregate', 'short' => 'PakWheels'],
        'total' => ['name' => 'PARCO / Total PARCO', 'short' => 'PARCO'],
        'totalparco' => ['name' => 'PARCO / Total PARCO', 'short' => 'PARCO'],
        'parco' => ['name' => 'PARCO / Total PARCO', 'short' => 'PARCO'],
        'attock' => ['name' => 'Attock Petroleum (APL)', 'short' => 'APL'],
        'apl' => ['name' => 'Attock Petroleum (APL)', 'short' => 'APL'],
        'go' => ['name' => 'Gas & Oil Pakistan (GO)', 'short' => 'GO'],
        'hascol' => ['name' => 'Hascol Petroleum', 'short' => 'Hascol'],
        'puma' => ['name' => 'Puma Energy Pakistan', 'short' => 'Puma'],
        'cnergyico' => ['name' => 'Cnergyico (Byco)', 'short' => 'Cnergyico'],
        'byco' => ['name' => 'Cnergyico (Byco)', 'short' => 'Cnergyico'],
        'be_energy' => ['name' => 'BE Energy (Caltex)', 'short' => 'BE'],
        'be' => ['name' => 'BE Energy (Caltex)', 'short' => 'BE'],
        'caltex' => ['name' => 'BE Energy (Caltex)', 'short' => 'BE'],
        'euro_oil' => ['name' => 'Euro Oil Pakistan', 'short' => 'Euro'],
        'euro' => ['name' => 'Euro Oil Pakistan', 'short' => 'Euro'],
        'taj' => ['name' => 'Taj Gasoline', 'short' => 'Taj'],
        'askar' => ['name' => 'Askar Oil Services', 'short' => 'Askar'],
        'zoom' => ['name' => 'Zoom Petroleum', 'short' => 'Zoom'],
        'flow' => ['name' => 'Flow Petroleum', 'short' => 'Flow'],
        'jinn' => ['name' => 'Jinn Petroleum', 'short' => 'Jinn'],
        'vital' => ['name' => 'Vital Petroleum', 'short' => 'Vital'],
        'national' => ['name' => 'National / OGRA notified', 'short' => 'National'],
        'ogra' => ['name' => 'National / OGRA notified', 'short' => 'National'],
    ];

    public function __construct(
        private readonly FuelPriceProvider $provider,
        private readonly PriceRepository $prices,
        private readonly StationRepository $stations,
        private readonly FuelPriceReconciler $reconciler,
    ) {}

    /**
     * @return array{status: string, fetched: int, created: int, updated: int, unchanged: int, message: string}
     */
    public function sync(): array
    {
        $started = now();
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        try {
            $rows = $this->provider->latestPrices();
            $fetched = count($rows);

            foreach ($rows as $row) {
                foreach ($this->expandRows($row) as $expanded) {
                    $result = $this->upsertRow($expanded);
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    } else {
                        $unchanged++;
                    }
                }
            }

            $historyPoints = $this->syncHistory(90);

            // Always heal contaminated previous_price / composite junk after ingest.
            $reconcile = $this->reconciler->reconcile();

            // Flag runs that returned rows but missed a mandatory fuel or left it
            // stale — a "success" that silently keeps an old petrol price on screen.
            $missing = $this->staleOrMissingFuels(self::MANDATORY_FUELS);
            $status = $missing === [] ? 'ok' : 'partial';
            $message = "Synced {$fetched} prices ({$created} new, {$updated} changed, {$unchanged} unchanged); history points {$historyPoints}.";
            if ($missing !== []) {
                $message .= ' Stale/missing: '.implode(', ', $missing).'.';
                Log::warning('fuel:sync partial coverage', ['stale_or_missing' => $missing]);
            }

            SyncLog::query()->create([
                'provider' => $this->provider->name(),
                'status' => $status,
                'fetched' => $fetched,
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'message' => $message,
                'meta' => [
                    'health' => $this->provider->health(),
                    'history_points' => $historyPoints,
                    'stale_or_missing' => $missing,
                    'reconcile' => $reconcile,
                ],
                'started_at' => $started,
                'finished_at' => now(),
            ]);

            $this->prices->forgetLatestCache();
            $this->stations->forgetListCache();
            foreach (array_keys(\App\Support\SeoLandings::FUELS) as $fuelKey) {
                \Illuminate\Support\Facades\Cache::forget('seo:daily_update:'.$fuelKey);
            }

            $summary = compact('status', 'fetched', 'created', 'updated', 'unchanged', 'message') + [
                'history_points' => $historyPoints,
                'stale_or_missing' => $missing,
                'reconcile' => $reconcile,
            ];

            if ($updated > 0 || $created > 0) {
                FuelPriceUpdated::dispatch($summary);
            }

            return $summary;
        } catch (\Throwable $e) {
            SafeError::log($e, 'Fuel price sync failed', [
                'provider' => $this->provider->name(),
            ]);

            SyncLog::query()->create([
                'provider' => $this->provider->name(),
                'status' => 'failed',
                'fetched' => 0,
                'created' => $created,
                'updated' => $updated,
                'unchanged' => $unchanged,
                'message' => SafeError::storedMessage('Sync failed. See server logs for details.'),
                'started_at' => $started,
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    /**
     * Return the mandatory fuel slugs that have no price row, or whose newest
     * price is older than STALE_AFTER_DAYS. Empty array means full coverage.
     *
     * @param  list<string>  $fuelSlugs
     * @return list<string>
     */
    private function staleOrMissingFuels(array $fuelSlugs): array
    {
        $cutoff = now()->startOfDay()->subDays(self::STALE_AFTER_DAYS);
        $missing = [];

        foreach ($fuelSlugs as $slug) {
            $typeId = FuelType::query()->where('slug', $slug)->value('id');
            if ($typeId === null) {
                continue; // fuel not in catalog — not expected in the feed
            }

            $pakistanId = CountryScope::pakistan()->id;
            $fresh = FuelPrice::query()
                ->where('fuel_type_id', $typeId)
                ->where(function ($q) use ($pakistanId) {
                    $q->where('country_id', $pakistanId)->orWhereNull('country_id');
                })
                ->whereNotNull('effective_date')
                ->where('effective_date', '>=', $cutoff)
                ->exists();

            if (! $fresh) {
                $missing[] = $slug;
            }
        }

        return $missing;
    }

    /**
     * Pull TrackMate history and store revision steps (deduped by day/price).
     */
    public function syncHistory(int $days = 90): int
    {
        $products = ['petrol', 'hsd', 'lpg', 'kerosene', 'lsd'];
        $stored = 0;

        foreach ($products as $product) {
            try {
                $rows = $this->provider->history($product, null, $days);
            } catch (\Throwable) {
                continue;
            }

            // Collapse scrape noise → one point per company/product/day when price changes.
            $lastPriceByKey = [];
            usort($rows, fn ($a, $b) => strcmp($a['date'], $b['date']));

            foreach ($rows as $row) {
                $source = Str::slug((string) ($row['source'] ?? 'unknown'));
                $fuelSlug = self::PRODUCT_MAP[(string) ($row['product'] ?? $product)]
                    ?? Str::slug((string) ($row['product'] ?? $product));
                $key = $source.'|'.$fuelSlug;
                $price = round((float) $row['price'], 2);
                $date = (string) $row['date'];

                if (isset($lastPriceByKey[$key]) && abs($lastPriceByKey[$key]['price'] - $price) < 0.001) {
                    continue;
                }

                $previous = $lastPriceByKey[$key]['price'] ?? null;
                $lastPriceByKey[$key] = ['price' => $price, 'date' => $date];

                $pakistanId = CountryScope::pakistan()->id;
                $company = FuelCompany::query()->firstOrCreate(
                    ['slug' => $source],
                    [
                        'country_id' => $pakistanId,
                        'name' => self::COMPANY_MAP[$source]['name'] ?? Str::headline($source),
                        'short_name' => self::COMPANY_MAP[$source]['short'] ?? Str::upper(Str::limit($source, 8, '')),
                        'is_active' => true,
                        'priority' => 100,
                    ],
                );

                $fuelType = FuelType::query()->firstOrCreate(
                    ['slug' => $fuelSlug],
                    [
                        'name' => $this->fuelTypeName($fuelSlug),
                        'category' => $this->fuelCategory($fuelSlug),
                        'unit' => $this->unitLabel((string) ($row['unit'] ?? 'litre')),
                        'is_active' => true,
                        'priority' => 100,
                    ],
                );

                $pakistanId = CountryScope::pakistan()->id;
                // Attach history to the real national OMC row (any live source),
                // never mint a separate "composite" price card that can leak to Home.
                $fuelPrice = FuelPrice::query()
                    ->where('company_id', $company->id)
                    ->where('fuel_type_id', $fuelType->id)
                    ->whereNull('city_id')
                    ->where(function ($q) use ($pakistanId) {
                        $q->where('country_id', $pakistanId)->orWhereNull('country_id');
                    })
                    ->whereNotIn('source', ['composite', 'seed'])
                    ->orderByDesc('effective_date')
                    ->orderByDesc('id')
                    ->first();

                if ($fuelPrice === null) {
                    $fuelPrice = FuelPrice::query()->create([
                        'country_id' => $pakistanId,
                        'company_id' => $company->id,
                        'city_id' => null,
                        'fuel_type_id' => $fuelType->id,
                        'price' => $price,
                        'previous_price' => $this->sanitizePrevious($price, $previous),
                        'currency' => 'PKR',
                        'unit' => $this->unitLabel((string) ($row['unit'] ?? 'litre')),
                        'effective_date' => $date,
                        'source' => 'trackmate',
                        'source_url' => 'https://fuel.trackmate.page/api/history',
                        'verified' => false,
                    ]);
                }

                $exists = FuelPriceHistory::query()
                    ->where('fuel_price_id', $fuelPrice->id)
                    ->whereDate('effective_at', $date)
                    ->where('new_price', $price)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $sanePrevious = $this->sanitizePrevious($price, $previous);
                $change = $sanePrevious === null ? 0.0 : round($price - $sanePrevious, 2);
                // Skip writing history spikes that skip intermediate revisions.
                if (abs($change) > FuelPriceReconciler::MAX_SANE_DELTA) {
                    continue;
                }
                $pct = ($sanePrevious !== null && $sanePrevious > 0) ? round(($change / $sanePrevious) * 100, 4) : 0;

                FuelPriceHistory::query()->create([
                    'fuel_price_id' => $fuelPrice->id,
                    'previous_price' => $sanePrevious,
                    'new_price' => $price,
                    'change_amount' => $change,
                    'change_percentage' => $pct,
                    'effective_at' => Carbon::parse($date)->startOfDay(),
                ]);

                $stored++;
            }
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<array<string, mixed>>
     */
    private function expandRows(array $row): array
    {
        $out = [$row];
        $sourceSlug = Str::slug((string) ($row['source'] ?? ''));
        $productSlug = (string) ($row['product'] ?? '');
        $fuelSlug = self::PRODUCT_MAP[$productSlug] ?? Str::slug($productSlug);

        if ($sourceSlug === 'pso' && $fuelSlug === 'octane_plus') {
            foreach (self::PSO_HIGH_OCTANE_ALIASES as $alias) {
                $clone = $row;
                $clone['product'] = $alias;
                $out[] = $clone;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function upsertRow(array $row): string
    {
        $sourceSlug = Str::slug((string) ($row['source'] ?? 'unknown'));
        $productSlug = (string) ($row['product'] ?? '');
        $fuelSlug = self::PRODUCT_MAP[$productSlug] ?? Str::slug($productSlug);
        $dataSource = (string) ($row['provider'] ?? $this->provider->name());
        $sourceUrl = (string) ($row['source_url'] ?? match ($dataSource) {
            'pso_official' => 'https://psopk.com/en/fuels/fuel-prices',
            default => 'https://fuel.trackmate.page/api/prices',
        });

        if ($fuelSlug === '' || $sourceSlug === '') {
            return 'unchanged';
        }

        $companyMeta = self::COMPANY_MAP[$sourceSlug] ?? [
            'name' => Str::headline($sourceSlug),
            'short' => Str::upper(Str::limit($sourceSlug, 8, '')),
        ];

        // Live sync providers are Pakistan-only in Phase 1.
        $pakistanId = CountryScope::pakistan()->id;

        $company = FuelCompany::query()->firstOrCreate(
            ['slug' => $sourceSlug],
            [
                'country_id' => $pakistanId,
                'name' => $companyMeta['name'],
                'short_name' => $companyMeta['short'],
                'is_active' => true,
                'priority' => $sourceSlug === 'pso' ? 10 : 100,
            ],
        );
        if ($company->country_id === null) {
            $company->update(['country_id' => $pakistanId]);
        }

        $fuelType = FuelType::query()->firstOrCreate(
            ['slug' => $fuelSlug],
            [
                'name' => $this->fuelTypeName($fuelSlug),
                'category' => $this->fuelCategory($fuelSlug),
                'unit' => $this->unitLabel((string) ($row['unit'] ?? 'litre')),
                'is_active' => true,
                'priority' => match ($fuelSlug) {
                    'petrol' => 10,
                    'hsd' => 20,
                    'lpg' => 30,
                    'kerosene' => 40,
                    'ldo' => 50,
                    default => 100,
                },
            ],
        );

        $cityId = null;
        $cityName = trim((string) ($row['city'] ?? ''));
        if ($cityName !== '') {
            $city = City::query()->firstOrCreate(
                [
                    'country_id' => $pakistanId,
                    'slug' => Str::slug($cityName),
                ],
                [
                    'name' => $cityName,
                    'is_active' => true,
                ],
            );
            $cityId = $city->id;
        }

        $price = round((float) ($row['price_pkr'] ?? $row['price'] ?? 0), 2);
        if ($price <= 0) {
            return 'unchanged';
        }

        $unit = $this->unitLabel((string) ($row['unit'] ?? $fuelType->unit));
        $effective = $this->parseEffectiveDate($row['effective_date'] ?? $row['scraped_at'] ?? null);

        // Prefer the highest-accuracy row for this company/fuel/city (any provider tag).
        $existing = FuelPrice::query()
            ->where('company_id', $company->id)
            ->where('fuel_type_id', $fuelType->id)
            ->where(function ($q) use ($pakistanId) {
                $q->where('country_id', $pakistanId)->orWhereNull('country_id');
            })
            ->when(
                $cityId === null,
                fn ($q) => $q->whereNull('city_id'),
                fn ($q) => $q->where('city_id', $cityId),
            )
            ->orderByRaw("CASE source WHEN 'ogra_daily' THEN 0 WHEN 'propakistani' THEN 1 WHEN 'pso_official' THEN 2 WHEN 'trackmate' THEN 3 ELSE 4 END")
            ->first();

        if ($existing === null) {
            FuelPrice::query()->create([
                'country_id' => $pakistanId,
                'company_id' => $company->id,
                'city_id' => $cityId,
                'fuel_type_id' => $fuelType->id,
                'price' => $price,
                'previous_price' => null,
                'currency' => 'PKR',
                'unit' => $unit,
                'effective_date' => $effective,
                'source' => $dataSource,
                'source_url' => $sourceUrl,
                'verified' => in_array($dataSource, ['ogra_daily', 'pso_official'], true),
            ]);

            return 'created';
        }

        if ($existing->country_id === null) {
            $existing->country_id = $pakistanId;
        }

        $incomingRank = self::SOURCE_RANK[$dataSource] ?? 10;
        $existingRank = self::SOURCE_RANK[(string) $existing->source] ?? 10;
        // Petrol/HSD: PSO's site often lags daily OGRA — treat stored pso_official
        // as disposable so TrackMate/news can heal them.
        if (
            in_array($fuelSlug, ['petrol', 'hsd'], true)
            && (string) $existing->source === 'pso_official'
        ) {
            $existingRank = 0;
        }
        if (
            $dataSource === 'pso_official'
            && in_array($fuelSlug, ['petrol', 'hsd'], true)
        ) {
            $incomingRank = 0;
        }
        // SKO/LDO: TrackMate aggregates stay on outdated placeholders (e.g. 233.71 /
        // 199.98) while PSO/OGRA publish the notified ex-depot rates — never let
        // trackmate overwrite or outrank those official specialty figures.
        if (
            in_array($fuelSlug, ['kerosene', 'ldo', 'lsd'], true)
            && (string) $existing->source === 'trackmate'
        ) {
            $existingRank = 0;
        }
        if (
            $dataSource === 'trackmate'
            && in_array($fuelSlug, ['kerosene', 'ldo', 'lsd'], true)
        ) {
            $incomingRank = 0;
        }
        $existingEffective = $existing->effective_date
            ? Carbon::parse($existing->effective_date)->startOfDay()
            : null;
        $incomingIsNewerDate = $effective !== null
            && $existingEffective !== null
            && $effective->gt($existingEffective);
        $incomingIsOlderDate = $effective !== null
            && $existingEffective !== null
            && $effective->lt($existingEffective);

        // Weaker source may overwrite only when it carries a strictly newer
        // effective date (primary never wrote today's figure). Same-day stale
        // PSO must never flip an OGRA/news price backwards.
        if ($incomingRank < $existingRank && ! $incomingIsNewerDate) {
            return 'unchanged';
        }

        if ($incomingIsOlderDate && $incomingRank <= $existingRank) {
            return 'unchanged';
        }

        if ((float) $existing->price === $price) {
            $updates = [
                'effective_date' => $effective ?? $existing->effective_date,
                'unit' => $unit,
            ];
            // Promote source tag when equal/higher rank; never demote it.
            if ($incomingRank >= $existingRank) {
                $updates['source'] = $dataSource;
                $updates['source_url'] = $sourceUrl;
                $updates['verified'] = in_array($dataSource, ['ogra_daily', 'pso_official'], true)
                    ? true
                    : $existing->verified;
            }
            $existing->update($updates);

            return 'unchanged';
        }

        $previous = (float) $existing->price;
        $sanePrevious = $this->sanitizePrevious($price, $previous);
        if ($sanePrevious === null) {
            $resolved = $this->reconciler->resolvePrevious($existing);
            $sanePrevious = ($resolved !== null && $this->isSaneDelta($price, $resolved))
                ? $resolved
                : null;
        }
        $change = $sanePrevious === null ? 0.0 : round($price - $sanePrevious, 2);
        $pct = ($sanePrevious !== null && $sanePrevious > 0) ? round(($change / $sanePrevious) * 100, 4) : 0;

        $existing->update([
            'previous_price' => $sanePrevious,
            'price' => $price,
            'unit' => $unit,
            'effective_date' => $effective,
            'source' => $dataSource,
            'source_url' => $sourceUrl,
            'verified' => in_array($dataSource, ['ogra_daily', 'pso_official'], true),
        ]);

        if (abs($change) <= FuelPriceReconciler::MAX_SANE_DELTA) {
            FuelPriceHistory::query()->create([
                'fuel_price_id' => $existing->id,
                'previous_price' => $sanePrevious,
                'new_price' => $price,
                'change_amount' => $change,
                'change_percentage' => $pct,
                'effective_at' => $effective?->startOfDay() ?? now(),
            ]);
        }

        return 'updated';
    }

    private function sanitizePrevious(float $current, ?float $previous): ?float
    {
        if ($previous === null || $previous <= 0) {
            return null;
        }
        if (! $this->isSaneDelta($current, $previous)) {
            return null;
        }

        return round($previous, 2);
    }

    private function isSaneDelta(float $current, float $previous): bool
    {
        $delta = abs($current - $previous);

        return $delta <= FuelPriceReconciler::MAX_SANE_DELTA
            || ($previous > 0 && ($delta / $previous) <= 0.08);
    }

    private function fuelTypeName(string $slug): string
    {
        return match ($slug) {
            'petrol' => 'Petrol (MS / PMG)',
            'hsd' => 'HSD Diesel',
            'ldo', 'lsd' => 'Light Diesel Oil (LDO)',
            'lpg' => 'LPG',
            'cng' => 'CNG',
            'kerosene' => 'Kerosene (SKO)',
            'octane_plus' => 'Octane+',
            'hi_octane' => 'Hi-Octane',
            'hobc' => 'HOBC (High Octane)',
            'premium' => 'Premium Petrol',
            'super' => 'Super Petrol',
            'e10' => 'E-10 Petrol',
            'furnace_oil' => 'Furnace Oil',
            'jp1' => 'JP-1 Jet Fuel',
            default => Str::headline($slug),
        };
    }

    private function fuelCategory(string $slug): string
    {
        return match ($slug) {
            'petrol', 'octane_plus', 'hi_octane', 'hobc', 'premium', 'super', 'e10' => 'petrol',
            'hsd', 'ldo', 'lsd' => 'diesel',
            'lpg', 'cng' => 'gas',
            'kerosene' => 'other',
            'furnace_oil', 'jp1' => 'industrial',
            default => 'other',
        };
    }

    private function unitLabel(string $unit): string
    {
        $unit = strtolower(trim($unit));

        return match (true) {
            str_contains($unit, 'kg') => 'Rs/kg',
            str_contains($unit, 'rs/') => $unit,
            default => 'Rs/L',
        };
    }

    private function parseEffectiveDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return now()->startOfDay();
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}

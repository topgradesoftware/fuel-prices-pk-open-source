<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nnjeim\World\Models\City as WorldCity;
use Nnjeim\World\Models\Country as WorldCountry;
use Nnjeim\World\Models\Currency as WorldCurrency;

/**
 * Copies nnjeim/world catalog into fuel-app countries/cities tables.
 * Preserves PK fuel companies/prices; adds demo national rows for IN/BD.
 */
class WorldFuelCatalogSeeder extends Seeder
{
    /** Prefer these at the top of country pickers. */
    private const PINNED_CODES = ['PK', 'IN', 'BD', 'AE', 'SA', 'US', 'GB'];

    public function run(): void
    {
        if (! WorldCountry::query()->exists()) {
            $this->command?->warn('world_countries is empty — run WorldSeeder first.');

            return;
        }

        $this->command?->info('Syncing world countries → fuel countries…');
        $this->syncCountries();

        $this->command?->info('Syncing world cities → fuel cities…');
        $this->syncCities();

        $this->command?->info('Ensuring IN/BD demo national prices…');
        $this->ensureSouthAsiaDemoPrices();

        // Keep Pakistan branding for currency display.
        Country::query()->where('code', 'PK')->update([
            'currency' => 'PKR',
            'currency_symbol' => 'Rs',
            'sort_order' => 10,
        ]);

        $this->command?->info('World fuel catalog sync complete.');
    }

    private function syncCountries(): void
    {
        $currencies = WorldCurrency::query()
            ->get(['country_id', 'code', 'symbol', 'symbol_native'])
            ->keyBy('country_id');

        $sort = 100;
        WorldCountry::query()
            ->orderBy('name')
            ->chunkById(100, function ($rows) use ($currencies, &$sort) {
                foreach ($rows as $world) {
                    $code = strtoupper((string) $world->iso2);
                    if ($code === '') {
                        continue;
                    }

                    $currency = $currencies->get($world->id);
                    $pinIndex = array_search($code, self::PINNED_CODES, true);
                    $sortOrder = $pinIndex === false ? $sort++ : ($pinIndex + 1) * 10;

                    Country::query()->updateOrCreate(
                        ['code' => $code],
                        [
                            'name' => (string) $world->name,
                            'currency' => $currency?->code ?: 'USD',
                            'currency_symbol' => $currency?->symbol_native
                                ?: $currency?->symbol
                                ?: $currency?->code
                                ?: '$',
                            'unit_volume' => 'L',
                            'is_active' => (bool) ($world->status ?? true),
                            'sort_order' => $sortOrder,
                        ],
                    );
                }
            });
    }

    private function syncCities(): void
    {
        $countryIds = Country::query()->pluck('id', 'code');

        WorldCity::query()
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($countryIds) {
                $payload = [];
                $now = now();

                foreach ($rows as $world) {
                    $code = strtoupper((string) ($world->country_code ?? ''));
                    $countryId = $countryIds[$code] ?? null;
                    if ($countryId === null) {
                        continue;
                    }

                    $name = trim((string) $world->name);
                    if ($name === '') {
                        continue;
                    }

                    $slug = Str::slug($name);
                    if ($slug === '') {
                        $slug = 'city-'.$world->id;
                    }

                    $payload[] = [
                        'country_id' => $countryId,
                        'slug' => $slug,
                        'name' => $name,
                        'province' => $world->state_code ?: null,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($payload === []) {
                    return;
                }

                // Chunk upserts — unique on (country_id, slug).
                foreach (array_chunk($payload, 200) as $chunk) {
                    DB::table('cities')->upsert(
                        $chunk,
                        ['country_id', 'slug'],
                        ['name', 'province', 'is_active', 'updated_at'],
                    );
                }
            });
    }

    private function ensureSouthAsiaDemoPrices(): void
    {
        $this->ensureNationalCompanyAndPrices('IN', 'national-in', 'National (India)', [
            'petrol' => 94.77,
            'hsd' => 87.67,
        ], '₹/L', 'INR', '₹');

        $this->ensureNationalCompanyAndPrices('BD', 'national-bd', 'National (Bangladesh)', [
            'petrol' => 125.00,
            'hsd' => 109.00,
        ], '৳/L', 'BDT', '৳');
    }

    /**
     * @param  array<string, float>  $prices
     */
    private function ensureNationalCompanyAndPrices(
        string $countryCode,
        string $companySlug,
        string $companyName,
        array $prices,
        string $unit,
        string $currency,
        string $symbol,
    ): void {
        $country = Country::query()->where('code', $countryCode)->first();
        if (! $country) {
            return;
        }

        $country->update([
            'currency' => $currency,
            'currency_symbol' => $symbol,
        ]);

        $company = FuelCompany::query()->updateOrCreate(
            ['slug' => $companySlug],
            [
                'country_id' => $country->id,
                'name' => $companyName,
                'short_name' => 'National',
                'is_active' => true,
                'priority' => 190,
            ],
        );

        foreach ($prices as $fuelSlug => $amount) {
            $fuelType = FuelType::query()->where('slug', $fuelSlug)->first();
            if (! $fuelType) {
                continue;
            }

            FuelPrice::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'company_id' => $company->id,
                    'city_id' => null,
                    'fuel_type_id' => $fuelType->id,
                    'source' => 'seed',
                ],
                [
                    'price' => $amount,
                    'previous_price' => null,
                    'currency' => $currency,
                    'unit' => $unit,
                    'effective_date' => now()->toDateString(),
                    'verified' => false,
                ],
            );
        }
    }
}

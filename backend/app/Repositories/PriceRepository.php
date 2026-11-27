<?php

namespace App\Repositories;

use App\Models\FuelPrice;
use App\Support\CountryScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PriceRepository
{
    private const CACHE_TTL = 300;

    private const VERSION_KEY = 'prices:cache_version';

    public function latest(
        ?string $city = null,
        ?string $company = null,
        ?string $product = null,
        ?string $country = null,
    ): Collection {
        $countryCode = CountryScope::normalize($country);
        $version = (string) Cache::get(self::VERSION_KEY, '0');
        $key = 'prices:latest:'.$version.':'.md5(json_encode([$countryCode, $city, $company, $product]));

        $cached = Cache::get($key);
        if ($cached instanceof Collection) {
            return $cached;
        }

        if ($cached !== null) {
            // Stale/incomplete serialized payloads (e.g. __PHP_Incomplete_Class) must not be reused.
            Cache::forget($key);
        }

        $fresh = $this->queryLatest($city, $company, $product, $countryCode);
        Cache::put($key, $fresh, self::CACHE_TTL);

        return $fresh;
    }

    private function queryLatest(
        ?string $city,
        ?string $company,
        ?string $product,
        string $countryCode,
    ): Collection {
        $countryModel = CountryScope::resolve($countryCode);
        $countryId = $countryModel->id;

        $query = FuelPrice::query()
            ->with([
                'company:id,name,short_name,slug,country_id',
                'city:id,name,slug,country_id',
                'fuelType:id,name,slug,category,unit',
                'country:id,code,currency,currency_symbol',
            ])
            ->where(function ($q) use ($countryId, $countryCode) {
                $q->where('country_id', $countryId);
                if ($countryCode === CountryScope::DEFAULT_CODE) {
                    $q->orWhereNull('country_id');
                }
            })
            ->whereHas('company', fn ($q) => $q->where('is_active', true))
            ->whereHas('fuelType', fn ($q) => $q->where('is_active', true));

        if ($city && $city !== 'national') {
            $query->where(function ($q) use ($city, $countryId, $countryCode) {
                $q->whereHas('city', function ($c) use ($city, $countryId, $countryCode) {
                    $c->where(function ($slugOrName) use ($city) {
                        $slugOrName->where('slug', $city)->orWhere('name', $city);
                    })->where(function ($country) use ($countryId, $countryCode) {
                        $country->where('country_id', $countryId);
                        if ($countryCode === CountryScope::DEFAULT_CODE) {
                            $country->orWhereNull('country_id');
                        }
                    });
                })->orWhereNull('city_id');
            });
        }

        if ($company) {
            $query->whereHas('company', fn ($c) => $c->where('slug', $company));
        }

        if ($product) {
            $query->whereHas('fuelType', fn ($t) => $t->where('slug', $product));
        }

        return $query->get();
    }

    public function forgetLatestCache(): void
    {
        Cache::forever(self::VERSION_KEY, (string) now()->timestamp);
    }
}

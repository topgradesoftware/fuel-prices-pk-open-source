<?php

namespace App\Repositories;

use App\Models\FuelStation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class StationRepository
{
    private const CACHE_TTL = 600;

    private const VERSION_KEY = 'stations:cache_version';

    public function list(?string $city = null, ?string $company = null, ?string $q = null, int $limit = 100): Collection
    {
        $limit = max(1, min($limit, 100));
        $version = (string) Cache::get(self::VERSION_KEY, '0');
        $key = 'stations:list:'.$version.':'.md5(json_encode([$city, $company, $q, $limit]));

        return Cache::remember($key, self::CACHE_TTL, function () use ($city, $company, $q, $limit) {
            $query = FuelStation::query()
                ->with(['company:id,name,short_name,slug', 'city:id,name,slug'])
                ->where('is_active', true);

            if ($city) {
                $query->whereHas('city', fn ($c) => $c->where('slug', $city));
            }

            if ($company) {
                $query->whereHas('company', fn ($c) => $c->where('slug', $company));
            }

            if (filled($q)) {
                $term = '%'.addcslashes((string) $q, '%_\\').'%';
                $query->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('address', 'like', $term)
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $term)
                            ->orWhere('short_name', 'like', $term)
                            ->orWhere('slug', 'like', $term))
                        ->orWhereHas('city', fn ($c) => $c->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term));
                });
            }

            return $query->orderBy('name')->limit($limit)->get();
        });
    }

    public function forgetListCache(): void
    {
        Cache::forever(self::VERSION_KEY, (string) now()->timestamp);
    }
}

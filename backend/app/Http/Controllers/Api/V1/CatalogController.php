<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CatalogSearchRequest;
use App\Http\Requests\Api\V1\FuelTypesRequest;
use App\Http\Requests\Api\V1\ListStationsRequest;
use App\Http\Requests\Api\V1\NearbyStationsRequest;
use App\Models\City;
use App\Models\Country;
use App\Models\FuelCompany;
use App\Models\FuelStation;
use App\Models\FuelType;
use App\Repositories\StationRepository;
use App\Services\Maps\GooglePlacesStationService;
use App\Support\ApiResponse;
use App\Support\CountryScope;
use App\Support\SafeError;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function __construct(
        private readonly StationRepository $stations,
        private readonly GooglePlacesStationService $places,
    ) {}

    public function countries(): JsonResponse
    {
        $items = Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name', 'currency', 'currency_symbol', 'unit_volume', 'sort_order']);

        return ApiResponse::success(['items' => $items], 'Countries');
    }

    public function companies(CatalogSearchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $q = trim((string) ($data['q'] ?? ''));
        $country = CountryScope::resolve($data['country'] ?? null);

        $items = FuelCompany::query()
            ->where('is_active', true)
            ->where(function ($query) use ($country) {
                $query->where('country_id', $country->id);
                if ($country->code === CountryScope::DEFAULT_CODE) {
                    $query->orWhereNull('country_id');
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('short_name', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            })
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'slug', 'logo', 'website', 'priority', 'country_id']);

        return ApiResponse::success([
            'items' => $items,
            'q' => $q ?: null,
            'country' => $country->code,
        ], 'Companies');
    }

    public function cities(CatalogSearchRequest $request): JsonResponse
    {
        $data = $request->validated();
        $q = trim((string) ($data['q'] ?? ''));
        $country = CountryScope::resolve($data['country'] ?? null);

        $items = City::query()
            ->where('is_active', true)
            ->where(function ($query) use ($country) {
                $query->where('country_id', $country->id);
                if ($country->code === CountryScope::DEFAULT_CODE) {
                    $query->orWhereNull('country_id');
                }
            })
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('province', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'province', 'country_id']);

        return ApiResponse::success([
            'items' => $items,
            'q' => $q ?: null,
            'country' => $country->code,
        ], 'Cities');
    }

    public function fuelTypes(FuelTypesRequest $request): JsonResponse
    {
        $q = trim((string) ($request->validated('q') ?? ''));

        $items = FuelType::query()
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('category', 'like', $term);
                });
            })
            ->orderBy('priority')
            ->get(['id', 'name', 'slug', 'category', 'unit']);

        return ApiResponse::success(['items' => $items, 'q' => $q ?: null], 'Fuel types');
    }

    public function stations(ListStationsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $city = $data['city'] ?? null;
        $company = $data['company'] ?? null;
        $q = isset($data['q']) ? trim((string) $data['q']) : null;
        $q = $q !== '' ? $q : null;
        $limit = (int) ($data['limit'] ?? 100);

        $items = $this->stations->list($city, $company, $q, $limit)->map(fn (FuelStation $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'address' => $s->address,
            'lat' => (float) $s->lat,
            'lng' => (float) $s->lng,
            'is_open_24h' => (bool) $s->is_open_24h,
            'company_id' => $s->company?->slug,
            'company' => $s->company?->short_name,
            'city_id' => $s->city?->slug,
            'city' => $s->city?->name,
            'maps_url' => 'https://www.google.com/maps/dir/?api=1&destination='.$s->lat.','.$s->lng,
        ])->values();

        return ApiResponse::success([
            'items' => $items,
            'q' => $q,
            'city' => $city,
            'company' => $company,
        ], 'Stations');
    }

    public function nearbyStations(NearbyStationsRequest $request): JsonResponse
    {
        if (! $this->places->enabled()) {
            return ApiResponse::error('Nearby stations are temporarily unavailable.', null, 503);
        }

        $lat = (float) $request->validated('lat');
        $lng = (float) $request->validated('lng');
        $radius = (int) ($request->validated('radius') ?? 5000);
        $limit = (int) ($request->validated('limit') ?? 20);
        $q = trim((string) ($request->validated('q') ?? ''));
        $company = trim((string) ($request->validated('company') ?? ''));

        try {
            $items = $this->places->nearbyGasStations($lat, $lng, $radius, $limit);
        } catch (\Throwable $e) {
            SafeError::log($e, 'API nearby stations failed');

            return ApiResponse::error('Nearby stations are temporarily unavailable.', null, 503);
        }

        if ($company !== '') {
            $items = array_values(array_filter(
                $items,
                fn (array $s) => ($s['company_id'] ?? '') === $company,
            ));
        }

        if ($q !== '') {
            $needle = Str::lower($q);
            $items = array_values(array_filter($items, function (array $s) use ($needle) {
                $hay = Str::lower(implode(' ', array_filter([
                    $s['name'] ?? '',
                    $s['address'] ?? '',
                    $s['company'] ?? '',
                ])));

                return str_contains($hay, $needle);
            }));
        }

        return ApiResponse::success([
            'items' => $items,
            'origin' => ['lat' => $lat, 'lng' => $lng],
            'radius' => $radius,
            'source' => 'google_places',
            'count' => count($items),
        ], 'Nearby stations');
    }
}

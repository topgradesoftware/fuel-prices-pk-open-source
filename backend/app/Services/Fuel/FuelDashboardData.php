<?php

namespace App\Services\Fuel;

use App\Models\Article;
use App\Models\City;
use App\Models\Country;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelStation;
use App\Models\FuelType;
use App\Models\SyncLog;
use App\Support\CountryScope;

class FuelDashboardData
{
    public function __construct(
        private readonly FuelHistorySeries $historySeries,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forPublicApp(): array
    {
        $defaultCode = CountryScope::DEFAULT_CODE;

        $countries = Country::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['code', 'name', 'currency', 'currency_symbol', 'unit_volume', 'sort_order'])
            ->map(fn (Country $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'currency' => $c->currency,
                'currency_symbol' => $c->currency_symbol,
                'unit_volume' => $c->unit_volume,
            ])
            ->values();

        if ($countries->isEmpty()) {
            $countries = collect([[
                'code' => 'PK',
                'name' => 'Pakistan',
                'currency' => 'PKR',
                'currency_symbol' => 'Rs',
                'unit_volume' => 'L',
            ]]);
        }

        $defaultCountry = Country::query()->where('code', $defaultCode)->first();
        $pkId = $defaultCountry?->id;

        // Only seed cities for the default country in the first paint — others
        // load via GET /api/v1/cities?country=XX (full world catalog is huge).
        $cities = collect();
        if ($pkId) {
            $cities = City::query()
                ->where('is_active', true)
                ->where('country_id', $pkId)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'province', 'country_id'])
                ->map(fn (City $c) => [
                    'id' => $c->slug,
                    'name' => $c->name,
                    'province' => $c->province,
                    'country_code' => $defaultCode,
                ])
                ->values();
        }

        $cities = collect([[
            'id' => 'national',
            'name' => 'National',
            'province' => null,
            'country_code' => $defaultCode,
        ]])->concat($cities)->values();

        $companies = FuelCompany::query()
            ->with('country:id,code')
            ->where('is_active', true)
            ->where('slug', '!=', 'pakwheels')
            ->when($pkId, function ($q) use ($pkId) {
                $q->where(function ($inner) use ($pkId) {
                    $inner->where('country_id', $pkId)->orWhereNull('country_id');
                });
            })
            ->orderBy('priority')
            ->get(['id', 'name', 'short_name', 'slug', 'country_id'])
            ->map(fn (FuelCompany $c) => [
                'id' => $c->slug,
                'name' => $c->name,
                'short' => $c->short_name,
                'country_code' => $c->country?->code ?? $defaultCode,
            ])
            ->values();

        $fuelTypes = FuelType::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->get(['id', 'name', 'slug', 'unit', 'category'])
            ->map(fn (FuelType $t) => [
                'id' => $t->slug,
                'name' => $t->name,
                'unit' => $t->unit,
                'category' => $t->category,
            ])
            ->values();

        // Prices: include all countries that have rows (PK live + demo others).
        // Never expose composite/seed junk cards on the public home surface.
        $prices = FuelPrice::query()
            ->with([
                'company:id,slug,short_name',
                'city:id,slug,name,country_id',
                'city.country:id,code',
                'fuelType:id,slug,name,unit',
                'country:id,code',
            ])
            ->where(function ($q) {
                $q->whereNull('source')
                    ->orWhereNotIn('source', ['composite', 'seed']);
            })
            ->get()
            ->map(function (FuelPrice $p) use ($defaultCode) {
                $healed = app(FuelPriceReconciler::class)->resolvePrevious($p);

                return [
                    'company_id' => $p->company?->slug,
                    'city_id' => $p->city?->slug ?? 'national',
                    'country_code' => $p->country?->code
                        ?? $p->city?->country?->code
                        ?? $defaultCode,
                    'fuel_type_id' => $p->fuelType?->slug,
                    'price' => (float) $p->price,
                    'previous_price' => $healed,
                    'unit' => $p->unit,
                    'currency' => $p->currency,
                    'effective_date' => optional($p->effective_date)?->toDateString(),
                    'source' => $p->source,
                ];
            })
            ->values();

        $history = [];
        foreach (['petrol', 'hsd', 'lpg'] as $slug) {
            $history[$slug] = $this->historySeries->points($slug, 365);
        }

        $lastSync = SyncLog::query()->latest('id')->first();

        $stations = FuelStation::query()
            ->with(['company:id,name,short_name,slug', 'city:id,name,slug,country_id', 'city.country:id,code'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (FuelStation $s) => [
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
                'country_code' => $s->city?->country?->code ?? $defaultCode,
                'maps_url' => 'https://www.google.com/maps/dir/?api=1&destination='.$s->lat.','.$s->lng,
            ])
            ->values();

        $fuelNews = Article::query()
            ->published()
            ->where('category', 'fuel-news')
            ->latest('published_at')
            ->take(4)
            ->get()
            ->map(fn (Article $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'excerpt' => $a->excerpt,
                'cover_image' => $a->cover_image,
                'published_at' => optional($a->published_at)?->format('d M Y'),
                'url' => route('guides.show', $a->slug),
            ])
            ->values();

        if ($fuelNews->isEmpty()) {
            $fuelNews = Article::query()
                ->published()
                ->latest('published_at')
                ->take(4)
                ->get()
                ->map(fn (Article $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'slug' => $a->slug,
                    'excerpt' => $a->excerpt,
                    'cover_image' => $a->cover_image,
                    'published_at' => optional($a->published_at)?->format('d M Y'),
                    'url' => route('guides.show', $a->slug),
                ])
                ->values();
        }

        return [
            'countries' => $countries,
            'default_country' => $defaultCode,
            'cities' => $cities,
            'companies' => $companies,
            'fuel_types' => $fuelTypes,
            'prices' => $prices,
            'history' => $history,
            'stations' => $stations,
            'fuel_news' => $fuelNews,
            'last_sync' => $lastSync ? [
                'status' => $lastSync->status,
                'finished_at' => optional($lastSync->finished_at)
                    ?->timezone(config('app.timezone'))
                    ->format('d M Y, h:i A'),
            ] : null,
            'auth' => [
                'google_login' => (bool) config('features.google_login')
                    && filled(config('services.google.client_id')),
                'google_client_id' => config('services.google.client_id') ?: null,
            ],
        ];
    }
}

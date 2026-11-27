<?php

namespace App\Http\Controllers;

use App\Models\FuelType;
use App\Support\Seo;
use App\Support\SeoLandings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SeoLandingController extends Controller
{
    public function faq(): Response
    {
        return Inertia::render('Seo/Faq', [
            'seoMeta' => Seo::forPath('faq'),
            'faqs' => array_values(array_filter(config('seo.home_faq', []), 'is_array')),
            'app_name' => Seo::siteName(),
        ]);
    }

    public function prices(): Response
    {
        $cities = SeoLandings::activeCities()->map(fn ($c) => [
            'name' => $c->name,
            'slug' => $c->slug,
            'province' => $c->province,
        ])->values()->all();

        return Inertia::render('Seo/CityIndex', [
            'seoMeta' => Seo::forPath('prices'),
            'cities' => $cities,
            'fuels' => collect(SeoLandings::FUELS)->map(fn ($m, $key) => [
                'key' => $key,
                'label' => $m['label'],
                'prefix' => $m['prefix'],
                'hub' => $m['hub'],
            ])->values()->all(),
            'app_name' => Seo::siteName(),
        ]);
    }

    public function hub(string $fuel): Response
    {
        abort_unless(isset(SeoLandings::FUELS[$fuel]), 404);
        $meta = SeoLandings::FUELS[$fuel];
        $path = $meta['hub'];

        return Inertia::render('Seo/Hub', [
            'seoMeta' => Seo::forPath($path),
            'fuel' => [
                'key' => $fuel,
                'label' => $meta['label'],
                'slug' => $meta['fuel_slug'],
                'prefix' => $meta['prefix'],
                'hub' => $meta['hub'],
            ],
            'prices' => SeoLandings::priceRowsFor($meta['fuel_slug']),
            'daily_update' => SeoLandings::dailyUpdate($fuel),
            'cities' => SeoLandings::activeCities()->map(fn ($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'province' => $c->province,
            ])->values()->all(),
            'app_name' => Seo::siteName(),
        ]);
    }

    public function cityFuel(string $fuel, string $city): Response
    {
        abort_unless(isset(SeoLandings::FUELS[$fuel]), 404);
        $meta = SeoLandings::FUELS[$fuel];
        $path = $meta['prefix'].'/'.$city;
        abort_unless(SeoLandings::matches($path), 404);

        $cityRow = SeoLandings::activeCities()->firstWhere('slug', $city);

        $relatedFuels = collect(SeoLandings::FUELS)
            ->map(fn ($m, $key) => [
                'label' => $m['label'].' in '.($cityRow?->name ?? $city),
                'href' => '/'.$m['prefix'].'/'.$city,
                'key' => $key,
            ])
            ->values()
            ->all();
        $relatedFuels[] = [
            'label' => 'HOBC / high-octane overview',
            'href' => '/fuel/hobc',
            'key' => 'hobc',
        ];

        $prices = SeoLandings::priceRowsFor($meta['fuel_slug'], $city);
        $lastUpdated = collect($prices)
            ->pluck('effective_date')
            ->filter()
            ->sort()
            ->last();

        $guideCategory = match ($fuel) {
            'petrol' => 'petrol-prices',
            'hsd' => 'diesel',
            'lpg' => 'lpg',
            default => 'guides',
        };

        $relatedGuides = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('articles')) {
            $relatedGuides = \App\Models\Article::query()
                ->published()
                ->where(function ($q) use ($guideCategory, $city) {
                    $q->where('category', $guideCategory)
                        ->orWhere('category', 'guides')
                        ->orWhere('category', 'fuel-saving')
                        ->orWhere('slug', 'like', '%'.$city.'%');
                })
                ->latest('published_at')
                ->take(6)
                ->get(['slug', 'title', 'excerpt'])
                ->map(fn ($a) => [
                    'slug' => $a->slug,
                    'title' => $a->title,
                    'excerpt' => $a->excerpt,
                    'href' => '/guides/'.$a->slug,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Seo/CityFuel', [
            'seoMeta' => Seo::forPath($path),
            'fuel' => [
                'key' => $fuel,
                'label' => $meta['label'],
                'slug' => $meta['fuel_slug'],
                'prefix' => $meta['prefix'],
                'hub' => $meta['hub'],
            ],
            'city' => [
                'name' => $cityRow?->name ?? $city,
                'slug' => $city,
                'province' => $cityRow?->province,
            ],
            'prices' => $prices,
            'last_updated' => $lastUpdated ?: now()->toDateString(),
            'daily_update' => SeoLandings::dailyUpdate($fuel),
            'related_cities' => SeoLandings::activeCities()
                ->filter(fn ($c) => in_array($c->slug, SeoLandings::PRIORITY_CITIES, true) && $c->slug !== $city)
                ->take(12)
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug])
                ->values()
                ->all(),
            'related_fuels' => $relatedFuels,
            'related_guides' => $relatedGuides,
            'app_name' => Seo::siteName(),
        ]);
    }

    public function calculator(string $slug): Response
    {
        abort_unless(isset(SeoLandings::CALCULATORS[$slug]), 404);
        $meta = SeoLandings::CALCULATORS[$slug];
        $mode = $meta['mode'];

        return Inertia::render('Seo/Calculator', [
            'seoMeta' => Seo::forPath($slug),
            'mode' => $mode,
            'app_path' => '/fuel-calculator?mode='.$mode,
            'app_name' => Seo::siteName(),
        ]);
    }

    /**
     * Flat keyword aliases: /petrol-price-karachi → /petrol-price/karachi
     */
    public function flatCityAlias(string $flat): RedirectResponse
    {
        if (preg_match('/^(petrol|diesel|lpg)-price-(.+)$/', $flat, $m) === 1) {
            $fuelKey = match ($m[1]) {
                'petrol' => 'petrol',
                'diesel' => 'hsd',
                'lpg' => 'lpg',
                default => null,
            };
            if ($fuelKey === null || ! isset(SeoLandings::FUELS[$fuelKey])) {
                abort(404);
            }
            $city = $m[2];
            $prefix = SeoLandings::FUELS[$fuelKey]['prefix'];
            $path = $prefix.'/'.$city;
            abort_unless(SeoLandings::matches($path), 404);

            return redirect('/'.$path, 301);
        }

        if (preg_match('/^hobc-price-(.+)$/', $flat) === 1) {
            return redirect('/fuel/hobc', 301);
        }

        abort(404);
    }

    public function omc(string $company): Response
    {
        $path = 'omc/'.$company;
        abort_unless(SeoLandings::matches($path), 404);

        $row = SeoLandings::activeCompanies()->firstWhere('slug', $company);

        return Inertia::render('Seo/Omc', [
            'seoMeta' => Seo::forPath($path),
            'company' => [
                'name' => $row?->name,
                'short_name' => $row?->short_name,
                'slug' => $company,
            ],
            'prices' => collect(SeoLandings::FUELS)
                ->flatMap(fn ($m) => SeoLandings::priceRowsFor($m['fuel_slug'], null, $company))
                ->values()
                ->all(),
            'fuels' => collect(SeoLandings::FUELS)->map(fn ($m) => [
                'label' => $m['label'],
                'hub' => $m['hub'],
            ])->values()->all(),
            'app_name' => Seo::siteName(),
        ]);
    }

    public function fuelType(string $fuel): Response|RedirectResponse
    {
        foreach (SeoLandings::FUELS as $meta) {
            if ($meta['fuel_slug'] === $fuel) {
                return redirect('/'.$meta['hub'], 301);
            }
        }

        abort_unless(
            FuelType::query()->where('is_active', true)->where('slug', $fuel)->exists(),
            404,
        );

        return Inertia::render('Seo/Hub', [
            'seoMeta' => Seo::forPath('fuel/'.$fuel),
            'fuel' => [
                'key' => $fuel,
                'label' => FuelType::query()->where('slug', $fuel)->value('name') ?: $fuel,
                'slug' => $fuel,
                'prefix' => 'petrol-price',
                'hub' => 'petrol-price-today',
            ],
            'prices' => SeoLandings::priceRowsFor($fuel),
            'cities' => SeoLandings::activeCities()->map(fn ($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'province' => $c->province,
            ])->values()->all(),
            'app_name' => Seo::siteName(),
        ]);
    }
}

<?php

namespace App\Support;

use App\Models\City;
use App\Models\Country;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Indexable fuel-price landings (city × fuel, hubs, OMC, FAQ).
 */
final class SeoLandings
{
    /** @var array<string, array{prefix:string,label:string,hub:string,fuel_slug:string}> */
    public const FUELS = [
        'petrol' => [
            'prefix' => 'petrol-price',
            'label' => 'Petrol',
            'hub' => 'petrol-price-today',
            'fuel_slug' => 'petrol',
        ],
        'hsd' => [
            'prefix' => 'diesel-price',
            'label' => 'Diesel (HSD)',
            'hub' => 'diesel-price-today',
            'fuel_slug' => 'hsd',
        ],
        'lpg' => [
            'prefix' => 'lpg-price',
            'label' => 'LPG',
            'hub' => 'lpg-price-today',
            'fuel_slug' => 'lpg',
        ],
    ];

    /** Cities prioritized for IndexNow / llms samples. */
    public const PRIORITY_CITIES = [
        'karachi', 'lahore', 'islamabad', 'rawalpindi', 'faisalabad', 'multan',
        'peshawar', 'quetta', 'hyderabad', 'gujranwala', 'sialkot', 'bahawalpur',
        'sargodha', 'sukkur', 'mardan', 'abbottabad', 'attock', 'sahiwal',
        'rahim-yar-khan', 'gujrat',
    ];

    /**
     * Dedicated calculator SEO landings (path → mode + copy).
     *
     * @var array<string, array{mode:string,title:string,h1:string,description:string,keywords:string,breadcrumb:string,faq:list<array{question:string,answer:string}>}>
     */
    public const CALCULATORS = [
        'fuel-cost-calculator' => [
            'mode' => 'fuel',
            'title' => 'Fuel Cost Calculator Pakistan | Petrol & Diesel Trip Cost',
            'h1' => 'Fuel cost calculator for Pakistan',
            'description' => 'Estimate petrol or diesel trip cost in Pakistan with live rates. Enter distance and mileage to see litres needed and total rupees.',
            'keywords' => 'fuel cost calculator Pakistan, petrol trip cost, diesel cost calculator, fuel expense calculator',
            'breadcrumb' => 'Fuel cost calculator',
            'faq' => [
                [
                    'question' => 'How do I estimate fuel cost for a trip?',
                    'answer' => 'Enter distance (km) and your vehicle mileage (km/L). Fuel Prices PK multiplies litres needed by today’s petrol or diesel rate for your city.',
                ],
                [
                    'question' => 'Does the calculator use live Pakistan fuel prices?',
                    'answer' => 'Yes. It uses rates synced into Fuel Prices PK (OMC / OGRA-linked). Pick your city when local figures are available.',
                ],
            ],
        ],
        'trip-cost-calculator' => [
            'mode' => 'trip',
            'title' => 'Trip Fuel Cost Calculator Pakistan | One-Way & Round Trip',
            'h1' => 'Trip fuel cost calculator',
            'description' => 'Calculate one-way and round-trip petrol or diesel cost in Pakistan. Uses live fuel rates with distance and mileage inputs.',
            'keywords' => 'trip cost calculator Pakistan, round trip fuel cost, petrol trip calculator, diesel journey cost',
            'breadcrumb' => 'Trip cost calculator',
            'faq' => [
                [
                    'question' => 'Can I calculate round-trip fuel cost?',
                    'answer' => 'Yes. Enter one-way distance and mileage; the trip calculator doubles litres for a round trip and multiplies by the current fuel rate.',
                ],
                [
                    'question' => 'Which fuels are supported?',
                    'answer' => 'Petrol and diesel (HSD) are the primary options. Switch fuel type in the live calculator to match your vehicle.',
                ],
            ],
        ],
        'mileage-calculator' => [
            'mode' => 'fuel',
            'title' => 'Mileage & Fuel Consumption Calculator Pakistan',
            'h1' => 'Mileage and fuel consumption calculator',
            'description' => 'Turn km/L mileage into fuel consumption and trip cost using today’s Pakistan petrol or diesel prices.',
            'keywords' => 'mileage calculator Pakistan, fuel consumption calculator, km per litre calculator, petrol mileage cost',
            'breadcrumb' => 'Mileage calculator',
            'faq' => [
                [
                    'question' => 'How is fuel consumption calculated?',
                    'answer' => 'Litres needed = distance ÷ mileage (km per litre). Cost = litres × current fuel price for your selected city.',
                ],
                [
                    'question' => 'Should I use claimed or real-world mileage?',
                    'answer' => 'Use a realistic km/L figure for your driving (city/highway mix). Overstated brochure mileage underestimates cost.',
                ],
            ],
        ],
        'monthly-fuel-budget-calculator' => [
            'mode' => 'fuel',
            'title' => 'Monthly Fuel Budget Calculator Pakistan',
            'h1' => 'Monthly fuel budget calculator',
            'description' => 'Estimate monthly petrol or diesel spend in Pakistan from daily km, driving days and live fuel rates.',
            'keywords' => 'monthly fuel budget Pakistan, petrol monthly cost, diesel budget calculator, commuting fuel cost',
            'breadcrumb' => 'Monthly fuel budget',
            'faq' => [
                [
                    'question' => 'How do I estimate a monthly fuel budget?',
                    'answer' => 'Multiply average daily kilometres by driving days per month, convert to litres with your mileage, then multiply by today’s fuel rate.',
                ],
                [
                    'question' => 'Will price changes affect my budget?',
                    'answer' => 'Yes. Pakistan revises fuel prices frequently under OGRA’s mechanism — re-check the calculator after each update.',
                ],
            ],
        ],
    ];

    public static function matches(string $pathKey): bool
    {
        $pathKey = trim($pathKey, '/');

        if ($pathKey === 'faq' || $pathKey === 'prices') {
            return true;
        }

        if (isset(self::CALCULATORS[$pathKey])) {
            return true;
        }

        foreach (self::FUELS as $meta) {
            if ($pathKey === $meta['hub']) {
                return true;
            }

            $prefix = $meta['prefix'].'/';
            if (str_starts_with($pathKey, $prefix)) {
                $citySlug = substr($pathKey, strlen($prefix));

                return self::activeCities()->contains(fn (City $c) => $c->slug === $citySlug);
            }
        }

        if (str_starts_with($pathKey, 'omc/')) {
            $slug = substr($pathKey, 4);

            return self::activeCompanies()->contains(fn (FuelCompany $c) => $c->slug === $slug);
        }

        if (str_starts_with($pathKey, 'fuel/')) {
            $slug = substr($pathKey, 5);
            foreach (self::FUELS as $meta) {
                if ($meta['fuel_slug'] === $slug) {
                    return true;
                }
            }

            return FuelType::query()->where('is_active', true)->where('slug', $slug)->exists();
        }

        return false;
    }

    /**
     * Full SEO bag for a landing path key, or null if not a landing.
     *
     * @return array<string, mixed>|null
     */
    public static function seoBag(string $pathKey): ?array
    {
        $pathKey = trim($pathKey, '/');

        if ($pathKey === 'faq') {
            return self::buildBag('faq', self::faqPage());
        }

        if ($pathKey === 'prices') {
            return self::buildBag('prices', self::pricesIndexPage());
        }

        if (isset(self::CALCULATORS[$pathKey])) {
            return self::buildBag($pathKey, self::calculatorPage($pathKey));
        }

        foreach (self::FUELS as $fuelKey => $meta) {
            if ($pathKey === $meta['hub']) {
                return self::buildBag($pathKey, self::hubPage($fuelKey));
            }

            $prefix = $meta['prefix'].'/';
            if (str_starts_with($pathKey, $prefix)) {
                $citySlug = substr($pathKey, strlen($prefix));
                $page = self::cityFuelPage($fuelKey, $citySlug);
                if ($page === null) {
                    return null;
                }

                return self::buildBag($pathKey, $page);
            }
        }

        if (str_starts_with($pathKey, 'omc/')) {
            $companySlug = substr($pathKey, 4);
            $page = self::omcPage($companySlug);
            if ($page === null) {
                return null;
            }

            return self::buildBag($pathKey, $page);
        }

        if (str_starts_with($pathKey, 'fuel/')) {
            $fuelSlug = substr($pathKey, 5);
            $page = self::fuelTypePage($fuelSlug);
            if ($page === null) {
                return null;
            }

            return self::buildBag($pathKey, $page);
        }

        return null;
    }

    /**
     * @return list<array{path:string,priority:float,changefreq:string}>
     */
    public static function sitemapRows(): array
    {
        $rows = [
            ['path' => 'faq', 'priority' => 0.7, 'changefreq' => 'monthly'],
            ['path' => 'prices', 'priority' => 0.85, 'changefreq' => 'daily'],
        ];

        foreach (array_keys(self::CALCULATORS) as $calcPath) {
            $rows[] = ['path' => $calcPath, 'priority' => 0.9, 'changefreq' => 'weekly'];
        }

        foreach (self::FUELS as $meta) {
            $rows[] = ['path' => $meta['hub'], 'priority' => 0.95, 'changefreq' => 'hourly'];
        }

        foreach (self::activeCompanies() as $company) {
            $rows[] = [
                'path' => 'omc/'.$company->slug,
                'priority' => 0.75,
                'changefreq' => 'daily',
            ];
        }

        foreach (self::FUELS as $meta) {
            $rows[] = [
                'path' => 'fuel/'.$meta['fuel_slug'],
                'priority' => 0.8,
                'changefreq' => 'daily',
            ];
        }

        // Only Pakistan priority cities — full world city catalog (~100k+)
        // creates thin URLs that dilute crawl budget and hurt rankings.
        foreach (self::sitemapCities() as $city) {
            foreach (self::FUELS as $meta) {
                $rows[] = [
                    'path' => $meta['prefix'].'/'.$city->slug,
                    'priority' => in_array($city->slug, self::PRIORITY_CITIES, true) ? 0.9 : 0.7,
                    'changefreq' => 'hourly',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public static function priorityUrls(): array
    {
        $urls = [
            Seo::absoluteUrl('/'),
            Seo::absoluteUrl('/fuel-calculator'),
            Seo::absoluteUrl('/price-history'),
            Seo::absoluteUrl('/faq'),
            Seo::absoluteUrl('/prices'),
        ];

        foreach (array_keys(self::CALCULATORS) as $calcPath) {
            $urls[] = Seo::absoluteUrl('/'.$calcPath);
        }

        foreach (self::FUELS as $meta) {
            $urls[] = Seo::absoluteUrl('/'.$meta['hub']);
            foreach (array_slice(self::PRIORITY_CITIES, 0, 12) as $slug) {
                $urls[] = Seo::absoluteUrl('/'.$meta['prefix'].'/'.$slug);
            }
        }

        foreach (self::activeCompanies()->take(8) as $company) {
            $urls[] = Seo::absoluteUrl('/omc/'.$company->slug);
        }

        if (class_exists(\App\Models\Article::class) && \Illuminate\Support\Facades\Schema::hasTable('articles')) {
            foreach (\App\Models\Article::query()
                ->published()
                ->where(function ($q) {
                    $q->where('is_featured', true)->orWhere('is_trending', true);
                })
                ->latest('published_at')
                ->limit(40)
                ->pluck('slug') as $slug) {
                $urls[] = Seo::absoluteUrl('/guides/'.$slug);
            }
        }

        $urls[] = Seo::absoluteUrl('/guides');

        return array_values(array_unique($urls));
    }

    public static function pricesLastmod(): string
    {
        $ts = Cache::remember('seo:prices_lastmod', 300, function () {
            return FuelPrice::query()->max('updated_at') ?? now();
        });

        return \Illuminate\Support\Carbon::parse($ts)->toAtomString();
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    private static function buildBag(string $pathKey, array $page): array
    {
        $canonicalPath = '/'.$pathKey;
        $og = Seo::ogImage();
        $title = (string) ($page['title'] ?? Seo::siteName());
        $description = (string) ($page['description'] ?? Seo::defaultDescription());
        $keywords = (string) ($page['keywords'] ?? config('seo.default_keywords', ''));
        $h1 = (string) ($page['h1'] ?? Seo::h1ForPage($page));

        $jsonLd = $page['json_ld_blocks'] ?? null;
        if (! is_array($jsonLd)) {
            $jsonLd = Seo::standardPageJsonLd($pathKey, $page);
        }

        $bag = [
            'path_key' => $pathKey,
            'title' => $title,
            'h1' => $h1,
            'description' => $description,
            'keywords' => $keywords,
            'robots' => (string) ($page['robots'] ?? 'index, follow'),
            'canonical' => Seo::absoluteUrl($canonicalPath),
            'site_name' => Seo::siteName(),
            'site_url' => Seo::siteUrl(),
            'og' => [
                'type' => 'website',
                'title' => $title,
                'description' => $description,
                'url' => Seo::absoluteUrl($canonicalPath),
                'site_name' => Seo::siteName(),
                'locale' => Seo::locale(),
                'image' => $og,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title,
                'description' => $description,
                'image' => $og['url'],
            ],
            'json_ld' => $jsonLd,
        ];

        if (! empty($page['intro']) && is_string($page['intro'])) {
            $bag['intro'] = $page['intro'];
        }

        if (! empty($page['faq']) && is_array($page['faq'])) {
            $bag['faq'] = $page['faq'];
        }

        return $bag;
    }

    /**
     * @return array<string, mixed>
     */
    private static function faqPage(): array
    {
        $faq = array_values(array_filter(config('seo.home_faq', []), 'is_array'));
        $title = 'Pakistan Fuel Price FAQ | Petrol, Diesel & LPG';
        $description = 'Answers about petrol price today in Pakistan, diesel (HSD) and LPG rates, OGRA updates, OMC compare, and how Fuel Prices PK works.';
        $canonical = Seo::absoluteUrl('/faq');

        return [
            'title' => $title,
            'h1' => 'Pakistan fuel prices — frequently asked questions',
            'description' => $description,
            'keywords' => 'petrol price today Pakistan FAQ, OGRA fuel prices, diesel HSD Pakistan, LPG rate questions',
            'breadcrumb' => 'FAQ',
            'json_ld_blocks' => [
                Seo::webPageBlock('faq', $canonical, $title, $description),
                Seo::breadcrumbBlock('faq', ['breadcrumb' => 'FAQ'], $canonical),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => array_values(array_filter(array_map(static function ($item) {
                        if (! is_array($item)) {
                            return null;
                        }
                        $q = trim((string) ($item['question'] ?? ''));
                        $a = trim((string) ($item['answer'] ?? ''));
                        if ($q === '' || $a === '') {
                            return null;
                        }

                        return [
                            '@type' => 'Question',
                            'name' => $q,
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $a,
                            ],
                        ];
                    }, $faq))),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function pricesIndexPage(): array
    {
        $title = 'Fuel Prices by City in Pakistan | Petrol, Diesel & LPG';
        $description = 'Browse today’s petrol, diesel (HSD) and LPG prices by city across Pakistan — Karachi, Lahore, Islamabad and 200+ cities.';

        return [
            'title' => $title,
            'h1' => 'Pakistan fuel prices by city',
            'description' => $description,
            'keywords' => 'fuel prices by city Pakistan, petrol price by city, diesel price cities Pakistan',
            'breadcrumb' => 'Prices by city',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function hubPage(string $fuelKey): array
    {
        $meta = self::FUELS[$fuelKey];
        $label = $meta['label'];
        $sample = self::samplePrice($meta['fuel_slug']);
        $priceBit = $sample !== null
            ? ' National reference around Rs '.number_format($sample, 2).'/L.'
            : '';

        $title = "{$label} Price Today in Pakistan | Live Rates";
        $description = "Check {$label} price today in Pakistan.{$priceBit} Compare OMCs, open city pages for Karachi, Lahore, Islamabad and more, and estimate trip cost.";

        return [
            'title' => $title,
            'h1' => "{$label} price today in Pakistan",
            'description' => $description,
            'keywords' => strtolower($label)." price today Pakistan, {$meta['fuel_slug']} rate Pakistan, OGRA {$meta['fuel_slug']} price, {$label} Karachi Lahore Islamabad",
            'breadcrumb' => "{$label} today",
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cityFuelPage(string $fuelKey, string $citySlug): ?array
    {
        $city = self::activeCities()->firstWhere('slug', $citySlug);
        if (! $city) {
            return null;
        }

        $meta = self::FUELS[$fuelKey];
        $label = $meta['label'];
        $cityName = $city->name;
        $province = trim((string) ($city->province ?? ''));
        $sample = self::samplePrice($meta['fuel_slug'], $citySlug);
        $unitWord = $meta['fuel_slug'] === 'lpg' ? 'kg' : 'litre';
        $priceBit = $sample !== null
            ? ' Latest listed around Rs '.number_format($sample, 2).' per '.$unitWord.'.'
            : ' Shows local figures when available, otherwise national OGRA-linked rates.';

        $title = "{$label} Price in {$cityName} Today | Pakistan";
        $description = "{$label} price in {$cityName} today.{$priceBit} Compare PSO, Shell, APL and other OMCs on Fuel Prices PK.";
        $canonical = Seo::absoluteUrl('/'.$meta['prefix'].'/'.$citySlug);
        $placeName = $province !== '' ? "{$cityName}, {$province}" : "{$cityName}, Pakistan";

        $intro = "Check today’s {$label} price in {$cityName}"
            .($province !== '' ? " ({$province})" : '')
            .'. Fuel Prices PK lists OMC rates for this city when available, falls back to national OGRA-linked figures, and links to trip calculators and price history so you can plan fills with current numbers.';

        $jsonLd = [
            Seo::webPageBlock($meta['prefix'].'/'.$citySlug, $canonical, $title, $description),
            Seo::breadcrumbBlock($meta['prefix'].'/'.$citySlug, ['breadcrumb' => "{$cityName} {$label}"], $canonical),
            [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => "{$label} in {$cityName}",
                'description' => $description,
                'category' => 'Fuel',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => 'Pakistan retail fuel (OMC / OGRA-linked)',
                ],
                'areaServed' => [
                    '@type' => 'Place',
                    'name' => $placeName,
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $cityName,
                        'addressRegion' => $province !== '' ? $province : 'Pakistan',
                        'addressCountry' => 'PK',
                    ],
                ],
            ],
        ];

        if ($sample !== null) {
            $jsonLd[2]['offers'] = [
                '@type' => 'Offer',
                'price' => number_format($sample, 2, '.', ''),
                'priceCurrency' => 'PKR',
                'availability' => 'https://schema.org/InStock',
                'url' => $canonical,
                'priceValidUntil' => now()->addDay()->toDateString(),
            ];
        }

        $faq = [
            [
                'question' => "What is the {$label} price in {$cityName} today?",
                'answer' => $sample !== null
                    ? "The latest listed {$label} rate for {$cityName} on Fuel Prices PK is about Rs ".number_format($sample, 2)." per {$unitWord}. Compare OMCs on this page for exact figures."
                    : "Open this page after the next sync to see {$label} rates for {$cityName}, or use national OGRA-linked figures when city rows are not yet available.",
            ],
            [
                'question' => "How often do {$label} prices change in {$cityName}?",
                'answer' => 'Pakistan revises fuel prices frequently under OGRA’s pricing mechanism. Fuel Prices PK refreshes when new OMC / OGRA-linked rates are synced — check back daily.',
            ],
            [
                'question' => "Is the {$label} price in {$cityName} different from the national rate?",
                'answer' => "Regulated petrol and HSD follow national OGRA notifications. This page shows city-linked OMC rows when available and falls back to national figures otherwise. Always confirm the board at your local pump.",
            ],
            [
                'question' => "Where can I see {$label} history and trip cost for {$cityName}?",
                'answer' => "Use price history for recent trends, then the fuel cost or trip cost calculators with today’s rate. Related guides below explain OGRA pricing and fuel-saving tips.",
            ],
        ];

        $jsonLd[] = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item) => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ], $faq),
        ];

        $isPriority = in_array($citySlug, self::PRIORITY_CITIES, true);

        return [
            'title' => $title,
            'h1' => "{$label} price in {$cityName} today",
            'description' => $description,
            'keywords' => strtolower($label)." price {$cityName}, {$meta['fuel_slug']} price {$cityName}, petrol price today {$cityName}, fuel prices {$cityName} Pakistan",
            'breadcrumb' => "{$cityName} {$label}",
            'intro' => $intro,
            'faq' => $faq,
            'json_ld_blocks' => $jsonLd,
            // Only priority Pakistan cities should compete in Google; other PK
            // localities stay reachable but stay out of the index.
            'robots' => $isPriority ? 'index, follow' : 'noindex, follow',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function calculatorPage(string $pathKey): array
    {
        $meta = self::CALCULATORS[$pathKey];
        $canonical = Seo::absoluteUrl('/'.$pathKey);
        $title = $meta['title'];
        $description = $meta['description'];
        $faqEntities = [];
        foreach ($meta['faq'] as $item) {
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer'],
                ],
            ];
        }

        return [
            'title' => $title,
            'h1' => $meta['h1'],
            'description' => $description,
            'keywords' => $meta['keywords'],
            'breadcrumb' => $meta['breadcrumb'],
            'faq' => $meta['faq'],
            'json_ld_blocks' => [
                Seo::webPageBlock($pathKey, $canonical, $title, $description),
                Seo::breadcrumbBlock($pathKey, ['breadcrumb' => $meta['breadcrumb']], $canonical),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $faqEntities,
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebApplication',
                    'name' => $meta['h1'],
                    'url' => $canonical,
                    'applicationCategory' => 'UtilitiesApplication',
                    'operatingSystem' => 'Web',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'PKR',
                    ],
                    'description' => $description,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function omcPage(string $companySlug): ?array
    {
        $company = self::activeCompanies()->firstWhere('slug', $companySlug);
        if (! $company) {
            return null;
        }

        $name = $company->short_name ?: $company->name;
        $title = "{$name} Fuel Prices Today Pakistan | Petrol & Diesel";
        $description = "Live {$name} petrol, diesel and LPG prices in Pakistan. Compare with other OMCs and check city rates on Fuel Prices PK.";

        return [
            'title' => $title,
            'h1' => "{$name} fuel prices today",
            'description' => $description,
            'keywords' => "{$name} petrol price, {$name} diesel price, {$company->slug} fuel prices Pakistan",
            'breadcrumb' => $name,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fuelTypePage(string $fuelSlug): ?array
    {
        foreach (self::FUELS as $fuelKey => $meta) {
            if ($meta['fuel_slug'] === $fuelSlug) {
                return self::hubPage($fuelKey);
            }
        }

        $type = FuelType::query()->where('is_active', true)->where('slug', $fuelSlug)->first();
        if (! $type) {
            return null;
        }

        $title = "{$type->name} Price Today Pakistan";
        $description = "Check {$type->name} rates in Pakistan, compare OMCs, and open city pages on Fuel Prices PK.";

        return [
            'title' => $title,
            'h1' => "{$type->name} price today",
            'description' => $description,
            'keywords' => "{$type->name} price Pakistan, {$fuelSlug} rate today",
            'breadcrumb' => $type->name,
        ];
    }

    /**
     * Fresh, dated update paragraph for landing pages — regenerated as prices
     * change, so crawlers see new content daily under the daily pricing regime.
     *
     * @return array{date: string, text: string}|null
     */
    public static function dailyUpdate(string $fuelKey): ?array
    {
        $meta = self::FUELS[$fuelKey] ?? null;
        if ($meta === null) {
            return null;
        }

        return Cache::remember('seo:daily_update:'.$fuelKey, 900, function () use ($meta) {
            $row = FuelPrice::query()
                ->whereNull('city_id')
                ->whereHas('fuelType', fn ($q) => $q->where('slug', $meta['fuel_slug']))
                ->whereHas('company', fn ($q) => $q->where('slug', 'pso'))
                ->orderByDesc('effective_date')
                ->first()
                ?? FuelPrice::query()
                    ->whereHas('fuelType', fn ($q) => $q->where('slug', $meta['fuel_slug']))
                    ->orderByDesc('effective_date')
                    ->first();

            if ($row === null) {
                return null;
            }

            $unit = $meta['fuel_slug'] === 'lpg' ? 'per kg' : 'per litre';
            $price = (float) $row->price;
            $effective = optional($row->effective_date)?->format('j F Y') ?? now()->format('j F Y');

            $text = sprintf('%s is selling at Rs %s %s across Pakistan', $meta['label'], number_format($price, 2), $unit);

            $prev = $row->previous_price !== null ? (float) $row->previous_price : null;
            if ($prev !== null && abs($price - $prev) >= 0.01) {
                $delta = $price - $prev;
                $text .= sprintf(
                    ', %s Rs %s from the previous rate of Rs %s',
                    $delta > 0 ? 'up' : 'down',
                    number_format(abs($delta), 2),
                    number_format($prev, 2),
                );
            } elseif ($prev !== null) {
                $text .= ', unchanged from the previous rate';
            }

            $text .= sprintf(
                ', effective %s. Pakistan now revises fuel prices daily under OGRA\'s pricing mechanism, so check back each morning for the latest rate.',
                $effective,
            );

            return [
                'date' => now()->timezone('Asia/Karachi')->format('l, j F Y'),
                'text' => $text,
            ];
        });
    }

    private static function samplePrice(string $fuelSlug, ?string $citySlug = null): ?float
    {
        $key = 'seo:sample_price:'.$fuelSlug.':'.($citySlug ?? 'national');

        return Cache::remember($key, 300, function () use ($fuelSlug, $citySlug) {
            $query = FuelPrice::query()
                ->whereHas('fuelType', fn ($q) => $q->where('slug', $fuelSlug)->where('is_active', true))
                ->whereHas('company', fn ($q) => $q->where('is_active', true))
                ->orderByDesc('effective_date')
                ->orderByDesc('updated_at');

            if ($citySlug) {
                $query->where(function ($q) use ($citySlug) {
                    $q->whereHas('city', fn ($c) => $c->where('slug', $citySlug))
                        ->orWhereNull('city_id');
                });
            }

            $price = $query->value('price');

            return $price !== null ? (float) $price : null;
        });
    }

    /**
     * Cities eligible for public SEO city landings (Pakistan-focused).
     *
     * @return Collection<int, City>
     */
    public static function activeCities(): Collection
    {
        // Avoid caching Eloquent models (file/redis serialize can yield incomplete classes).
        return self::pakistanCitiesQuery()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'province']);
    }

    /**
     * Cities included in sitemap / IndexNow priority lists.
     * Restricted to PRIORITY_CITIES so Google crawls money pages, not every village.
     *
     * @return Collection<int, City>
     */
    public static function sitemapCities(): Collection
    {
        return self::pakistanCitiesQuery()
            ->whereIn('slug', self::PRIORITY_CITIES)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'province']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<City>
     */
    private static function pakistanCitiesQuery()
    {
        $pkId = Country::query()->where('code', 'PK')->value('id');

        return City::query()
            ->where('is_active', true)
            ->when(
                $pkId,
                fn ($q) => $q->where(function ($inner) use ($pkId) {
                    $inner->where('country_id', $pkId)->orWhereNull('country_id');
                }),
            );
    }

    /**
     * @return Collection<int, FuelCompany>
     */
    public static function activeCompanies(): Collection
    {
        return FuelCompany::query()
            ->where('is_active', true)
            ->whereNotIn('slug', ['pakwheels'])
            ->orderBy('priority')
            ->get(['id', 'name', 'short_name', 'slug']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function priceRowsFor(string $fuelSlug, ?string $citySlug = null, ?string $companySlug = null): array
    {
        $query = FuelPrice::query()
            ->with([
                'company:id,name,short_name,slug',
                'city:id,name,slug',
                'fuelType:id,name,slug,unit',
            ])
            ->whereHas('fuelType', fn ($q) => $q->where('slug', $fuelSlug)->where('is_active', true))
            ->whereHas('company', fn ($q) => $q->where('is_active', true));

        if ($citySlug) {
            $query->where(function ($q) use ($citySlug) {
                $q->whereHas('city', fn ($c) => $c->where('slug', $citySlug))
                    ->orWhereNull('city_id');
            });
        }

        if ($companySlug) {
            $query->whereHas('company', fn ($c) => $c->where('slug', $companySlug));
        }

        return $query
            ->orderByDesc('effective_date')
            ->limit(40)
            ->get()
            ->map(static function (FuelPrice $row) {
                return [
                    'company' => $row->company?->short_name ?: $row->company?->name,
                    'company_slug' => $row->company?->slug,
                    'city' => $row->city?->name ?? 'National',
                    'city_slug' => $row->city?->slug,
                    'fuel' => $row->fuelType?->name,
                    'price' => (float) $row->price,
                    'unit' => $row->unit ?: ($row->fuelType?->unit ?: 'Rs/L'),
                    'effective_date' => optional($row->effective_date)->toDateString(),
                ];
            })
            ->unique(fn ($r) => ($r['company_slug'] ?? '').'|'.($r['city_slug'] ?? 'national'))
            ->values()
            ->all();
    }
}

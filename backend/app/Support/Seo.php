<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Seo
{
    public static function siteUrl(): string
    {
        $configured = rtrim((string) config('seo.site_url', config('app.url')), '/');
        if ($configured !== '' && ! PublicAppUrl::isLocal($configured)) {
            return $configured;
        }

        return PublicAppUrl::base();
    }

    public static function siteName(): string
    {
        return (string) config('seo.site_name', config('app.name', 'Fuel Prices PK'));
    }

    public static function locale(): string
    {
        return (string) config('seo.locale', 'en_GB');
    }

    public static function hreflang(): string
    {
        return str_replace('_', '-', self::locale());
    }

    public static function organizationId(): string
    {
        return self::siteUrl().'/#organization';
    }

    public static function websiteId(): string
    {
        return self::siteUrl().'/#website';
    }

    public static function defaultDescription(): string
    {
        return (string) config('seo.default_description', '');
    }

    public static function absoluteUrl(string $path = '/'): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if ($path === '' || $path === '/') {
            return self::siteUrl().'/';
        }

        return self::siteUrl().'/'.ltrim($path, '/');
    }

    /**
     * @return array{path:string,width:int,height:int,alt:string,type:string,url:string}
     */
    public static function ogImage(): array
    {
        $image = config('seo.og_image', []);
        $path = (string) ($image['path'] ?? '/og-image.jpg');

        return [
            'path' => $path,
            'width' => (int) ($image['width'] ?? 1200),
            'height' => (int) ($image['height'] ?? 630),
            'alt' => (string) ($image['alt'] ?? self::siteName()),
            'type' => (string) ($image['type'] ?? 'image/png'),
            'url' => self::absoluteUrl($path),
        ];
    }

    public static function pathKey(?string $path): string
    {
        $path = trim((string) $path, '/');

        return $path === '' ? 'home' : $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function pageConfig(string $pathKey): ?array
    {
        $pages = config('seo.pages', []);

        return is_array($pages[$pathKey] ?? null) ? $pages[$pathKey] : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forPath(string $path): array
    {
        $pathKey = self::pathKey($path);

        if ($landing = SeoLandings::seoBag($pathKey)) {
            return $landing;
        }

        $page = self::pageConfig($pathKey) ?? [];
        $canonicalPath = $pathKey === 'home' ? '/' : '/'.$pathKey;
        $og = self::ogImage();

        $title = (string) ($page['title'] ?? self::siteName());
        $description = (string) ($page['description'] ?? self::defaultDescription());
        $keywords = (string) ($page['keywords'] ?? config('seo.default_keywords', ''));
        $robots = (string) ($page['robots'] ?? 'index, follow');

        return [
            'path_key' => $pathKey,
            'title' => $title,
            'h1' => self::h1ForPage($page),
            'description' => $description,
            'keywords' => $keywords,
            'robots' => $robots,
            'canonical' => self::absoluteUrl($canonicalPath),
            'site_name' => self::siteName(),
            'site_url' => self::siteUrl(),
            'og' => [
                'type' => 'website',
                'title' => $title,
                'description' => $description,
                'url' => self::absoluteUrl($canonicalPath),
                'site_name' => self::siteName(),
                'locale' => self::locale(),
                'image' => $og,
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title,
                'description' => $description,
                'image' => $og['url'],
            ],
            'json_ld' => self::jsonLdForPage($pathKey, $page),
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<array<string, mixed>>
     */
    public static function jsonLdForPage(string $pathKey, array $page): array
    {
        if (($page['json_ld'] ?? null) === 'home') {
            return self::homeJsonLd();
        }

        return self::standardPageJsonLd($pathKey, $page);
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<array<string, mixed>>
     */
    public static function standardPageJsonLd(string $pathKey, array $page): array
    {
        $canonicalPath = $pathKey === 'home' ? '/' : '/'.$pathKey;
        $canonical = self::absoluteUrl($canonicalPath);
        $title = (string) ($page['title'] ?? self::siteName());
        $description = (string) ($page['description'] ?? self::defaultDescription());

        $blocks = [
            self::webPageBlock($pathKey, $canonical, $title, $description),
        ];

        if ($pathKey !== 'home') {
            $blocks[] = self::breadcrumbBlock($pathKey, $page, $canonical);
        }

        $faqEntities = [];
        foreach ($page['faq'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($faqEntities !== []) {
            $blocks[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        return $blocks;
    }

    /**
     * @return array<string, mixed>
     */
    public static function webPageBlock(string $pathKey, string $canonical, string $title, string $description): array
    {
        $block = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $canonical.'#webpage',
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => self::websiteId()],
            'about' => ['@id' => self::organizationId()],
            'inLanguage' => self::locale(),
        ];

        if ($pathKey !== 'home') {
            $block['breadcrumb'] = ['@id' => $canonical.'#breadcrumb'];
        }

        return $block;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, mixed>
     */
    public static function breadcrumbBlock(string $pathKey, array $page, string $canonical): array
    {
        $label = (string) ($page['breadcrumb'] ?? $page['h1'] ?? $page['title'] ?? $pathKey);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            '@id' => $canonical.'#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => self::siteUrl().'/',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $label,
                    'item' => $canonical,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public static function h1ForPage(array $page): string
    {
        if (! empty($page['h1'])) {
            return (string) $page['h1'];
        }

        if (! empty($page['breadcrumb'])) {
            return (string) $page['breadcrumb'];
        }

        $title = trim((string) ($page['title'] ?? self::siteName()));
        $siteName = preg_quote(self::siteName(), '/');
        $stripped = preg_replace('/\s*[|]\s*(?:'.$siteName.')\s*$/iu', '', $title);
        $stripped = preg_replace('/^(?:'.$siteName.')\s*[|]\s*/iu', '', (string) $stripped);
        $stripped = trim((string) $stripped);

        return $stripped !== '' ? $stripped : $title;
    }

    public static function forRequest(Request $request): array
    {
        $pathKey = self::pathKey($request->path());

        if (str_starts_with($pathKey, 'admin')) {
            return [
                'title' => self::siteName().' Admin',
                'h1' => 'Admin',
                'description' => '',
                'robots' => 'noindex, nofollow',
                'canonical' => self::absoluteUrl('/'.$request->path()),
                'site_name' => self::siteName(),
                'site_url' => self::siteUrl(),
                'og' => ['image' => self::ogImage()],
                'twitter' => ['card' => 'summary_large_image'],
                'json_ld' => [],
                'path_key' => $pathKey,
            ];
        }

        if (self::pageConfig($pathKey) || SeoLandings::matches($pathKey)) {
            return self::forPath($request->path());
        }

        return [
            'title' => self::siteName(),
            'h1' => self::siteName(),
            'description' => self::defaultDescription(),
            'robots' => 'noindex, nofollow',
            'canonical' => self::absoluteUrl('/'.$request->path()),
            'site_name' => self::siteName(),
            'site_url' => self::siteUrl(),
            'og' => ['image' => self::ogImage()],
            'twitter' => ['card' => 'summary_large_image'],
            'json_ld' => [],
            'path_key' => $pathKey,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function homeJsonLd(): array
    {
        $siteUrl = self::siteUrl();
        $siteName = self::siteName();
        $description = self::defaultDescription();
        $logo = self::absoluteUrl((string) config('seo.logo_path', '/icons/icon-512.png'));
        $support = (string) config('mail.from.address', 'support@topgradesoftware.com');

        $organization = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => self::organizationId(),
            'name' => 'TopGrade Software',
            'url' => 'https://topgradesoftware.com',
            'logo' => $logo,
            'description' => $description,
            'areaServed' => 'PK',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'email' => $support,
                'availableLanguage' => ['English', 'Urdu'],
            ],
        ];

        $sameAs = array_values(array_filter(config('seo.social', []), fn ($url) => is_string($url) && $url !== ''));
        if ($sameAs !== []) {
            $organization['sameAs'] = $sameAs;
        }

        $website = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => self::websiteId(),
            'name' => $siteName,
            'url' => $siteUrl,
            'description' => $description,
            'publisher' => ['@id' => self::organizationId()],
            'inLanguage' => self::locale(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => self::absoluteUrl('/prices').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $software = [
            '@context' => 'https://schema.org',
            '@type' => 'WebApplication',
            'name' => $siteName,
            'url' => $siteUrl,
            'applicationCategory' => 'UtilitiesApplication',
            'operatingSystem' => 'Web, Android',
            'browserRequirements' => 'Requires JavaScript',
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'PKR',
            ],
            'description' => $description,
            'inLanguage' => self::locale(),
        ];

        $faqEntities = [];
        foreach (config('seo.home_faq', []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        $homeTitle = (string) config('seo.pages.home.title', $siteName);
        $blocks = [
            $organization,
            $website,
            $software,
            self::webPageBlock('home', $siteUrl.'/', $homeTitle, $description),
        ];

        if ($faqEntities !== []) {
            $blocks[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        return $blocks;
    }

    /**
     * @return list<array{loc:string,lastmod:string,changefreq:string,priority:float}>
     */
    public static function sitemapEntries(): array
    {
        $now = now()->toAtomString();
        $priceMod = SeoLandings::pricesLastmod();
        $entries = [];
        $seen = [];

        $rows = array_merge(config('seo.sitemap', []), SeoLandings::sitemapRows());

        if (class_exists(\App\Models\Article::class) && \Illuminate\Support\Facades\Schema::hasTable('articles')) {
            foreach (\App\Models\Article::query()->published()->latest('published_at')->get(['slug', 'updated_at', 'published_at']) as $article) {
                $rows[] = [
                    'path' => 'guides/'.$article->slug,
                    'priority' => 0.8,
                    'changefreq' => 'weekly',
                    'lastmod' => optional($article->updated_at ?? $article->published_at)?->toAtomString(),
                ];
            }
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $path = (string) ($row['path'] ?? '');
            $loc = $path === '' ? self::siteUrl().'/' : self::absoluteUrl($path);
            if (isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;

            $isPricePage = $path === '' || $path === 'prices'
                || str_contains($path, 'price')
                || str_starts_with($path, 'omc/')
                || str_starts_with($path, 'fuel/');

            $entries[] = [
                'loc' => $loc,
                'lastmod' => (string) ($row['lastmod'] ?? ($isPricePage ? $priceMod : $now)),
                'changefreq' => (string) ($row['changefreq'] ?? 'monthly'),
                'priority' => (float) ($row['priority'] ?? 0.5),
            ];
        }

        return $entries;
    }

    /** Sitemap index (served at /sitemap.xml). */
    public static function sitemapXml(): string
    {
        $now = now()->toAtomString();
        $children = [
            self::absoluteUrl('/sitemap-pages.xml'),
            self::absoluteUrl('/sitemap-images.xml'),
            self::absoluteUrl('/sitemap-news.xml'),
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($children as $loc) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</loc>\n";
            $xml .= '    <lastmod>'.e($now)."</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }

        return $xml."</sitemapindex>\n";
    }

    /** Page URL catalog (canonical city/hub/calculator URLs). */
    public static function pagesSitemapXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach (self::sitemapEntries() as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8')."</loc>\n";
            $xml .= '    <lastmod>'.e($entry['lastmod'])."</lastmod>\n";
            $xml .= '    <changefreq>'.e($entry['changefreq'])."</changefreq>\n";
            $xml .= '    <priority>'.number_format($entry['priority'], 2, '.', '')."</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml."</urlset>\n";
    }

    /** Image sitemap for OG / brand assets. */
    public static function imageSitemapXml(): string
    {
        $og = self::ogImage();
        $pageUrl = self::siteUrl().'/';
        $images = [
            [
                'loc' => $og['url'],
                'title' => $og['alt'],
                'caption' => self::defaultDescription(),
            ],
            [
                'loc' => self::absoluteUrl((string) config('seo.logo_path', '/icons/icon-512.png')),
                'title' => self::siteName().' logo',
                'caption' => self::siteName(),
            ],
            [
                'loc' => self::absoluteUrl('/icons/icon-192.png'),
                'title' => self::siteName().' app icon',
                'caption' => self::siteName(),
            ],
            [
                'loc' => self::absoluteUrl('/apple-touch-icon.png'),
                'title' => self::siteName().' apple touch icon',
                'caption' => self::siteName(),
            ],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
        $xml .= "  <url>\n";
        $xml .= '    <loc>'.htmlspecialchars($pageUrl, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</loc>\n";
        foreach ($images as $image) {
            $xml .= "    <image:image>\n";
            $xml .= '      <image:loc>'.htmlspecialchars($image['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8')."</image:loc>\n";
            $xml .= '      <image:title>'.htmlspecialchars($image['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8')."</image:title>\n";
            $xml .= '      <image:caption>'.htmlspecialchars($image['caption'], ENT_XML1 | ENT_COMPAT, 'UTF-8')."</image:caption>\n";
            $xml .= "    </image:image>\n";
        }
        $xml .= "  </url>\n";

        return $xml."</urlset>\n";
    }

    /** Google News sitemap from published articles (last 2 days preferred by News). */
    public static function newsSitemapXml(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            .' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">'."\n";

        if (class_exists(\App\Models\Article::class) && \Illuminate\Support\Facades\Schema::hasTable('articles')) {
            $articles = \App\Models\Article::query()
                ->published()
                ->where('published_at', '>=', now()->subDays(3))
                ->latest('published_at')
                ->take(100)
                ->get();

            foreach ($articles as $article) {
                $loc = self::absoluteUrl($article->urlPath());
                $xml .= "  <url>\n";
                $xml .= '    <loc>'.htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</loc>\n";
                $xml .= "    <news:news>\n";
                $xml .= "      <news:publication>\n";
                $xml .= '        <news:name>'.htmlspecialchars(self::siteName(), ENT_XML1 | ENT_COMPAT, 'UTF-8')."</news:name>\n";
                $xml .= "        <news:language>en</news:language>\n";
                $xml .= "      </news:publication>\n";
                $xml .= '      <news:publication_date>'.e(optional($article->published_at)?->toAtomString() ?? '')."</news:publication_date>\n";
                $xml .= '      <news:title>'.htmlspecialchars($article->title, ENT_XML1 | ENT_COMPAT, 'UTF-8')."</news:title>\n";
                $xml .= "    </news:news>\n";
                $xml .= "  </url>\n";
            }
        }

        return $xml."</urlset>\n";
    }

    public static function robotsText(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Allow: /petrol-price',
            'Allow: /diesel-price',
            'Allow: /lpg-price',
            'Allow: /fuel-calculator',
            'Allow: /fuel-cost-calculator',
            'Allow: /trip-cost-calculator',
            'Allow: /mileage-calculator',
            'Allow: /monthly-fuel-budget-calculator',
            'Allow: /price-history',
            'Allow: /faq',
            'Allow: /prices',
            'Allow: /guides',
            'Allow: /about',
            'Allow: /contact',
            'Allow: /privacy',
            'Allow: /terms',
            'Allow: /disclaimer',
            'Allow: /editorial-policy',
        ];

        foreach (config('seo.robots_allow_extra', []) as $path) {
            $lines[] = 'Allow: '.$path;
        }

        foreach (config('seo.robots_disallow', []) as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = 'Sitemap: '.self::absoluteUrl('/sitemap.xml');

        return implode("\n", $lines)."\n";
    }

    public static function indexNowKey(): string
    {
        return trim((string) config('seo.indexnow_key', ''));
    }

    /**
     * @return list<string>
     */
    public static function indexNowUrls(): array
    {
        // Sitemap is PK-focused (~150 URLs) — submit the full set to IndexNow.
        $urls = array_map(
            static fn (array $entry): string => (string) ($entry['loc'] ?? ''),
            self::sitemapEntries(),
        );

        $urls = array_values(array_unique(array_filter($urls)));

        return $urls !== [] ? $urls : SeoLandings::priorityUrls();
    }

    public static function llmsText(): string
    {
        $site = self::siteName();
        $url = self::siteUrl();
        $description = self::defaultDescription();

        $lines = [
            '# '.$site,
            '',
            '> '.$description,
            '',
            '## Core pages',
            '- '.$url.'/',
            '- '.$url.'/fuel-calculator',
            '- '.$url.'/fuel-cost-calculator',
            '- '.$url.'/trip-cost-calculator',
            '- '.$url.'/mileage-calculator',
            '- '.$url.'/monthly-fuel-budget-calculator',
            '- '.$url.'/guides',
            '- '.$url.'/guides/rss.xml',
            '- '.$url.'/price-history',
            '- '.$url.'/faq',
            '- '.$url.'/prices',
            '- '.$url.'/petrol-price-today',
            '- '.$url.'/diesel-price-today',
            '- '.$url.'/lpg-price-today',
            '',
            '## Priority city landings',
        ];

        foreach (array_slice(SeoLandings::PRIORITY_CITIES, 0, 12) as $slug) {
            $lines[] = '- '.$url.'/petrol-price/'.$slug;
            $lines[] = '- '.$url.'/diesel-price/'.$slug;
        }

        $lines[] = '';
        $lines[] = '## High-intent guides';
        foreach ([
            'petrol-price-today-in-pakistan',
            'diesel-hsd-price-today-pakistan',
            'lpg-price-today-pakistan',
            'ogra-daily-fuel-pricing-explained',
            'pso-petrol-price-today',
            'petrol-price-in-karachi',
            'petrol-price-in-lahore',
            'petrol-price-in-islamabad',
            'fuel-prices-pakistan-urdu-guide',
            'how-much-40-litre-petrol-costs-pakistan',
        ] as $slug) {
            $lines[] = '- '.$url.'/guides/'.$slug;
        }

        $lines[] = '';
        $lines[] = '## Product';
        $lines[] = 'Fuel Prices PK shows live Pakistan petrol, diesel (HSD) and LPG prices by city, OMC compare, history charts, nearby pumps, and trip cost calculators. Full URL list: '.$url.'/sitemap-pages.xml';
        $lines[] = '';
        $lines[] = '## Contact';
        $lines[] = '- Website: '.$url.'/';
        $lines[] = '- Company: https://topgradesoftware.com';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    public static function inertiaShare(?Request $request = null): array
    {
        $pages = [];
        foreach (array_keys(config('seo.pages', [])) as $key) {
            $pages[$key] = self::forPath($key === 'home' ? '' : $key);
        }

        return [
            'site_name' => self::siteName(),
            'site_url' => self::siteUrl(),
            'locale' => self::locale(),
            'hreflang' => self::hreflang(),
            'default_description' => self::defaultDescription(),
            'default_keywords' => (string) config('seo.default_keywords', ''),
            'og_image' => self::ogImage(),
            'pages' => $pages,
            'home_json_ld' => self::homeJsonLd(),
            'home_faq' => array_values(array_filter(config('seo.home_faq', []), 'is_array')),
            'ga_measurement_id' => (string) config('seo.ga_measurement_id', ''),
            'theme_color' => (string) config('seo.theme_color', '#f2a63f'),
        ];
    }
}

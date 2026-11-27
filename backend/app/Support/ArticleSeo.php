<?php

namespace App\Support;

use App\Models\Article;

final class ArticleSeo
{
    /**
     * @return array<string, mixed>
     */
    public static function forArticle(Article $article): array
    {
        $path = $article->urlPath();
        $canonical = Seo::absoluteUrl($path);
        $title = $article->meta_title ?: ($article->title.' | Fuel Prices PK');
        $description = $article->meta_description ?: $article->excerpt;
        $og = Seo::ogImage();
        if (filled($article->og_image_path)) {
            $og = [
                'url' => Seo::absoluteUrl((string) $article->og_image_path),
                'width' => 1200,
                'height' => 630,
                'alt' => $article->title,
            ];
        }
        $published = optional($article->published_at)?->toIso8601String() ?? now()->toIso8601String();
        $modified = optional($article->updated_at)?->toIso8601String() ?? $published;
        $isNews = in_array($article->category, ['fuel-news', 'government', 'oil-market'], true);

        $jsonLd = [
            Seo::webPageBlock(ltrim($path, '/'), $canonical, $title, $description),
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                '@id' => $canonical.'#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => Seo::siteUrl().'/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Guides',
                        'item' => Seo::absoluteUrl('/guides'),
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $article->title,
                        'item' => $canonical,
                    ],
                ],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => $isNews ? 'NewsArticle' : 'Article',
                'headline' => $article->title,
                'description' => $description,
                'datePublished' => $published,
                'dateModified' => $modified,
                'author' => [
                    '@type' => 'Person',
                    'name' => $article->author_name,
                ],
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => 'TopGrade Software',
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => Seo::absoluteUrl((string) config('seo.logo_path', '/icons/icon-512.png')),
                    ],
                ],
                'mainEntityOfPage' => $canonical,
                'image' => [$og['url']],
                'articleSection' => $article->categoryLabel(),
                'keywords' => implode(', ', $article->tags ?? []),
                'inLanguage' => Seo::locale(),
            ],
        ];

        $faqEntities = [];
        foreach ($article->faq ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $faqEntities[] = [
                '@type' => 'Question',
                'name' => $q,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $a,
                ],
            ];
        }
        if ($faqEntities !== []) {
            $jsonLd[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqEntities,
            ];
        }

        return [
            'path_key' => ltrim($path, '/'),
            'title' => $title,
            'h1' => $article->title,
            'description' => $description,
            'keywords' => implode(', ', $article->tags ?? []),
            'robots' => 'index, follow',
            'canonical' => $canonical,
            'site_name' => Seo::siteName(),
            'site_url' => Seo::siteUrl(),
            'og' => [
                'type' => 'article',
                'title' => $title,
                'description' => $description,
                'url' => $canonical,
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
    }

    /**
     * @return array<string, mixed>
     */
    public static function forIndex(?string $category = null, ?string $q = null): array
    {
        $path = '/guides';
        $title = 'Fuel Guides & News Pakistan | Petrol, Diesel, LPG Tips';
        $description = 'Guides on petrol prices, diesel, LPG, HOBC, OGRA updates, OPEC, fuel-saving tips and calculators for Pakistan.';
        if ($category && isset(Article::CATEGORIES[$category])) {
            $label = Article::CATEGORIES[$category];
            $title = $label.' | Fuel Guides Pakistan';
            $description = 'Articles and guides about '.$label.' on Fuel Prices PK.';
            $path = '/guides?category='.$category;
        }
        if ($q) {
            $title = 'Search guides: '.$q.' | Fuel Prices PK';
        }

        $canonical = Seo::absoluteUrl('/guides');

        return [
            'path_key' => 'guides',
            'title' => $title,
            'h1' => 'Fuel guides & news',
            'description' => $description,
            'keywords' => 'fuel guides Pakistan, petrol price explained, diesel tips, LPG guide',
            'robots' => 'index, follow',
            'canonical' => $canonical,
            'site_name' => Seo::siteName(),
            'site_url' => Seo::siteUrl(),
            'og' => [
                'type' => 'website',
                'title' => $title,
                'description' => $description,
                'url' => $canonical,
                'site_name' => Seo::siteName(),
                'locale' => Seo::locale(),
                'image' => Seo::ogImage(),
            ],
            'twitter' => [
                'card' => 'summary_large_image',
                'title' => $title,
                'description' => $description,
                'image' => Seo::ogImage()['url'],
            ],
            'json_ld' => Seo::standardPageJsonLd('guides', [
                'title' => $title,
                'description' => $description,
                'breadcrumb' => 'Guides',
            ]),
        ];
    }
}

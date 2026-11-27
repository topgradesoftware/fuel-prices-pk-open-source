<?php

namespace App\Support;

final class PublicAppUrl
{
    public const PRODUCTION = 'https://oilprice.topgradesoftware.com';

    public static function base(): string
    {
        foreach ([
            config('seo.site_url'),
            env('SEO_SITE_URL'),
            config('app.url'),
            env('APP_URL'),
        ] as $candidate) {
            $url = rtrim(trim((string) $candidate), '/');
            if ($url !== '' && ! self::isLocal($url)) {
                return $url;
            }
        }

        return self::PRODUCTION;
    }

    public static function isLocal(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: $url));

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test')
            || str_starts_with($host, '192.168.')
            || str_starts_with($host, '10.');
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Seo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SeoNotifyCommand extends Command
{
    protected $signature = 'seo:notify {--batch=100 : URLs per IndexNow request}';

    protected $description = 'Ping IndexNow (Bing) with sitemap URLs and notify search engines of the sitemap';

    public function handle(): int
    {
        $key = Seo::indexNowKey();
        $siteUrl = Seo::siteUrl();
        $ok = true;

        // Always ping sitemap endpoints (no API key required).
        $sitemap = $siteUrl.'/sitemap.xml';
        $pings = [
            'Bing sitemap' => 'https://www.bing.com/indexnow?url='.rawurlencode($sitemap).'&key='.rawurlencode($key ?: 'none'),
            'Google sitemap ping' => 'https://www.google.com/ping?sitemap='.rawurlencode($sitemap),
        ];

        // Prefer proper IndexNow JSON when key is configured.
        if ($key !== '') {
            $keyFile = public_path($key.'.txt');
            if (! is_file($keyFile)) {
                file_put_contents($keyFile, $key);
                $this->info('Wrote '.$keyFile);
            }

            $urls = Seo::indexNowUrls();
            if ($urls === []) {
                $this->warn('No sitemap URLs to submit.');
            } else {
                $batchSize = max(10, min(10000, (int) $this->option('batch')));
                $chunks = array_chunk($urls, $batchSize);
                $this->info(sprintf(
                    'Submitting %d URLs to IndexNow in %d batch(es) for %s',
                    count($urls),
                    count($chunks),
                    $siteUrl,
                ));

                $host = (string) parse_url($siteUrl, PHP_URL_HOST);
                $endpoints = [
                    'IndexNow' => 'https://api.indexnow.org/indexnow',
                    'Bing' => 'https://www.bing.com/indexnow',
                ];

                foreach ($chunks as $i => $chunk) {
                    $payload = [
                        'host' => $host,
                        'key' => $key,
                        'keyLocation' => $siteUrl.'/'.$key.'.txt',
                        'urlList' => array_values($chunk),
                    ];

                    foreach ($endpoints as $label => $endpoint) {
                        try {
                            $response = Http::timeout(30)->asJson()->post($endpoint, $payload);
                            if ($response->successful() || in_array($response->status(), [200, 202], true)) {
                                $this->info(sprintf(
                                    '%s batch %d/%d: %s OK (%d urls)',
                                    $label,
                                    $i + 1,
                                    count($chunks),
                                    $response->status(),
                                    count($chunk),
                                ));
                            } else {
                                $ok = false;
                                $this->error("{$label} batch ".($i + 1).": {$response->status()} ".$response->body());
                            }
                        } catch (\Throwable $e) {
                            $ok = false;
                            $this->error("{$label} batch ".($i + 1).': '.$e->getMessage());
                        }
                    }
                }
            }
        } else {
            $this->warn('SEO_INDEXNOW_KEY is not set — skipping IndexNow JSON submit.');
            unset($pings['Bing sitemap']);
        }

        foreach ($pings as $label => $endpoint) {
            if ($label === 'Bing sitemap' && $key === '') {
                continue;
            }
            if ($label === 'Bing sitemap') {
                // Already covered by JSON IndexNow posts above.
                continue;
            }
            try {
                $response = Http::timeout(15)->get($endpoint);
                $this->info("{$label}: {$response->status()}");
            } catch (\Throwable $e) {
                // Google deprecated ping; treat network/4xx as soft warn.
                $this->warn("{$label}: ".$e->getMessage());
            }
        }

        $this->info('Sitemap: '.$sitemap);
        $this->line('Submit this sitemap in Google Search Console if not already added.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}

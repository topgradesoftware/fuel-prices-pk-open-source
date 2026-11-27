<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;
use App\Support\SafeError;
use App\Support\Seo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IndexNowAdapter extends AbstractDistributionAdapter
{
    public function channel(): string
    {
        return 'indexnow';
    }

    public function distribute(Article $article): array
    {
        $key = Seo::indexNowKey();
        if ($key === '') {
            return $this->skipped('indexnow_key_missing');
        }

        $url = $this->publicUrl($article);
        $siteUrl = Seo::siteUrl();
        $host = (string) parse_url($siteUrl, PHP_URL_HOST);
        $keyFile = public_path($key.'.txt');
        if (! is_file($keyFile)) {
            file_put_contents($keyFile, $key);
        }

        $payload = [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $siteUrl.'/'.$key.'.txt',
            'urlList' => [$url],
        ];

        try {
            $response = Http::timeout(20)->asJson()->post('https://api.indexnow.org/indexnow', $payload);
            if ($response->successful() || in_array($response->status(), [200, 202], true)) {
                return $this->sent(['http_status' => $response->status(), 'url' => $url]);
            }

            return [
                'status' => 'failed',
                'error' => 'HTTP '.$response->status(),
                'payload' => ['body' => Str::limit($response->body(), 300)],
            ];
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.distribution.indexnow failed', ['article_id' => $article->id]);

            return ['status' => 'failed', 'error' => SafeError::storedMessage('IndexNow failed. See server logs for details.')];
        }
    }
}

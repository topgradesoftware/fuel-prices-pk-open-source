<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;
use App\Support\SafeError;
use Illuminate\Support\Facades\Http;

class WhatsAppAdapter extends AbstractDistributionAdapter
{
    public function channel(): string
    {
        return 'whatsapp';
    }

    public function distribute(Article $article): array
    {
        $webhook = (string) config('content.distribution.whatsapp.webhook_url');
        if ($webhook === '') {
            return $this->skipped('whatsapp_webhook_missing');
        }

        try {
            $response = Http::timeout(20)->asJson()->post($webhook, [
                'title' => $article->title,
                'url' => $this->publicUrl($article),
                'excerpt' => $article->excerpt,
                'og_image' => $article->og_image_path,
            ]);
            if ($response->successful()) {
                return $this->sent(['http_status' => $response->status()]);
            }

            return ['status' => 'failed', 'error' => 'HTTP '.$response->status()];
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.distribution.whatsapp failed', ['article_id' => $article->id]);

            return ['status' => 'failed', 'error' => SafeError::storedMessage('WhatsApp delivery failed. See server logs for details.')];
        }
    }
}

<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;
use App\Support\SafeError;
use Illuminate\Support\Facades\Http;

class TelegramAdapter extends AbstractDistributionAdapter
{
    public function channel(): string
    {
        return 'telegram';
    }

    public function distribute(Article $article): array
    {
        $token = (string) config('content.distribution.telegram.bot_token');
        $chatId = (string) config('content.distribution.telegram.chat_id');
        if ($token === '' || $chatId === '') {
            return $this->skipped('telegram_credentials_missing');
        }

        $text = $article->title."\n\n".$this->publicUrl($article);
        try {
            $response = Http::timeout(20)->asJson()->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                ['chat_id' => $chatId, 'text' => $text, 'disable_web_page_preview' => false],
            );
            if ($response->successful()) {
                return $this->sent(['message_id' => data_get($response->json(), 'result.message_id')]);
            }

            return ['status' => 'failed', 'error' => 'HTTP '.$response->status()];
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.distribution.telegram failed', ['article_id' => $article->id]);

            return ['status' => 'failed', 'error' => SafeError::storedMessage('Telegram delivery failed. See server logs for details.')];
        }
    }
}

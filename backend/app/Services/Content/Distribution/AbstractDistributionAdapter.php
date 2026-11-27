<?php

namespace App\Services\Content\Distribution;

use App\Contracts\DistributionAdapter;
use App\Models\Article;

abstract class AbstractDistributionAdapter implements DistributionAdapter
{
    abstract public function channel(): string;

    public function enabled(): bool
    {
        return (bool) config('content.distribution.channels.'.$this->channel().'.enabled', false);
    }

    /**
     * @return array{status: string, payload?: array<string, mixed>, error?: string}
     */
    protected function skipped(string $reason): array
    {
        return ['status' => 'skipped', 'payload' => ['reason' => $reason]];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, payload?: array<string, mixed>, error?: string}
     */
    protected function sent(array $payload = []): array
    {
        return ['status' => 'sent', 'payload' => $payload];
    }

    protected function publicUrl(Article $article): string
    {
        return rtrim((string) config('seo.site_url', config('app.url')), '/').$article->urlPath();
    }
}

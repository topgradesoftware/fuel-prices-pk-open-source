<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;

/**
 * Pluggable stub for social channels — queues as skipped until credentials exist.
 */
class StubSocialAdapter extends AbstractDistributionAdapter
{
    public function __construct(
        private readonly string $channelName,
        private readonly ?string $credentialHint = null,
    ) {}

    public function channel(): string
    {
        return $this->channelName;
    }

    public function distribute(Article $article): array
    {
        return $this->skipped($this->credentialHint ?? 'credentials_not_configured');
    }
}

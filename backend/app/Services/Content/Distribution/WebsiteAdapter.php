<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;

class WebsiteAdapter extends AbstractDistributionAdapter
{
    public function channel(): string
    {
        return 'website';
    }

    public function distribute(Article $article): array
    {
        if (! $article->isPublic()) {
            return $this->skipped('not_public');
        }

        return $this->sent([
            'url' => $this->publicUrl($article),
            'note' => 'Live on /guides once status=published',
        ]);
    }
}

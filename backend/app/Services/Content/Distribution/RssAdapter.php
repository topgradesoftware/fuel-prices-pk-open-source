<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;

class RssAdapter extends AbstractDistributionAdapter
{
    public function channel(): string
    {
        return 'rss';
    }

    public function distribute(Article $article): array
    {
        // Guides RSS is generated dynamically from published articles.
        return $this->sent(['feed' => '/guides/rss.xml', 'url' => $this->publicUrl($article)]);
    }
}

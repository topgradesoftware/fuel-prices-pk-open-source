<?php

namespace App\Services\Content\Distribution;

use App\Models\Article;

class NewsSitemapAdapter extends AbstractDistributionAdapter
{
    public function channel(): string
    {
        return 'news_sitemap';
    }

    public function distribute(Article $article): array
    {
        return $this->sent(['sitemap' => '/sitemap-news.xml', 'url' => $this->publicUrl($article)]);
    }
}

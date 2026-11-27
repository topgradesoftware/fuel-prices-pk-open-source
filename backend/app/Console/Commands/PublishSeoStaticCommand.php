<?php

namespace App\Console\Commands;

use App\Support\Seo;
use Illuminate\Console\Command;

class PublishSeoStaticCommand extends Command
{
    protected $signature = 'seo:publish-static {--url= : Override public site URL for this run}';

    protected $description = 'Write public sitemap index + child sitemaps, robots.txt and llms.txt';

    public function handle(): int
    {
        if ($url = $this->option('url')) {
            config(['seo.site_url' => rtrim((string) $url, '/')]);
        }

        $public = public_path();
        $entries = Seo::sitemapEntries();
        $count = count($entries);

        file_put_contents($public.'/sitemap.xml', Seo::sitemapXml());
        file_put_contents($public.'/sitemap-pages.xml', Seo::pagesSitemapXml());
        file_put_contents($public.'/sitemap-images.xml', Seo::imageSitemapXml());
        file_put_contents($public.'/sitemap-news.xml', Seo::newsSitemapXml());
        file_put_contents($public.'/robots.txt', Seo::robotsText());
        file_put_contents($public.'/llms.txt', Seo::llmsText());

        $key = Seo::indexNowKey();
        if ($key !== '') {
            file_put_contents($public.'/'.$key.'.txt', $key);
            $this->info('IndexNow key file: /'.$key.'.txt');
        }

        $this->info('Published sitemap index + pages/images/news, robots.txt, llms.txt → '.$public);
        $this->line('Sitemap index: '.Seo::absoluteUrl('/sitemap.xml'));
        $this->line('Page URLs in sitemap-pages.xml: '.$count);

        if ($count < 20) {
            $this->warn('Sitemap looks thin (<20 URLs). Ensure cities/companies are seeded before publishing.');
        }

        return self::SUCCESS;
    }
}

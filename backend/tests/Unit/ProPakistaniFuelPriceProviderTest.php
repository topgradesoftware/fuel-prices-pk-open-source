<?php

namespace Tests\Unit;

use App\Services\Fuel\ProPakistaniFuelPriceProvider;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ProPakistaniFuelPriceProviderTest extends TestCase
{
    private function feedWith(string $content, ?Carbon $published = null): string
    {
        $pubDate = ($published ?? Carbon::now()->subHours(2))->toRssString();

        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0">
        <channel>
            <title>ProPakistani Business</title>
            <item>
                <title>Government Announces New Fuel Prices</title>
                <link>https://propakistani.pk/2026/07/20/fuel-prices/</link>
                <pubDate>{$pubDate}</pubDate>
                <description>{$content}</description>
            </item>
        </channel>
        </rss>
        XML;
    }

    public function test_parses_fresh_price_announcement(): void
    {
        $content = 'Starting tomorrow, petrol will go up to Rs. 315.80 per litre '
            .'while high-speed diesel (HSD) has been revised to Rs 360.06 per litre '
            .'across the country, the finance ministry announced.';

        // Published at 20:00 PKT — the new price applies from the next day.
        $published = Carbon::parse('2026-07-19 20:00:00', 'Asia/Karachi');
        Carbon::setTestNow($published->copy()->addHours(3));

        try {
            $provider = new ProPakistaniFuelPriceProvider;
            $rows = $provider->parseFeed($this->feedWith($content, $published));

            $byProduct = [];
            foreach ($rows as $row) {
                $byProduct[$row['product']] = $row;
            }

            $this->assertSame(315.80, $byProduct['petrol']['price_pkr']);
            $this->assertSame(360.06, $byProduct['hsd']['price_pkr']);
            $this->assertSame('2026-07-19', $byProduct['petrol']['effective_date']);
            $this->assertSame('pso', $byProduct['petrol']['source']);
            $this->assertSame('propakistani', $byProduct['petrol']['provider']);
            $this->assertSame('https://propakistani.pk/2026/07/20/fuel-prices/', $byProduct['petrol']['source_url']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_morning_announcement_is_effective_same_day(): void
    {
        $content = 'The petrol price has been increased to Rs 317.50 per litre with immediate effect, '
            .'while diesel was revised to Rs 355.00 per litre.';
        $published = Carbon::parse('2026-07-20 09:00:00', 'Asia/Karachi');
        Carbon::setTestNow($published->copy()->addHour());

        try {
            $provider = new ProPakistaniFuelPriceProvider;
            $rows = $provider->parseFeed($this->feedWith($content, $published));

            $this->assertCount(2, $rows);
            $this->assertSame('2026-07-20', $rows[0]['effective_date']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_ignores_stale_articles(): void
    {
        $content = 'Last month petrol dropped to Rs 310.00 per litre.';
        $stale = Carbon::now()->subDays(4);

        $provider = new ProPakistaniFuelPriceProvider;
        $rows = $provider->parseFeed($this->feedWith($content, $stale));

        $this->assertSame([], $rows);
    }

    public function test_ignores_implausible_prices(): void
    {
        $content = 'Analysts joked petrol could fall to Rs 10.99 per litre someday.';

        $provider = new ProPakistaniFuelPriceProvider;
        $rows = $provider->parseFeed($this->feedWith($content));

        $this->assertSame([], $rows);
    }

    public function test_ignores_articles_without_prices(): void
    {
        $content = 'The government discussed energy policy reforms in a cabinet meeting today.';

        $provider = new ProPakistaniFuelPriceProvider;
        $rows = $provider->parseFeed($this->feedWith($content));

        $this->assertSame([], $rows);
    }

    public function test_does_not_let_diesel_steal_petrol_price_across_clauses(): void
    {
        $content = 'The prices of petrol and high-speed diesel (HSD) were increased on Tuesday night. '
            .'The price of petrol was increased by Rs. 4.93/litre to Rs. 320.73 per litre, '
            .'while diesel rose by Rs. 7.15/litre to sell for Rs. 367.21 per litre.';
        $published = Carbon::parse('2026-07-21 22:52:00', 'Asia/Karachi');
        Carbon::setTestNow($published->copy()->addHours(2));

        try {
            $provider = new ProPakistaniFuelPriceProvider;
            $rows = $provider->parseFeed($this->feedWith($content, $published));

            $byProduct = [];
            foreach ($rows as $row) {
                $byProduct[$row['product']] = $row;
            }

            $this->assertSame(320.73, $byProduct['petrol']['price_pkr']);
            $this->assertSame(367.21, $byProduct['hsd']['price_pkr']);
            $this->assertSame('2026-07-21', $byProduct['petrol']['effective_date']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_parses_optional_kerosene_from_same_announcement(): void
    {
        $content = 'The price of petrol was increased by Rs. 6.39/litre to Rs. 327.12 per litre, '
            .'while diesel rose by Rs. 7.83/litre to sell for Rs. 375.04 per litre. '
            .'The government has also increased the price of kerosene oil by Rs7.80 per litre, '
            .'setting the new rate at Rs297.17 per litre.';
        $published = Carbon::parse('2026-07-22 23:00:00', 'Asia/Karachi');
        Carbon::setTestNow($published->copy()->addHours(2));

        try {
            $provider = new ProPakistaniFuelPriceProvider;
            $rows = $provider->parseFeed($this->feedWith($content, $published));

            $byProduct = [];
            foreach ($rows as $row) {
                $byProduct[$row['product']] = $row;
            }

            $this->assertSame(327.12, $byProduct['petrol']['price_pkr']);
            $this->assertSame(375.04, $byProduct['hsd']['price_pkr']);
            $this->assertSame(297.17, $byProduct['kerosene']['price_pkr']);
            $this->assertSame('propakistani', $byProduct['kerosene']['provider']);
            $this->assertSame('2026-07-22', $byProduct['kerosene']['effective_date']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_allows_kerosene_only_enrichment_rows_via_api_posts_merge(): void
    {
        $published = Carbon::parse('2026-07-22 23:10:00', 'Asia/Karachi');
        Carbon::setTestNow($published->copy()->addHours(2));

        try {
            $provider = new ProPakistaniFuelPriceProvider;
            $rows = $provider->parseApiPosts([
                [
                    'date' => '2026-07-22T23:00:00',
                    'link' => 'https://propakistani.pk/petrol-hsd/',
                    'content' => [
                        'rendered' => '<p>The price of petrol was increased by Rs. 6.39/litre to Rs. 327.12 per litre, '
                            .'while diesel rose by Rs. 7.83/litre to sell for Rs. 375.04 per litre.</p>',
                    ],
                ],
                [
                    'date' => '2026-07-22T23:10:00',
                    'link' => 'https://en.dailypakistan.com.pk/sko/',
                    'content' => [
                        'rendered' => '<p>The government has also increased the price of kerosene oil by Rs7.80 per litre, '
                            .'setting the new rate at Rs297.17 per litre.</p>',
                    ],
                ],
            ]);

            $byProduct = [];
            foreach ($rows as $row) {
                $byProduct[$row['product']] = $row;
            }

            $this->assertSame(327.12, $byProduct['petrol']['price_pkr']);
            $this->assertSame(375.04, $byProduct['hsd']['price_pkr']);
            $this->assertSame(297.17, $byProduct['kerosene']['price_pkr']);
            $this->assertSame(
                'https://en.dailypakistan.com.pk/sko/',
                $byProduct['kerosene']['source_url'],
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_parses_wordpress_api_posts_when_feed_is_empty(): void
    {
        $published = Carbon::parse('2026-07-20 23:28:25', 'Asia/Karachi');
        Carbon::setTestNow($published->copy()->addHours(20));

        try {
            $posts = [[
                'date' => '2026-07-20T23:28:25',
                'date_gmt' => '2026-07-20T18:28:25',
                'link' => 'https://propakistani.pk/2026/07/20/petrol-price-reduced-diesel-increased-for-next-24-hours/',
                'content' => [
                    'rendered' => '<p>Petrol was decreased by Rs. 0.35/litre to Rs. 315.80 per litre, '
                        .'while diesel rose by Rs. 5.71/litre to sell for Rs. 360.06 per litre.</p>'
                        .'<p>Last week, Petrol was increased by Rs. 5.44/litre to Rs. 316.15 per litre, '
                        .'while diesel rose by Rs. 31.05/litre to sell for Rs. 354.35 per litre.</p>',
                ],
            ]];

            $provider = new ProPakistaniFuelPriceProvider;
            $rows = $provider->parseApiPosts($posts);

            $byProduct = [];
            foreach ($rows as $row) {
                $byProduct[$row['product']] = $row;
            }

            $this->assertSame(315.80, $byProduct['petrol']['price_pkr']);
            $this->assertSame(360.06, $byProduct['hsd']['price_pkr']);
            $this->assertSame('2026-07-20', $byProduct['petrol']['effective_date']);
            $this->assertSame(
                'https://propakistani.pk/2026/07/20/petrol-price-reduced-diesel-increased-for-next-24-hours/',
                $byProduct['petrol']['source_url'],
            );
        } finally {
            Carbon::setTestNow();
        }
    }
}

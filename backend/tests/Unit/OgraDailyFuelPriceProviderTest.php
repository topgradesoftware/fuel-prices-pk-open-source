<?php

namespace Tests\Unit;

use App\Services\Fuel\OgraDailyFuelPriceProvider;
use PHPUnit\Framework\TestCase;

class OgraDailyFuelPriceProviderTest extends TestCase
{
    public function test_parses_totals_from_formula_anchored_text(): void
    {
        $text = <<<'TXT'
        OIL & GAS REGULATORY AUTHORITY
        Price Publication Effective Dated 20 July 2026
        Price Effective 20 July 2026
        Component Petrol Diesel
        Ex-Refinery Price P 245.10 288.40
        Inland Freight Equalization Margin B 5.12 4.87
        Total Petrol Price P+A+B+C+D+E T={P+A+B+C+D+E} 315.80
        Total Diesel Price P+A+B+C+D+E T={P+A+B+C+D+E} 360.06
        Total Kerosene Price P+A+B+C+D+E T={P+A+B+C+D+E} 282.59
        Total Light Diesel Oil Price P+A+B+C+D+E T={P+A+B+C+D+E} 230.67
        TXT;

        $provider = new OgraDailyFuelPriceProvider;
        $rows = $provider->pricesFromText($text, null, 'https://ogra.org.pk/download/123');

        $this->assertCount(4, $rows);

        $byProduct = [];
        foreach ($rows as $row) {
            $byProduct[$row['product']] = $row;
        }

        $this->assertSame(315.80, $byProduct['petrol']['price_pkr']);
        $this->assertSame(360.06, $byProduct['hsd']['price_pkr']);
        $this->assertSame(282.59, $byProduct['kerosene']['price_pkr']);
        $this->assertSame(230.67, $byProduct['ldo']['price_pkr']);
        $this->assertSame('2026-07-20', $byProduct['petrol']['effective_date']);
        $this->assertSame('pso', $byProduct['petrol']['source']);
        $this->assertSame('ogra_daily', $byProduct['petrol']['provider']);
        $this->assertSame('ogra_daily', $byProduct['kerosene']['provider']);
        $this->assertNull($byProduct['petrol']['city']);
    }

    public function test_parses_sko_and_ldo_label_aliases(): void
    {
        $text = <<<'TXT'
        Price Effective 23 July 2026
        Total SKO Price T={X} 297.17
        Total LDO Price T={X} 240.40
        TXT;

        $provider = new OgraDailyFuelPriceProvider;
        $rows = $provider->pricesFromText($text, null, 'https://ogra.org.pk/download/127');

        $byProduct = [];
        foreach ($rows as $row) {
            $byProduct[$row['product']] = $row;
        }

        $this->assertSame(297.17, $byProduct['kerosene']['price_pkr']);
        $this->assertSame(240.40, $byProduct['ldo']['price_pkr']);
        $this->assertSame('2026-07-23', $byProduct['kerosene']['effective_date']);
    }

    public function test_falls_back_to_last_plausible_number_without_formula_cell(): void
    {
        // Padding mirrors the real PDF, where each product block is followed by
        // component breakdowns before the next "Total … Price" line appears.
        $padding = str_repeat("Component breakdown line with details and notes\n", 6);
        $text = <<<TXT
        Price Effective 21 July 2026
        Total Petrol Price 1.23 45.00 318.25
        {$padding}
        Total Diesel Price 0.50 12.00 362.10
        TXT;

        $provider = new OgraDailyFuelPriceProvider;
        $rows = $provider->pricesFromText($text, null, 'https://ogra.org.pk/download/124');

        $byProduct = [];
        foreach ($rows as $row) {
            $byProduct[$row['product']] = $row;
        }

        $this->assertSame(318.25, $byProduct['petrol']['price_pkr']);
        $this->assertSame(362.10, $byProduct['hsd']['price_pkr']);
        $this->assertSame('2026-07-21', $byProduct['petrol']['effective_date']);
    }

    public function test_uses_label_date_when_text_has_no_effective_date(): void
    {
        $text = 'Total Petrol Price T={X} 320.00';

        $provider = new OgraDailyFuelPriceProvider;
        $rows = $provider->pricesFromText($text, '2026-07-19', 'https://ogra.org.pk/download/125');

        $this->assertCount(1, $rows);
        $this->assertSame('2026-07-19', $rows[0]['effective_date']);
    }

    public function test_rejects_implausible_prices(): void
    {
        $text = <<<'TXT'
        Total Petrol Price T={X} 15.80
        Total Diesel Price T={X} 9999.99
        TXT;

        $provider = new OgraDailyFuelPriceProvider;
        $rows = $provider->pricesFromText($text, null, 'https://ogra.org.pk/download/126');

        $this->assertSame([], $rows);
    }
}

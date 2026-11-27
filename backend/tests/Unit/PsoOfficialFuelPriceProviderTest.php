<?php

namespace Tests\Unit;

use App\Services\Fuel\PsoOfficialFuelPriceProvider;
use PHPUnit\Framework\TestCase;

class PsoOfficialFuelPriceProviderTest extends TestCase
{
    public function test_parse_html_extracts_pso_retail_products(): void
    {
        $html = <<<'HTML'
        <table>
            <tr><td>PREMIER EURO 5</td><td>Rs.316.15/Ltr</td></tr>
            <tr><td>HI-CETANE DIESEL EURO 5</td><td>Rs.354.35/Ltr</td></tr>
            <tr><td>LDO</td><td>Rs.230.67/Ltr</td></tr>
            <tr><td>SKO</td><td>Rs.276.66/Ltr</td></tr>
            <tr><td>JP-1</td><td>Rs.291.55/Ltr</td></tr>
            <tr><td>E10 GASOLINE</td><td>--</td></tr>
            <tr><td>Octane Euro 5 (Bahawalpur)</td><td>340.00</td></tr>
            <tr><td>Octane Euro 5 (Gilgit)</td><td>356.00</td></tr>
        </table>
        HTML;

        $provider = new PsoOfficialFuelPriceProvider();
        $rows = $provider->parseHtml($html);

        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['product'].'|'.($row['city'] ?? 'national')] = $row;
        }

        // Daily-regulated petrol/HSD are owned by OGRA — PSO page often lags.
        // SKO/LDO remain as fallback when OGRA/news do not publish them.
        $this->assertArrayNotHasKey('petrol|national', $byKey);
        $this->assertArrayNotHasKey('hsd|national', $byKey);
        $this->assertSame(230.67, $byKey['ldo|national']['price_pkr']);
        $this->assertSame(276.66, $byKey['kerosene|national']['price_pkr']);
        $this->assertSame(291.55, $byKey['jp1|national']['price_pkr']);
        $this->assertSame(340.0, $byKey['octane_plus|Bahawalpur']['price_pkr']);
        $this->assertSame(356.0, $byKey['octane_plus|Gilgit']['price_pkr']);
        $this->assertArrayNotHasKey('e10|national', $byKey);
        $this->assertSame('pso_official', $byKey['ldo|national']['provider']);
        $this->assertSame('pso', $byKey['ldo|national']['source']);
    }
}

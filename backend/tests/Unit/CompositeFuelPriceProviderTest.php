<?php

namespace Tests\Unit;

use App\Contracts\FuelPriceProvider;
use App\Services\Fuel\CompositeFuelPriceProvider;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CompositeFuelPriceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_ogra_petrol_and_hsd_win_over_stale_pso(): void
    {
        $ogra = $this->provider('ogra_daily', [
            $this->row('petrol', 315.80, 'ogra_daily'),
            $this->row('hsd', 360.06, 'ogra_daily'),
            $this->row('kerosene', 297.17, 'ogra_daily'),
            $this->row('ldo', 240.40, 'ogra_daily'),
        ]);
        $pso = $this->provider('pso_official', [
            $this->row('petrol', 316.15, 'pso_official'),
            $this->row('hsd', 354.35, 'pso_official'),
            $this->row('kerosene', 289.37, 'pso_official'),
            $this->row('ldo', 230.67, 'pso_official'),
            $this->row('jp1', 291.55, 'pso_official'),
        ]);

        $merged = (new CompositeFuelPriceProvider([$ogra, $pso]))->latestPrices();
        $byProduct = [];
        foreach ($merged as $row) {
            $byProduct[$row['product']] = $row;
        }

        $this->assertSame(315.80, $byProduct['petrol']['price_pkr']);
        $this->assertSame('ogra_daily', $byProduct['petrol']['provider']);
        $this->assertSame(360.06, $byProduct['hsd']['price_pkr']);
        $this->assertSame('ogra_daily', $byProduct['hsd']['provider']);
        $this->assertSame(297.17, $byProduct['kerosene']['price_pkr']);
        $this->assertSame('ogra_daily', $byProduct['kerosene']['provider']);
        $this->assertSame(240.40, $byProduct['ldo']['price_pkr']);
        $this->assertSame('ogra_daily', $byProduct['ldo']['provider']);
        $this->assertSame(291.55, $byProduct['jp1']['price_pkr']);
        $this->assertSame('pso_official', $byProduct['jp1']['provider']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function provider(string $name, array $rows): FuelPriceProvider
    {
        return new class($name, $rows) implements FuelPriceProvider
        {
            /**
             * @param  list<array<string, mixed>>  $rows
             */
            public function __construct(
                private readonly string $name,
                private readonly array $rows,
            ) {}

            public function name(): string
            {
                return $this->name;
            }

            public function latestPrices(): array
            {
                return $this->rows;
            }

            public function history(string $product, ?string $source = null, int $days = 30): array
            {
                return [];
            }

            public function health(): array
            {
                return ['healthy' => true];
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $product, float $price, string $provider): array
    {
        return [
            'source' => 'pso',
            'provider' => $provider,
            'product' => $product,
            'price_pkr' => $price,
            'unit' => 'litre',
            'city' => null,
            'effective_date' => '2026-07-21',
        ];
    }
}

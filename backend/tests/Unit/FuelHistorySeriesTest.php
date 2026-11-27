<?php

namespace Tests\Unit;

use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelPriceHistory;
use App\Models\FuelType;
use App\Services\Fuel\FuelHistorySeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelHistorySeriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_pso_headline_not_average_of_all_omcs(): void
    {
        $pso = FuelCompany::query()->create([
            'name' => 'Pakistan State Oil',
            'short_name' => 'PSO',
            'slug' => 'pso',
            'is_active' => true,
            'priority' => 10,
        ]);
        $shell = FuelCompany::query()->create([
            'name' => 'Shell',
            'short_name' => 'Shell',
            'slug' => 'shell',
            'is_active' => true,
            'priority' => 20,
        ]);
        $hsd = FuelType::query()->create([
            'name' => 'HSD',
            'slug' => 'hsd',
            'category' => 'diesel',
            'unit' => 'Rs/L',
            'is_active' => true,
            'priority' => 20,
        ]);

        $psoPrice = FuelPrice::query()->create([
            'company_id' => $pso->id,
            'fuel_type_id' => $hsd->id,
            'price' => 367.21,
            'previous_price' => 360.06,
            'currency' => 'PKR',
            'unit' => 'Rs/L',
            'effective_date' => '2026-07-22',
            'source' => 'propakistani',
            'verified' => true,
        ]);
        $shellPrice = FuelPrice::query()->create([
            'company_id' => $shell->id,
            'fuel_type_id' => $hsd->id,
            'price' => 320.73,
            'currency' => 'PKR',
            'unit' => 'Rs/L',
            'effective_date' => '2026-07-22',
            'source' => 'trackmate',
            'verified' => false,
        ]);

        FuelPriceHistory::query()->create([
            'fuel_price_id' => $psoPrice->id,
            'previous_price' => 360.06,
            'new_price' => 367.21,
            'change_amount' => 7.15,
            'change_percentage' => 2,
            'effective_at' => '2026-07-22 00:00:00',
        ]);
        FuelPriceHistory::query()->create([
            'fuel_price_id' => $shellPrice->id,
            'previous_price' => 360.06,
            'new_price' => 320.73,
            'change_amount' => -39.33,
            'change_percentage' => -10,
            'effective_at' => '2026-07-22 00:00:00',
        ]);

        $points = app(FuelHistorySeries::class)->points('hsd', 30);
        $byDate = collect($points)->keyBy('date');

        $this->assertSame(367.21, $byDate['2026-07-22']['price']);
    }

    public function test_same_day_correction_keeps_latest_history_row(): void
    {
        $pso = FuelCompany::query()->create([
            'name' => 'Pakistan State Oil',
            'short_name' => 'PSO',
            'slug' => 'pso',
            'is_active' => true,
            'priority' => 10,
        ]);
        $hsd = FuelType::query()->create([
            'name' => 'HSD',
            'slug' => 'hsd',
            'category' => 'diesel',
            'unit' => 'Rs/L',
            'is_active' => true,
            'priority' => 20,
        ]);
        $price = FuelPrice::query()->create([
            'company_id' => $pso->id,
            'fuel_type_id' => $hsd->id,
            'price' => 367.21,
            'currency' => 'PKR',
            'unit' => 'Rs/L',
            'effective_date' => '2026-07-22',
            'source' => 'propakistani',
            'verified' => true,
        ]);

        FuelPriceHistory::query()->create([
            'fuel_price_id' => $price->id,
            'previous_price' => 360.06,
            'new_price' => 320.73,
            'change_amount' => -39.33,
            'change_percentage' => -10,
            'effective_at' => '2026-07-22 00:00:00',
        ]);
        FuelPriceHistory::query()->create([
            'fuel_price_id' => $price->id,
            'previous_price' => 320.73,
            'new_price' => 367.21,
            'change_amount' => 46.48,
            'change_percentage' => 14,
            'effective_at' => '2026-07-22 00:00:00',
        ]);

        $points = app(FuelHistorySeries::class)->points('hsd', 30);
        $byDate = collect($points)->keyBy('date');

        $this->assertSame(367.21, $byDate['2026-07-22']['price']);
    }

    public function test_includes_other_omc_history_when_pso_only_has_today(): void
    {
        $pso = FuelCompany::query()->create([
            'name' => 'Pakistan State Oil',
            'short_name' => 'PSO',
            'slug' => 'pso',
            'is_active' => true,
            'priority' => 10,
        ]);
        $shell = FuelCompany::query()->create([
            'name' => 'Shell',
            'short_name' => 'Shell',
            'slug' => 'shell',
            'is_active' => true,
            'priority' => 20,
        ]);
        $hsd = FuelType::query()->create([
            'name' => 'HSD',
            'slug' => 'hsd',
            'category' => 'diesel',
            'unit' => 'Rs/L',
            'is_active' => true,
            'priority' => 20,
        ]);

        $psoPrice = FuelPrice::query()->create([
            'company_id' => $pso->id,
            'fuel_type_id' => $hsd->id,
            'price' => 367.21,
            'previous_price' => 360.06,
            'currency' => 'PKR',
            'unit' => 'Rs/L',
            'effective_date' => '2026-07-22',
            'source' => 'ogra_daily',
            'verified' => true,
        ]);
        $shellPrice = FuelPrice::query()->create([
            'company_id' => $shell->id,
            'fuel_type_id' => $hsd->id,
            'price' => 360.06,
            'previous_price' => 354.35,
            'currency' => 'PKR',
            'unit' => 'Rs/L',
            'effective_date' => '2026-07-21',
            'source' => 'trackmate',
            'verified' => false,
        ]);

        FuelPriceHistory::query()->create([
            'fuel_price_id' => $shellPrice->id,
            'previous_price' => 354.35,
            'new_price' => 360.06,
            'change_amount' => 5.71,
            'change_percentage' => 1.6,
            'effective_at' => '2026-07-15 00:00:00',
        ]);
        FuelPriceHistory::query()->create([
            'fuel_price_id' => $psoPrice->id,
            'previous_price' => 360.06,
            'new_price' => 367.21,
            'change_amount' => 7.15,
            'change_percentage' => 2,
            'effective_at' => '2026-07-22 00:00:00',
        ]);

        $points = app(FuelHistorySeries::class)->points('hsd', 30);
        $byDate = collect($points)->keyBy('date');

        $this->assertCount(2, $points);
        $this->assertSame(360.06, $byDate['2026-07-15']['price']);
        $this->assertSame(367.21, $byDate['2026-07-22']['price']);
    }
}

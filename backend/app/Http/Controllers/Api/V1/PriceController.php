<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\HistoryPricesRequest;
use App\Http\Requests\Api\V1\ListPricesRequest;
use App\Models\FuelPrice;
use App\Repositories\PriceRepository;
use App\Services\Fuel\FuelHistorySeries;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PriceController extends Controller
{
    public function __construct(
        private readonly PriceRepository $prices,
        private readonly FuelHistorySeries $historySeries,
    ) {}

    public function index(ListPricesRequest $request): JsonResponse
    {
        $data = $request->validated();
        $city = $data['city'] ?? null;
        $company = $data['company'] ?? null;
        $product = $data['product'] ?? null;
        $country = $data['country'] ?? null;

        $rows = $this->prices->latest($city, $company, $product, $country)
            ->map(fn (FuelPrice $price) => $this->serializePrice($price))
            ->values();

        return ApiResponse::success([
            'count' => $rows->count(),
            'items' => $rows,
        ], 'Latest prices');
    }

    public function history(HistoryPricesRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = $data['product'] ?? 'petrol';
        $company = $data['company'] ?? null;
        $days = (int) ($data['days'] ?? 30);
        $country = strtoupper((string) ($data['country'] ?? 'PK'));

        // History series is Pakistan-sourced in Phase 1.
        if ($country !== 'PK') {
            return ApiResponse::success(['items' => []], 'History');
        }

        $points = $this->historySeries->points($product, $days, $company);

        return ApiResponse::success(['items' => $points], 'History');
    }

    private function serializePrice(FuelPrice $price): array
    {
        return [
            'id' => (string) $price->id,
            'company_id' => $price->company?->slug ?? (string) $price->company_id,
            'city_id' => $price->city?->slug ?? ($price->city_id ? (string) $price->city_id : 'national'),
            'country_code' => $price->country?->code
                ?? $price->city?->country?->code
                ?? 'PK',
            'fuel_type_id' => $price->fuelType?->slug ?? (string) $price->fuel_type_id,
            'price' => (float) $price->price,
            'previous_price' => $price->previous_price !== null ? (float) $price->previous_price : null,
            'currency' => $price->currency,
            'unit' => $price->unit,
            'effective_date' => optional($price->effective_date)->toDateString() ?? now()->toDateString(),
            'source' => $price->source,
            'source_url' => $price->source_url,
            'verified' => (bool) $price->verified,
            'company' => $price->company ? [
                'name' => $price->company->name,
                'short_name' => $price->company->short_name,
                'slug' => $price->company->slug,
            ] : null,
            'city' => $price->city ? [
                'name' => $price->city->name,
                'slug' => $price->city->slug,
            ] : null,
            'fuel_type' => $price->fuelType ? [
                'name' => $price->fuelType->name,
                'slug' => $price->fuelType->slug,
                'category' => $price->fuelType->category,
                'unit' => $price->fuelType->unit,
            ] : null,
        ];
    }
}

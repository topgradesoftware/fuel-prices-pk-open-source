<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelType;
use App\Models\SyncLog;
use App\Repositories\PriceRepository;
use App\Services\Fuel\FuelPriceSyncService;
use App\Http\Requests\Admin\StoreManualPriceRequest;
use App\Support\SafeError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OpsController extends Controller
{
    public function __construct(
        private readonly PriceRepository $prices,
    ) {}

    public function index(): Response
    {
        $companies = FuelCompany::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'slug', 'is_active', 'priority']);

        $fuelTypes = FuelType::query()
            ->orderBy('priority')
            ->get(['id', 'name', 'slug', 'unit']);

        $logs = SyncLog::query()
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (SyncLog $log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'status' => $log->status,
                'message' => $log->status === 'failed'
                    ? SafeError::storedMessage('Sync failed. See server logs for details.')
                    : $log->message,
                'fetched' => $log->fetched,
                'created' => $log->created,
                'updated' => $log->updated,
                'finished_at' => optional($log->finished_at)?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Ops', [
            'companies' => $companies,
            'fuel_types' => $fuelTypes,
            'sync_logs' => $logs,
            'auth_user' => [
                'name' => auth()->user()?->name,
                'email' => auth()->user()?->email,
                'is_owner' => (bool) auth()->user()?->is_owner,
            ],
        ]);
    }

    public function toggleCompany(Request $request, FuelCompany $company): RedirectResponse
    {
        $company->update([
            'is_active' => ! $company->is_active,
        ]);

        return back()->with('success', $company->short_name.' is now '.($company->is_active ? 'active' : 'hidden').'.');
    }

    public function storeManualPrice(StoreManualPriceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $existing = FuelPrice::query()
            ->where('company_id', $data['company_id'])
            ->where('fuel_type_id', $data['fuel_type_id'])
            ->whereNull('city_id')
            ->where('source', 'manual')
            ->first();

        $price = round((float) $data['price'], 2);
        $unit = $data['unit'] ?? 'Rs/L';

        if ($existing) {
            $previous = (float) $existing->price;
            $existing->update([
                'previous_price' => $previous,
                'price' => $price,
                'unit' => $unit,
                'effective_date' => now()->toDateString(),
                'verified' => true,
            ]);
        } else {
            FuelPrice::query()->create([
                'company_id' => $data['company_id'],
                'city_id' => null,
                'fuel_type_id' => $data['fuel_type_id'],
                'price' => $price,
                'previous_price' => null,
                'currency' => 'PKR',
                'unit' => $unit,
                'effective_date' => now()->toDateString(),
                'source' => 'manual',
                'verified' => true,
            ]);
        }

        $this->prices->forgetLatestCache();

        return back()->with('success', 'Manual price saved.');
    }

    public function sync(FuelPriceSyncService $sync): RedirectResponse
    {
        try {
            $result = $sync->sync();

            return back()->with('success', $result['message']);
        } catch (\Throwable $e) {
            SafeError::log($e, 'Admin fuel sync failed');

            return back()->with('error', SafeError::storedMessage('Sync failed. See server logs for details.'));
        }
    }
}

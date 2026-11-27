<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemController extends Controller
{
    public function health(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'ok',
        ], 'Healthy');
    }

    public function status(): JsonResponse
    {
        $dbOk = false;
        $redisOk = false;

        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        try {
            if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
                Redis::connection()->ping();
                $redisOk = true;
            } else {
                $redisOk = null; // not required in local
            }
        } catch (\Throwable) {
            $redisOk = false;
        }

        $lastSync = SyncLog::query()->latest('id')->first();

        $status = $dbOk ? 'ok' : 'degraded';

        return ApiResponse::success([
            'status' => $status,
            'database' => $dbOk,
            'redis' => $redisOk,
            'queue_connection' => config('queue.default'),
            'cache_store' => config('cache.default'),
            'fcm_enabled' => (bool) config('firebase.fcm_enabled'),
            'last_sync' => $lastSync ? [
                'status' => $lastSync->status,
                'finished_at' => optional($lastSync->finished_at)?->toIso8601String(),
                'updated' => $lastSync->updated,
            ] : null,
            'scheduler' => [
                'fuel_sync' => 'everyFifteenMinutes',
            ],
        ], 'Status');
    }

    public function version(): JsonResponse
    {
        return ApiResponse::success([
            'app' => config('app.name'),
            'version' => config('app.version', '0.2.0'),
            'api' => 'v1',
            'features' => config('features'),
        ], 'Version');
    }

    public function features(): JsonResponse
    {
        $features = config('features');
        if (! is_array($features)) {
            $features = [];
        }

        // Mobile Google Sign-In needs the Web OAuth client ID as serverClientId.
        $features['google_client_id'] = (config('features.google_login')
            ? (config('services.google.client_id') ?: null)
            : null);

        return ApiResponse::success($features, 'Features');
    }
}

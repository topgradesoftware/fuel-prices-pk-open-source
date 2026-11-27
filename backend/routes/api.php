<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PriceController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\SystemController;
use App\Http\Controllers\Api\V1\FuelPriceController;
use App\Http\Controllers\Api\V1\EconomicIndicatorController;
use App\Http\Controllers\Api\V1\GovernmentController;
use App\Http\Controllers\Api\V1\GlobalMarketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', [SystemController::class, 'health']);
    Route::get('/status', [SystemController::class, 'status']);
    Route::get('/version', [SystemController::class, 'version']);
    Route::get('/features', [SystemController::class, 'features']);

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/auth/guest', [AuthController::class, 'guest']);
        Route::post('/auth/firebase', [AuthController::class, 'firebase']);
        Route::post('/auth/google', [AuthController::class, 'google']);
        Route::post('/auth/apple', [AuthController::class, 'apple']);
        Route::post('/auth/email', [AuthController::class, 'email']);
        Route::post('/auth/otp', [AuthController::class, 'otp']);
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/devices', [DeviceController::class, 'store']);
        Route::delete('/devices/{uuid}', [DeviceController::class, 'destroy']);
        Route::post('/push/subscribe', [PushSubscriptionController::class, 'store']);
        Route::post('/push/unsubscribe', [PushSubscriptionController::class, 'destroy']);
    });

    Route::get('/prices', [PriceController::class, 'index']);
    Route::get('/history', [PriceController::class, 'history']);
    Route::get('/countries', [CatalogController::class, 'countries']);
    Route::get('/companies', [CatalogController::class, 'companies']);
    Route::get('/cities', [CatalogController::class, 'cities']);
    Route::get('/fuel-types', [CatalogController::class, 'fuelTypes']);
    Route::get('/stations', [CatalogController::class, 'stations']);
    Route::get('/stations/nearby', [CatalogController::class, 'nearbyStations'])
        ->middleware('throttle:30,1');
    Route::get('/notifications', [NotificationController::class, 'index']);

    // Economic Timeline Public Endpoints
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/historical-fuel-prices', [FuelPriceController::class, 'index']);
        Route::get('/economic-indicators', [EconomicIndicatorController::class, 'index']);
        Route::get('/governments', [GovernmentController::class, 'index']);
        Route::get('/global-markets', [GlobalMarketController::class, 'index']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/devices/revoke-all', [DeviceController::class, 'revokeAll']);
    });
});

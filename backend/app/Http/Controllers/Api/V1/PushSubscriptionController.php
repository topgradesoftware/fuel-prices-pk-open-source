<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PushSubscribeRequest;
use App\Http\Requests\Api\V1\PushUnsubscribeRequest;
use App\Models\PushSubscription;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PushSubscriptionController extends Controller
{
    public function store(PushSubscribeRequest $request): JsonResponse
    {
        $data = $request->validated();

        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => 'aes128gcm',
                'locale' => $data['locale'] ?? null,
                'failed_at' => null,
            ],
        );

        return ApiResponse::success(null, 'Subscribed to price alerts.');
    }

    public function destroy(PushUnsubscribeRequest $request): JsonResponse
    {
        $data = $request->validated();

        PushSubscription::query()->where('endpoint', $data['endpoint'])->delete();

        return ApiResponse::success(null, 'Unsubscribed.');
    }
}

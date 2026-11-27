<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DeviceRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyDeviceRequest;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\Device;
use App\Repositories\DeviceRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function __construct(
        private readonly DeviceRepository $devices,
    ) {}

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $existing = $this->devices->findByUuid($data['device_uuid']);
        $isNew = $existing === null;

        // Guard mutations on an existing device: the caller must either be the
        // authenticated owner or present the device_secret issued at registration.
        // Legacy devices that predate secrets are claimed on this call (TOFU).
        if (! $isNew && ! $this->authorizesDevice($request, $existing)) {
            return ApiResponse::error('Device secret mismatch.', null, 403);
        }

        // Issue a secret for brand-new devices and for legacy rows that never had one.
        $secret = $existing?->device_secret ?: Str::random(48);

        $payload = [
            'device_uuid' => $data['device_uuid'],
            'device_name' => $data['device_name'] ?? $existing?->device_name,
            'platform' => $data['platform'],
            'app_version' => $data['app_version'] ?? $existing?->app_version,
            'os_version' => $data['os_version'] ?? $existing?->os_version,
            'firebase_token' => $data['firebase_token'] ?? $existing?->firebase_token,
            'locale' => $data['locale'] ?? $existing?->locale ?? 'en',
            'timezone' => $data['timezone'] ?? $existing?->timezone,
            'alerts_enabled' => $data['alerts_enabled'] ?? $existing?->alerts_enabled ?? true,
            'user_id' => $request->user()?->id ?? $existing?->user_id,
            'device_secret' => $secret,
            'last_seen_at' => now(),
            'last_ip' => $request->ip(),
            'revoked_at' => null,
        ];

        $device = $this->devices->upsertByUuid($payload);

        if ($isNew) {
            event(new DeviceRegistered($device));
        }

        return ApiResponse::success([
            'device' => [
                'device_uuid' => $device->device_uuid,
                'platform' => $device->platform,
                'alerts_enabled' => $device->alerts_enabled,
                'locale' => $device->locale,
            ],
            // Returned so the client can persist it and prove ownership on later calls.
            'device_secret' => $secret,
        ], $isNew ? 'Device registered successfully.' : 'Device updated successfully.');
    }

    public function destroy(DestroyDeviceRequest $request): JsonResponse
    {
        $uuid = (string) $request->validated('uuid');
        $device = $this->devices->findByUuid($uuid);
        if ($device === null) {
            return ApiResponse::error('Device not found.', null, 404);
        }

        if (! $this->authorizesDevice($request, $device)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $this->devices->revoke($device);

        return ApiResponse::success(null, 'Device revoked.');
    }

    /**
     * A caller may mutate a device only when they are its authenticated owner or
     * present the matching device_secret. A device that has no secret yet (legacy)
     * is treated as claimable on first contact.
     */
    private function authorizesDevice(Request $request, Device $device): bool
    {
        $user = $request->user();
        if ($user !== null && $device->user_id !== null && $device->user_id === $user->id) {
            return true;
        }

        $stored = (string) ($device->device_secret ?? '');
        if ($stored === '') {
            return true;
        }

        $provided = (string) ($request->input('device_secret') ?? $request->header('X-Device-Secret', ''));

        return $provided !== '' && hash_equals($stored, $provided);
    }

    public function revokeAll(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return ApiResponse::error('Unauthenticated.', null, 401);
        }

        $count = $this->devices->revokeAllForUser($user->id);

        return ApiResponse::success(['revoked' => $count], 'All devices revoked.');
    }
}

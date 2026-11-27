<?php

namespace App\Repositories;

use App\Models\Device;
use Illuminate\Support\Collection;

class DeviceRepository
{
    public function upsertByUuid(array $attributes): Device
    {
        return Device::query()->updateOrCreate(
            ['device_uuid' => $attributes['device_uuid']],
            $attributes,
        );
    }

    public function findByUuid(string $uuid): ?Device
    {
        return Device::query()->where('device_uuid', $uuid)->first();
    }

    public function activeForPush(): Collection
    {
        return Device::query()
            ->whereNull('revoked_at')
            ->where('alerts_enabled', true)
            ->whereNotNull('firebase_token')
            ->where('firebase_token', '!=', '')
            ->get();
    }

    public function revoke(Device $device): Device
    {
        $device->forceFill([
            'revoked_at' => now(),
            'firebase_token' => null,
            'alerts_enabled' => false,
        ])->save();

        return $device;
    }

    public function revokeAllForUser(int $userId): int
    {
        return Device::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'firebase_token' => null,
                'alerts_enabled' => false,
            ]);
    }
}

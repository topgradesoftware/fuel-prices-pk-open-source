<?php

namespace App\Contracts;

use App\Models\AppNotification;
use App\Models\Device;
use Illuminate\Support\Collection;

interface NotificationProvider
{
    public function name(): string;

    /**
     * @param  Collection<int, Device>  $devices
     * @return array{sent: int, failed: int}
     */
    public function send(AppNotification $notification, Collection $devices): array;
}

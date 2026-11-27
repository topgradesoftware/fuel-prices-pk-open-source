<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\NotificationProvider;
use App\Models\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** Stub — Phase B/C. */
class SmsProvider implements NotificationProvider
{
    public function name(): string
    {
        return 'sms';
    }

    public function send(AppNotification $notification, Collection $devices): array
    {
        Log::debug('SmsProvider stub skipped', ['notification_id' => $notification->id]);

        return ['sent' => 0, 'failed' => 0];
    }
}

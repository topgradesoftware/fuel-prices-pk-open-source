<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\NotificationProvider;
use App\Models\AppNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** Stub — Phase B/C. */
class WhatsAppProvider implements NotificationProvider
{
    public function name(): string
    {
        return 'whatsapp';
    }

    public function send(AppNotification $notification, Collection $devices): array
    {
        Log::debug('WhatsAppProvider stub skipped', ['notification_id' => $notification->id]);

        return ['sent' => 0, 'failed' => 0];
    }
}

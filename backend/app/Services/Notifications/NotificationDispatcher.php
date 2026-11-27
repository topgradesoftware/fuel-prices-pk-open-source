<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationProvider;
use App\Models\AppNotification;
use App\Repositories\DeviceRepository;
use App\Repositories\NotificationRepository;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    /** @param  iterable<NotificationProvider>  $providers */
    public function __construct(
        private readonly iterable $providers,
        private readonly DeviceRepository $devices,
        private readonly NotificationRepository $notifications,
    ) {}

    public function dispatch(AppNotification $notification): void
    {
        $targets = $this->devices->activeForPush();
        $sent = 0;
        $failed = 0;

        foreach ($this->providers as $provider) {
            if (! in_array($provider->name(), ['fcm', 'webpush'], true)) {
                // Stubs no-op; keep call for future multi-channel.
                $provider->send($notification, $targets);

                continue;
            }

            $result = $provider->send($notification, $targets);
            $sent += $result['sent'];
            $failed += $result['failed'];
        }

        $this->notifications->markDispatchResult($notification, $sent, $failed);

        Log::info('Notification dispatched', [
            'id' => $notification->id,
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }
}

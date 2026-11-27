<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $notificationId) {}

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $notification = AppNotification::query()->find($this->notificationId);
        if ($notification === null) {
            return;
        }

        $dispatcher->dispatch($notification);
    }
}

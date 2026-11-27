<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Events\NotificationCreated;
use App\Jobs\DispatchNotificationJob;
use App\Models\AppNotification;
use App\Repositories\NotificationRepository;

class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly NotificationFormatter $formatter,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function createAndQueue(NotificationType $type, array $context = []): AppNotification
    {
        $formatted = $this->formatter->format($type, $context);

        $notification = $this->notifications->create(
            $type,
            $formatted['title'],
            $formatted['body'],
            $formatted['data'],
        );

        event(new NotificationCreated($notification));

        DispatchNotificationJob::dispatch($notification->id)
            ->onQueue(config('horizon.queues.notifications', 'notifications'));

        return $notification;
    }
}

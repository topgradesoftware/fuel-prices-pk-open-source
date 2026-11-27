<?php

namespace App\Repositories;

use App\Enums\NotificationType;
use App\Models\AppNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository
{
    public function create(
        NotificationType $type,
        string $title,
        string $body,
        array $data = [],
    ): AppNotification {
        return AppNotification::query()->create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }

    public function recent(int $perPage = 20): LengthAwarePaginator
    {
        return AppNotification::query()
            ->latest('id')
            ->paginate($perPage);
    }

    public function markDispatchResult(AppNotification $notification, int $sent, int $failed): void
    {
        $notification->forceFill([
            'sent_count' => $sent,
            'failed_count' => $failed,
        ])->save();
    }
}

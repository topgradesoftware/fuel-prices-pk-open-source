<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;

class NotificationFormatter
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{title: string, body: string, data: array<string, mixed>}
     */
    public function format(NotificationType $type, array $context = []): array
    {
        return match ($type) {
            NotificationType::PriceChange => [
                'title' => $context['title'] ?? 'Fuel prices updated',
                'body' => $context['body'] ?? 'Petrol or diesel prices have changed. Open the app for details.',
                'data' => array_merge(['type' => $type->value], $context['data'] ?? []),
            ],
            default => [
                'title' => $context['title'] ?? 'Fuel Prices PK',
                'body' => $context['body'] ?? '',
                'data' => array_merge(['type' => $type->value], $context['data'] ?? []),
            ],
        };
    }
}

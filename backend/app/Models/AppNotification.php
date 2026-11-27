<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'type',
        'title',
        'body',
        'data',
        'sent_count',
        'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'data' => 'array',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
        ];
    }
}

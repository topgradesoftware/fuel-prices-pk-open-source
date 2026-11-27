<?php

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly AppNotification $notification) {}
}

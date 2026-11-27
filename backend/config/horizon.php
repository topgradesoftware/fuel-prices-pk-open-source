<?php

/**
 * Horizon-ready stub. Install with `composer require laravel/horizon` in production,
 * then `php artisan horizon:install`. Queue names below match application jobs.
 */
return [
    'queues' => [
        'notifications' => env('QUEUE_NOTIFICATIONS', 'notifications'),
        'sync' => env('QUEUE_SYNC', 'sync'),
        'default' => env('REDIS_QUEUE', 'default'),
    ],
];

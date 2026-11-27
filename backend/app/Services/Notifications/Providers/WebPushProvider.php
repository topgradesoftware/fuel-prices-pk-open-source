<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\NotificationProvider;
use App\Models\AppNotification;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * W3C Web Push (VAPID) for PWA/browser users — works without Firebase.
 * Subscriptions live in push_subscriptions, independent of the devices table.
 */
class WebPushProvider implements NotificationProvider
{
    public function name(): string
    {
        return 'webpush';
    }

    public function send(AppNotification $notification, Collection $devices): array
    {
        $publicKey = (string) config('services.webpush.public_key');
        $privateKey = (string) config('services.webpush.private_key');
        if ($publicKey === '' || $privateKey === '') {
            Log::info('WebPush skipped (VAPID keys missing)');

            return ['sent' => 0, 'failed' => 0];
        }

        $subscriptions = PushSubscription::query()->whereNull('failed_at')->get();
        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) config('services.webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
        $webPush->setDefaultOptions(['TTL' => 3600 * 12, 'urgency' => 'normal']);

        $payload = json_encode([
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => '/',
            'tag' => 'price-change',
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]),
                (string) $payload,
            );
        }

        $sent = 0;
        $failed = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }

            $failed++;
            $endpoint = $report->getEndpoint();
            if ($report->isSubscriptionExpired()) {
                // Browser revoked/expired the subscription — drop it for good.
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
            } else {
                PushSubscription::query()->where('endpoint', $endpoint)->update(['failed_at' => now()]);
                Log::warning('WebPush send failed', [
                    'endpoint' => substr($endpoint, 0, 60),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}

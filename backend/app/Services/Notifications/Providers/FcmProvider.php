<?php

namespace App\Services\Notifications\Providers;

use App\Contracts\NotificationProvider;
use App\Models\AppNotification;
use App\Support\SafeError;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmProvider implements NotificationProvider
{
    public function name(): string
    {
        return 'fcm';
    }

    public function send(AppNotification $notification, Collection $devices): array
    {
        if (! config('firebase.fcm_enabled') || ! config('features.push_notifications')) {
            Log::info('FCM skipped (disabled)', ['notification_id' => $notification->id]);

            return ['sent' => 0, 'failed' => 0];
        }

        $accessToken = $this->accessToken();
        $projectId = config('firebase.project_id');

        if (! $accessToken || ! $projectId) {
            Log::warning('FCM credentials missing');

            return ['sent' => 0, 'failed' => $devices->count()];
        }

        $sent = 0;
        $failed = 0;
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        foreach ($devices as $device) {
            $payload = [
                'message' => [
                    'token' => $device->firebase_token,
                    'notification' => [
                        'title' => $notification->title,
                        'body' => $notification->body,
                    ],
                    'data' => collect($notification->data ?? [])
                        ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))
                        ->all(),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'price_alerts',
                        ],
                    ],
                ],
            ];

            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->post($url, $payload);

                if ($response->successful()) {
                    $sent++;
                } else {
                    $failed++;
                    Log::warning('FCM send failed', [
                        'device' => $device->device_uuid,
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                SafeError::log($e, 'FCM send exception');
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    private function accessToken(): ?string
    {
        $email = config('firebase.client_email');
        $privateKey = config('firebase.private_key');

        if (! $email || ! $privateKey) {
            $path = config('firebase.credentials');
            if (is_string($path) && is_file($path)) {
                $json = json_decode((string) file_get_contents($path), true);
                $email = $json['client_email'] ?? null;
                $privateKey = $json['private_key'] ?? null;
            }
        }

        if (! $email || ! $privateKey) {
            return null;
        }

        try {
            $now = time();
            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = $this->b64(json_encode([
                'iss' => $email,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            $unsigned = $header.'.'.$claim;
            openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = $unsigned.'.'.$this->b64($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->json('access_token');
        } catch (\Throwable $e) {
            SafeError::log($e, 'FCM token error');

            return null;
        }
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

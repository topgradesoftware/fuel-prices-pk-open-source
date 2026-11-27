<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class FirebaseTokenVerifier
{
    /**
     * @return array{uid: string, email: ?string, email_verified: bool, name: ?string, picture: ?string, provider: string}
     */
    public function verify(string $idToken): array
    {
        $projectId = config('firebase.project_id');
        if (! $projectId) {
            throw new UnexpectedValueException('Firebase project is not configured.');
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Invalid Firebase ID token.');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $header = json_decode($this->b64($headerB64), true);
        $payload = json_decode($this->b64($payloadB64), true);

        if (! is_array($header) || ! is_array($payload)) {
            throw new UnexpectedValueException('Invalid Firebase ID token payload.');
        }

        if (($payload['aud'] ?? null) !== $projectId) {
            throw new UnexpectedValueException('Firebase token audience mismatch.');
        }

        if (($payload['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId) {
            throw new UnexpectedValueException('Firebase token issuer mismatch.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new UnexpectedValueException('Firebase token expired.');
        }

        $kid = $header['kid'] ?? null;
        $certs = $this->googleCerts();
        if ($kid === null || ! isset($certs[$kid])) {
            throw new UnexpectedValueException('Firebase token signing key not found.');
        }

        $publicKey = openssl_pkey_get_public($certs[$kid]);
        if ($publicKey === false) {
            throw new UnexpectedValueException('Unable to load Firebase public key.');
        }

        $signature = $this->b64raw($sigB64);
        $ok = openssl_verify($headerB64.'.'.$payloadB64, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new UnexpectedValueException('Firebase token signature invalid.');
        }

        $provider = 'google';
        $firebase = $payload['firebase'] ?? [];
        if (is_array($firebase) && isset($firebase['sign_in_provider'])) {
            $provider = (string) $firebase['sign_in_provider'];
        }

        return [
            'uid' => (string) $payload['sub'],
            'email' => isset($payload['email']) ? (string) $payload['email'] : null,
            'email_verified' => (bool) ($payload['email_verified'] ?? false),
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'picture' => isset($payload['picture']) ? (string) $payload['picture'] : null,
            'provider' => $provider,
        ];
    }

    /** @return array<string, string> */
    private function googleCerts(): array
    {
        return Cache::remember('firebase:google_certs', 3600, function () {
            $response = Http::timeout(10)->get(
                'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com',
            );

            if (! $response->successful()) {
                throw new UnexpectedValueException('Unable to fetch Firebase certificates.');
            }

            return $response->json() ?? [];
        });
    }

    private function b64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }

    private function b64raw(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}

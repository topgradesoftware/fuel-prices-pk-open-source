<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class GoogleIdTokenVerifier
{
    private const CERTS_CACHE_KEY = 'google:oauth_certs';

    private const CERTS_CACHE_TTL = 43200; // 12 hours

    /**
     * Verify a Google Identity Services / OAuth ID token.
     *
     * @return array{uid: string, email: ?string, email_verified: bool, name: ?string, picture: ?string, provider: string}
     */
    public function verify(string $idToken): array
    {
        $clientId = (string) config('services.google.client_id');
        if ($clientId === '') {
            throw new UnexpectedValueException('Google login is not configured.');
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Invalid Google ID token.');
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;
        $header = json_decode($this->b64($headerB64), true);
        $payload = json_decode($this->b64($payloadB64), true);

        if (! is_array($header) || ! is_array($payload)) {
            throw new UnexpectedValueException('Invalid Google ID token payload.');
        }

        $aud = $payload['aud'] ?? null;
        if ($aud !== $clientId && ! (is_array($aud) && in_array($clientId, $aud, true))) {
            throw new UnexpectedValueException('Google token audience mismatch.');
        }

        $iss = (string) ($payload['iss'] ?? '');
        if (! in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new UnexpectedValueException('Google token issuer mismatch.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new UnexpectedValueException('Google token expired.');
        }

        $kid = $header['kid'] ?? null;
        if (! is_string($kid) || $kid === '') {
            throw new UnexpectedValueException('Google token signing key not found.');
        }

        if (! $this->signatureValid($headerB64, $payloadB64, $sigB64, $kid, false)
            && ! $this->signatureValid($headerB64, $payloadB64, $sigB64, $kid, true)) {
            throw new UnexpectedValueException('Google token signature invalid.');
        }

        // Google `email_verified` can arrive as a bool or the string "true".
        $emailVerified = $payload['email_verified'] ?? false;

        return [
            'uid' => (string) $payload['sub'],
            'email' => isset($payload['email']) ? (string) $payload['email'] : null,
            'email_verified' => $emailVerified === true || $emailVerified === 'true',
            'name' => isset($payload['name']) ? (string) $payload['name'] : null,
            'picture' => isset($payload['picture']) ? (string) $payload['picture'] : null,
            'provider' => 'google',
        ];
    }

    /** Prefetch Google certs so the next login avoids a cold network fetch. */
    public function warm(): void
    {
        $this->googleCerts(forceRefresh: false);
    }

    private function signatureValid(
        string $headerB64,
        string $payloadB64,
        string $sigB64,
        string $kid,
        bool $forceRefresh,
    ): bool {
        $certs = $this->googleCerts($forceRefresh);
        if (! isset($certs[$kid])) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($certs[$kid]);
        if ($publicKey === false) {
            return false;
        }

        $signature = $this->b64raw($sigB64);

        return openssl_verify($headerB64.'.'.$payloadB64, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /** @return array<string, string> */
    private function googleCerts(bool $forceRefresh = false): array
    {
        if ($forceRefresh) {
            Cache::forget(self::CERTS_CACHE_KEY);
        }

        /** @var array<string, string> $certs */
        $certs = Cache::remember(self::CERTS_CACHE_KEY, self::CERTS_CACHE_TTL, function () {
            return $this->fetchCerts();
        });

        return is_array($certs) ? $certs : [];
    }

    /** @return array<string, string> */
    private function fetchCerts(): array
    {
        $response = Http::connectTimeout(2)
            ->timeout(4)
            ->acceptJson()
            ->get('https://www.googleapis.com/oauth2/v1/certs');

        if (! $response->successful()) {
            throw new UnexpectedValueException('Unable to fetch Google certificates.');
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
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

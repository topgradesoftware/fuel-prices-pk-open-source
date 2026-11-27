<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DisabledAuthRequest;
use App\Http\Requests\Api\V1\FirebaseAuthRequest;
use App\Http\Requests\Api\V1\GoogleAuthRequest;
use App\Http\Requests\Api\V1\GuestAuthRequest;
use App\Repositories\DeviceRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\FirebaseTokenVerifier;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use UnexpectedValueException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DeviceRepository $devices,
        private readonly FirebaseTokenVerifier $firebase,
        private readonly GoogleIdTokenVerifier $google,
    ) {}

    public function guest(GuestAuthRequest $request): JsonResponse
    {
        $user = $this->users->createGuest($request->string('name')->toString() ?: null);
        event(new UserRegistered($user));

        $token = $user->createToken('mobile')->plainTextToken;
        $this->touchDevice($request->input('device_uuid'), $user->id, $request->ip());

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Guest session created.');
    }

    public function firebase(FirebaseAuthRequest $request): JsonResponse
    {
        if (! config('features.google_login')) {
            return ApiResponse::error('Google login is disabled.', null, 403);
        }

        try {
            $claims = $this->firebase->verify($request->string('id_token')->toString());
        } catch (UnexpectedValueException $e) {
            return ApiResponse::error($e->getMessage(), null, 401);
        }

        $existing = $this->users->findByFirebaseUid($claims['uid']);
        $user = $this->users->upsertFromFirebase([
            'uid' => $claims['uid'],
            'email' => $claims['email'],
            'email_verified' => $claims['email_verified'],
            'name' => $claims['name'],
            'avatar_url' => $claims['picture'],
            'provider' => $claims['provider'],
        ]);

        if ($existing === null) {
            event(new UserRegistered($user));
        }

        $token = $user->createToken('mobile')->plainTextToken;
        $this->touchDevice($request->input('device_uuid'), $user->id, $request->ip());

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Authenticated.');
    }

    /** Google Identity Services ID token (PWA web client). */
    public function google(GoogleAuthRequest $request): JsonResponse
    {
        if (! config('features.google_login')) {
            return ApiResponse::error('Google login is disabled.', null, 403);
        }

        try {
            $claims = $this->google->verify($request->string('id_token')->toString());
        } catch (UnexpectedValueException $e) {
            return ApiResponse::error($e->getMessage(), null, 401);
        }

        $user = $this->users->upsertFromFirebase([
            'uid' => $claims['uid'],
            'email' => $claims['email'],
            'email_verified' => $claims['email_verified'],
            'name' => $claims['name'],
            'avatar_url' => $claims['picture'],
            'provider' => 'google',
        ]);

        if ($user->wasRecentlyCreated) {
            event(new UserRegistered($user));
        }

        $token = $user->createToken('web')->plainTextToken;
        $this->touchDevice($request->input('device_uuid'), $user->id, $request->ip());

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Authenticated.');
    }

    /** Stub — Phase B */
    public function apple(DisabledAuthRequest $request): JsonResponse
    {
        return ApiResponse::error('Apple Sign-In is not enabled yet.', null, 501);
    }

    /** Stub — Phase B */
    public function email(DisabledAuthRequest $request): JsonResponse
    {
        return ApiResponse::error('Email authentication is not enabled yet.', null, 501);
    }

    /** Stub — Phase B */
    public function otp(DisabledAuthRequest $request): JsonResponse
    {
        return ApiResponse::error('OTP authentication is not enabled yet.', null, 501);
    }

    public function me(): JsonResponse
    {
        $user = request()->user();

        return ApiResponse::success([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        $user = request()->user();
        $user?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }

    private function touchDevice(?string $uuid, int $userId, ?string $ip): void
    {
        if (! $uuid) {
            return;
        }

        $device = $this->devices->findByUuid($uuid);
        if ($device === null) {
            return;
        }

        $device->forceFill([
            'user_id' => $userId,
            'last_ip' => $ip,
            'last_login_at' => now(),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ])->save();
    }

    private function userPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'auth_provider' => $user->auth_provider,
            'is_guest' => (bool) $user->is_guest,
            'avatar_url' => $user->avatar_url,
        ];
    }
}

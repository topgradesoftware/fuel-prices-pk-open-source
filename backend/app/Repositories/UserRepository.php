<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Str;

class UserRepository
{
    public function findByFirebaseUid(string $uid): ?User
    {
        return User::query()->where('firebase_uid', $uid)->first();
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function createGuest(?string $name = null): User
    {
        return User::query()->create([
            'name' => $name ?: 'Guest',
            'email' => 'guest_'.Str::uuid().'@oilprice.local',
            'password' => Str::password(32),
            'auth_provider' => 'guest',
            'is_guest' => true,
        ]);
    }

    public function upsertFromFirebase(array $payload): User
    {
        $uid = $payload['uid'];
        $email = $payload['email'] ?? ($uid.'@firebase.local');
        $emailVerified = (bool) ($payload['email_verified'] ?? false);

        // Match an existing account by email ONLY when the provider has verified
        // that email — otherwise an attacker could register an unverified identity
        // with a victim's address and take over their account (account linking hole).
        $user = $this->findByFirebaseUid($uid);
        if ($user === null && $emailVerified) {
            $matchedByEmail = $this->findByEmail($email);
            // Never hijack an account already bound to a different provider UID.
            if ($matchedByEmail !== null && $matchedByEmail->firebase_uid === null) {
                $user = $matchedByEmail;
            }
        }

        if ($user === null) {
            // For unverified emails, store a UID-scoped address so we never claim
            // (and block) a real email the user hasn't proven they own.
            $user = User::query()->create([
                'name' => $payload['name'] ?? 'User',
                'email' => $emailVerified ? $email : $uid.'@firebase.local',
                'password' => Str::password(32),
                'firebase_uid' => $uid,
                'auth_provider' => $payload['provider'] ?? 'google',
                'avatar_url' => $payload['avatar_url'] ?? null,
                'is_guest' => false,
            ]);
        } else {
            $user->forceFill([
                'firebase_uid' => $uid,
                'auth_provider' => $payload['provider'] ?? $user->auth_provider,
                'avatar_url' => $payload['avatar_url'] ?? $user->avatar_url,
                'name' => $payload['name'] ?? $user->name,
                'is_guest' => false,
            ])->save();
        }

        return $user;
    }
}

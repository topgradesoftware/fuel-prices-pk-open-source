<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner-only user management: list/search users, grant or revoke admin,
 * reset passwords, and delete accounts. Owner accounts are immutable here —
 * they can only be changed from the server (seed/tinker).
 */
class UsersController extends Controller
{
    public function index(ListUsersRequest $request): Response
    {
        $data = $request->validated();
        $search = trim((string) ($data['q'] ?? ''));
        $filter = (string) ($data['filter'] ?? 'all');

        $query = User::query()
            ->orderByDesc('is_owner')
            ->orderByDesc('is_admin')
            ->orderByDesc('id');

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like));
        }

        match ($filter) {
            'admins' => $query->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_owner', true)),
            'guests' => $query->where('is_guest', true),
            'google' => $query->where('auth_provider', 'google'),
            default => null,
        };

        $users = $query->paginate(25)->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => [
                'data' => collect($users->items())->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'auth_provider' => $u->auth_provider,
                    'is_guest' => (bool) $u->is_guest,
                    'is_admin' => (bool) $u->is_admin,
                    'is_owner' => (bool) $u->is_owner,
                    'devices_count' => $u->devices()->count(),
                    'created_at' => optional($u->created_at)?->toDateTimeString(),
                ])->all(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
            'filters' => ['q' => $search, 'filter' => $filter],
            'stats' => [
                'total' => User::query()->count(),
                'admins' => User::query()->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_owner', true))->count(),
                'guests' => User::query()->where('is_guest', true)->count(),
                'google' => User::query()->where('auth_provider', 'google')->count(),
            ],
            'auth_user' => [
                'name' => $request->user()?->name,
                'email' => $request->user()?->email,
                'is_owner' => (bool) $request->user()?->is_owner,
            ],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'auth_provider' => 'password',
            'is_guest' => false,
            'is_admin' => (bool) ($data['is_admin'] ?? false),
        ]);

        return back()->with('success', "User {$data['email']} created.");
    }

    public function toggleAdmin(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->guardMutable($user)) {
            return $response;
        }

        // Admin sessions require a password account; promoting an OAuth/guest
        // account would create an admin that cannot actually log in — block it.
        if (! $user->is_admin && $user->auth_provider !== 'password') {
            return back()->with('error', 'Only password accounts can be admins. Create a dedicated admin account instead.');
        }

        $user->update(['is_admin' => ! $user->is_admin]);

        return back()->with('success', $user->email.' is '.($user->is_admin ? 'now an admin.' : 'no longer an admin.'));
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        if ($response = $this->guardMutable($user)) {
            return $response;
        }

        $data = $request->validated();

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password updated for '.$user->email.'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->guardMutable($user)) {
            return $response;
        }

        if ($user->id === $request->user()?->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->tokens()->delete();
        $user->devices()->update(['user_id' => null]);
        $user->delete();

        return back()->with('success', 'User '.$user->email.' deleted.');
    }

    /** Owner accounts can never be modified or deleted from the panel. */
    private function guardMutable(User $user): ?RedirectResponse
    {
        if ($user->is_owner) {
            return back()->with('error', 'Owner accounts cannot be modified from the panel.');
        }

        return null;
    }
}

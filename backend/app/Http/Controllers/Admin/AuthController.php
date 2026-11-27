<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('admin.ops');
        }

        // Demo credentials only on the local machine — never staging/production.
        $isLocal = app()->environment('local');

        return Inertia::render('Admin/Login', [
            'demo_login' => $isLocal,
            'prefill' => $isLocal ? [
                'email' => 'admin@oilprice.test',
                'password' => 'password',
            ] : [
                'email' => '',
                'password' => '',
            ],
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->validated();

        $key = Str::lower('admin-login:'.$request->ip().'|'.$credentials['email']);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        // Only flagged admin/owner accounts may hold a web session. Regular
        // app users (guests/OAuth) are excluded by the flag check; their
        // random 32-char passwords are unguessable anyway. We deliberately do
        // NOT require auth_provider='password' here: an owner who also signs
        // into the app with Google gets their provider flipped to 'google',
        // which must not lock them out of the admin panel.
        $attempt = array_merge($credentials, [
            fn ($query) => $query->where(fn ($q) => $q->where('is_admin', true)->orWhere('is_owner', true)),
        ]);

        if (! Auth::attempt($attempt, true)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'email' => 'Invalid email or password.',
            ])->onlyInput('email');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.ops'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}

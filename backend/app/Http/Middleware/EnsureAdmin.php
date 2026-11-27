<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Owner implies admin: the software owner has every permission.
        abort_unless($user !== null && ($user->is_admin || $user->is_owner), 403);

        return $next($request);
    }
}

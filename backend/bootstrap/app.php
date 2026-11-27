<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use App\Support\SafeError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sentry\Laravel\Integration;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Nginx / CloudPanel / Cloudflare
        $middleware->trustProxies(at: '*');

        $middleware->append(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ForceHttps::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->redirectGuestsTo('/admin/login');
        $middleware->redirectUsersTo('/admin/ops');

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'owner' => EnsureOwner::class,
        ]);

        $middleware->throttleApi('60,1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // No-op unless SENTRY_LARAVEL_DSN is set in the environment.
        Integration::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error(
                'Validation failed.',
                $e->errors(),
                422,
            );
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', null, 401);
        });

        // API: never expose exception text, SQL, paths, or stacks — regardless of APP_DEBUG.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            if ($status >= 500) {
                SafeError::log($e, 'Unhandled API exception');
            }

            return ApiResponse::error(SafeError::publicMessage($e, $status), null, $status);
        });

        // Web/Inertia: never render Ignition/Whoops stacks or raw exception text to browsers.
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            // Let Laravel handle expected client/auth/validation errors with its safe defaults.
            if ($e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof AuthorizationException
                || $e instanceof ModelNotFoundException
                || $e instanceof NotFoundHttpException
                || ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500)) {
                return null;
            }

            SafeError::log($e, 'Unhandled web exception');

            $requestId = $request->attributes->get('request_id');

            if ($request->header('X-Inertia')) {
                return back()->with('error', 'Something went wrong. Please try again.');
            }

            return response()->view('errors.safe', [
                'status' => 500,
                'message' => SafeError::publicMessage(null, 500),
                'requestId' => $requestId,
            ], 500);
        });
    })->create();

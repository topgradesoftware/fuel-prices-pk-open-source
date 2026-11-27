<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps exceptions to safe client-facing messages and logs full detail server-side.
 */
final class SafeError
{
    public static function publicMessage(?Throwable $e = null, ?int $status = null): string
    {
        $status ??= $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

        return match (true) {
            $status === 401 => 'Unauthenticated.',
            $status === 403 => 'Forbidden.',
            $status === 404 => 'Not found.',
            $status === 405 => 'Method not allowed.',
            $status === 408 => 'Request timeout.',
            $status === 419 => 'Page expired.',
            $status === 422 => 'Validation failed.',
            $status === 429 => 'Too many requests.',
            $status >= 500 => 'Server error.',
            default => 'Request failed.',
        };
    }

    /** Generic copy for flash messages, admin UI, and persisted error columns. */
    public static function storedMessage(string $fallback = 'Operation failed. See server logs for details.'): string
    {
        return $fallback;
    }

    /**
     * Log full exception detail with request context for debugging.
     *
     * @param  array<string, mixed>  $extra
     */
    public static function log(Throwable $e, string $context, array $extra = [], string $level = 'error'): void
    {
        $request = function_exists('request') ? request() : null;

        Log::log($level, $context, array_merge([
            'exception' => $e,
            'exception_class' => $e::class,
            'exception_message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'request_id' => $request?->attributes->get('request_id'),
            'method' => $request?->method(),
            'url' => $request?->fullUrl(),
            'ip' => $request?->ip(),
            'user_id' => $request?->user()?->getAuthIdentifier(),
        ], $extra));
    }
}

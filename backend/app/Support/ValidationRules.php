<?php

namespace App\Support;

/**
 * Shared strict schema fragments for Form Requests.
 */
final class ValidationRules
{
    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function optionalSlug(int $max = 64): array
    {
        return ['sometimes', 'nullable', 'string', 'max:'.$max, 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/'];
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function requiredSlug(int $max = 64): array
    {
        return ['required', 'string', 'max:'.$max, 'regex:/^[a-z0-9]+(?:[_-][a-z0-9]+)*$/'];
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function optionalCountryCode(): array
    {
        return ['sometimes', 'nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'];
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function optionalSearch(int $max = 120): array
    {
        return ['sometimes', 'nullable', 'string', 'max:'.$max];
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function idToken(): array
    {
        return ['required', 'string', 'min:20', 'max:8192'];
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function optionalUuid(): array
    {
        return ['sometimes', 'nullable', 'uuid'];
    }

    /** @return list<string|\Illuminate\Contracts\Validation\ValidationRule> */
    public static function requiredUuid(): array
    {
        return ['required', 'uuid'];
    }
}

<?php

namespace App\Support;

use App\Models\Country;

class CountryScope
{
    public const DEFAULT_CODE = 'PK';

    public static function normalize(?string $code): string
    {
        $code = strtoupper(trim((string) $code));

        return $code !== '' ? $code : self::DEFAULT_CODE;
    }

    public static function resolve(?string $code): Country
    {
        $normalized = self::normalize($code);

        $country = Country::query()
            ->where('code', $normalized)
            ->where('is_active', true)
            ->first();

        if ($country) {
            return $country;
        }

        return Country::query()
            ->where('code', self::DEFAULT_CODE)
            ->firstOrFail();
    }

    public static function pakistan(): Country
    {
        return self::resolve(self::DEFAULT_CODE);
    }
}

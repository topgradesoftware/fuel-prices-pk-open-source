<?php

namespace App\Services\Importers;

use App\Contracts\ImporterInterface;

class IMFImporter implements ImporterInterface
{
    public function download(): mixed
    {
        return null;
    }

    public function parse(mixed $rawData): array
    {
        return [];
    }

    public function validate(array $parsedData): bool
    {
        return true;
    }

    public function store(array $validatedData): void
    {
    }
}

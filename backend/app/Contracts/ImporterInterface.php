<?php

namespace App\Contracts;

interface ImporterInterface
{
    /**
     * Download or fetch raw data from the external source.
     */
    public function download(): mixed;

    /**
     * Parse the raw data into a structured format.
     */
    public function parse(mixed $rawData): array;

    /**
     * Validate the parsed data against expected rules.
     */
    public function validate(array $parsedData): bool;

    /**
     * Store the validated data into the database.
     */
    public function store(array $validatedData): void;
}

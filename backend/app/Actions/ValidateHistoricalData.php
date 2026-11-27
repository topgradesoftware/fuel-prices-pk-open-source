<?php

namespace App\Actions;

class ValidateHistoricalData
{
    public function execute(array $data, array $requiredFields = []): bool
    {
        if (empty($data)) {
            return false;
        }

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        return true;
    }
}

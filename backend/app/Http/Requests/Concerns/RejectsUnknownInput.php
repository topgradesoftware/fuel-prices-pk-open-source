<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Fail validation when the request contains keys not declared in rules().
 */
trait RejectsUnknownInput
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $allowed = $this->allowedInputKeys();
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add((string) $key, 'Unexpected field.');
                }
            }
        });
    }

    /** @return list<string> */
    protected function allowedInputKeys(): array
    {
        $keys = [];
        foreach (array_keys($this->rules()) as $ruleKey) {
            $keys[] = explode('.', (string) $ruleKey)[0];
        }

        return array_values(array_unique($keys));
    }
}

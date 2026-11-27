<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class HistoryPricesRequest extends FormRequest
{
    use RejectsUnknownInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $country = $this->input('country');
        if (is_string($country) && $country !== '') {
            $this->merge(['country' => strtoupper($country)]);
        }

        foreach (['product', 'company', 'country'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product' => ValidationRules::optionalSlug(),
            'company' => ValidationRules::optionalSlug(),
            'country' => ValidationRules::optionalCountryCode(),
            'days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}

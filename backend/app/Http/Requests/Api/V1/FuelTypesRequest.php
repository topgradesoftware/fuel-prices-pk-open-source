<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class FuelTypesRequest extends FormRequest
{
    use RejectsUnknownInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('q') === '') {
            $this->merge(['q' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ValidationRules::optionalSearch(120),
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualPriceRequest extends FormRequest
{
    use RejectsUnknownInput;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:fuel_companies,id'],
            'fuel_type_id' => ['required', 'integer', 'exists:fuel_types,id'],
            'price' => ['required', 'numeric', 'min:1', 'max:9999'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:16', 'regex:/^[A-Za-z$€£¥₹\/\s]{1,16}$/'],
        ];
    }
}

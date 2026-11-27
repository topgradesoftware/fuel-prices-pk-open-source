<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class PublicCalculatorRequest extends FormRequest
{
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
            'mode' => ['sometimes', 'nullable', 'string', 'in:fuel,tank,trip'],
            'tab' => ['sometimes', 'nullable', 'string', 'in:home,compare,history,stations,calc,garage,more'],
        ];
    }
}

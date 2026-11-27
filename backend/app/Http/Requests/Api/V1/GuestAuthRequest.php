<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class GuestAuthRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'device_uuid' => ValidationRules::optionalUuid(),
        ];
    }
}

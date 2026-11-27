<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Stubs for disabled auth methods: reject any request body fields.
 */
class DisabledAuthRequest extends FormRequest
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
        return [];
    }
}

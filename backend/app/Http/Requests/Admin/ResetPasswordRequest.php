<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:12', 'max:255'],
        ];
    }
}

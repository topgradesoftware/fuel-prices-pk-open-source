<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class ListUsersRequest extends FormRequest
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
        if ($this->input('filter') === '' || $this->input('filter') === null) {
            $this->merge(['filter' => 'all']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ValidationRules::optionalSearch(120),
            'filter' => ['sometimes', 'nullable', 'string', 'in:all,admins,guests,google'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}

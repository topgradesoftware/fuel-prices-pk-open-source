<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use Illuminate\Foundation\Http\FormRequest;

class AutomationIndexRequest extends FormRequest
{
    use RejectsUnknownInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('filter') === '' || $this->input('filter') === null) {
            $this->merge(['filter' => 'pending_review']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'filter' => [
                'sometimes',
                'nullable',
                'string',
                'in:all,pending_review,failed,published,archived,ai_generated,template_generated,requires_attention',
            ],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}

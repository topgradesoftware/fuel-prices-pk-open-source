<?php

namespace App\Http\Requests\Web;

use App\Models\Article;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuideIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'tag', 'category'] as $field) {
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
            'q' => ValidationRules::optionalSearch(120),
            'tag' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(Article::CATEGORIES))],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}

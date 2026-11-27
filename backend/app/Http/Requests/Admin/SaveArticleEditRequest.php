<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use Illuminate\Foundation\Http\FormRequest;

class SaveArticleEditRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'excerpt' => ['required', 'string', 'min:1', 'max:500'],
            'body' => ['required', 'string', 'min:1', 'max:50000'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
        ];
    }
}

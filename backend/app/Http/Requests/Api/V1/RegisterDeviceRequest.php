<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\RejectsUnknownInput;
use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
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
            'device_uuid' => ['required', 'uuid'],
            'device_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:40'],
            'os_version' => ['sometimes', 'nullable', 'string', 'max:40'],
            'firebase_token' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:16'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'alerts_enabled' => ['sometimes', 'nullable', 'boolean'],
            'device_secret' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:100'],
            'timezone' => [
                'required',
                'string',
                Rule::in(['Asia/Tokyo', 'UTC', 'America/Los_Angeles', 'Europe/London']),
            ],
        ];
    }
}

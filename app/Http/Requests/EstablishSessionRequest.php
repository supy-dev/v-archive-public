<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EstablishSessionRequest extends FormRequest
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
            'access_token' => ['required', 'string'],
        ];
    }

    public function accessToken(): string
    {
        return (string) $this->string('access_token');
    }
}

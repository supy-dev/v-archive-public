<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OshiColor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOshiRequest extends FormRequest
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
            'name'       => ['required', 'string', 'max:100'],
            'group_name' => ['nullable', 'string', 'max:100'],
            'color_id'   => ['nullable', 'string', Rule::enum(OshiColor::class)],
            'memo'       => ['nullable', 'string'],
        ];
    }
}

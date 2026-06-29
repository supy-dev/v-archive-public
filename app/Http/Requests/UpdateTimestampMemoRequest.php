<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** タイムスタンプメモ更新リクエストのバリデーション（FR-004 / FR-014） */
class UpdateTimestampMemoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy は Controller で確認する
    }

    public function rules(): array
    {
        return [
            'seconds'         => ['required', 'integer', 'min:0'],
            'body'            => ['required', 'string', 'max:1000'],
            'tag_ids'         => ['nullable', 'array'],
            'tag_ids.*'       => ['uuid'],
            'new_tag_names'   => ['nullable', 'array'],
            'new_tag_names.*' => ['string', 'max:50'],
        ];
    }
}

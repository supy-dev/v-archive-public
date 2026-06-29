<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** 動画ノート保存リクエストのバリデーション（FR-005 / FR-015） */
class SaveVideoNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy は Controller で確認する
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}

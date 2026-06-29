<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 見るリスト追加・見送りリクエストのバリデーション。
 *
 * status は want_to_watch と skipped のみ受け付ける。
 * watching は Feature 005 のプレイヤーから自動設定のため除外（FR-005）。
 */
class StoreUserWatchItemRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:want_to_watch,skipped'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => '無効なステータスです。',
        ];
    }
}

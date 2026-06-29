<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 視聴ステータス変更リクエストのバリデーション。
 *
 * 配信詳細のステータス選択から、全4状態へ手動変更できる。
 * watching はプレイヤー再生時にも自動設定される。
 */
class UpdateWatchStatusRequest extends FormRequest
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
            'status'      => ['required', 'string', 'in:want_to_watch,watching,watched,skipped'],
            // 詳細ページからのリダイレクト先（FR-016 / T020）。省略時は back() にフォールバック。
            'redirect_to' => ['nullable', 'string', 'max:500'],
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

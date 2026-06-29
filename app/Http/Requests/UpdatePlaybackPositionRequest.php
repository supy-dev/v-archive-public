<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 再生位置保存リクエストのバリデーション（FR-017）。
 *
 * last_position_seconds: 0 以上、動画時間以下（duration_seconds が取得できる場合のみ max を設定）。
 * is_ended: boolean 必須（動画終了イベントの判別に使用）。
 */
class UpdatePlaybackPositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 所有権確認は Controller の authorize() で実施済み
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // watch_item に紐づく動画の duration_seconds を取得（null の場合は max バリデーション省略）
        $duration = $this->route('watchItem')?->youtubeVideo?->duration_seconds;

        $positionRules = ['required', 'integer', 'min:0'];
        if ($duration !== null) {
            $positionRules[] = "max:{$duration}";
        }

        return [
            'last_position_seconds' => $positionRules,
            'is_ended'              => ['required', 'boolean'],
        ];
    }
}

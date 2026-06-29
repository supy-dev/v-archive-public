<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChannelSettingsRequest extends FormRequest
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
            'sync_enabled' => ['required', 'boolean'],
            // NOTIFICATIONS_PAUSED: 通知配信の実装後にバリデーションを戻す。
            // 'notify_enabled' => ['nullable', 'boolean'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Channel;

use App\Http\Requests\UpdateChannelSettingsRequest;
use App\Models\UserChannel;

class UpdateChannelSettingsAction
{
    /** 同期設定を更新する。通知設定は NOTIFICATIONS_PAUSED として一時停止中。 */
    public function execute(UserChannel $userChannel, UpdateChannelSettingsRequest $request): UserChannel
    {
        $userChannel->update([
            'sync_enabled' => $request->boolean('sync_enabled'),
            // NOTIFICATIONS_PAUSED: 通知配信の実装後に更新処理を戻す。
            // 'notify_enabled' => $request->boolean('notify_enabled'),
        ]);

        return $userChannel->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Channel\DeregisterChannelAction;
use App\Actions\Channel\RegisterChannelAction;
use App\Actions\Channel\SetMainChannelAction;
use App\Actions\Channel\UpdateChannelSettingsAction;
use App\Http\Requests\StoreUserChannelRequest;
use App\Http\Requests\UpdateChannelSettingsRequest;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * チャンネル登録・解除・設定変更・メイン指定のコントローラー。
 * 憲法 I: Controller は検証 → Action 呼び出し → Response のみ。
 */
class UserChannelController extends Controller
{
    /** チャンネルを推しに登録する */
    public function store(
        StoreUserChannelRequest $request,
        Oshi $oshi,
        RegisterChannelAction $action,
    ): RedirectResponse {
        $this->authorize('create', [UserChannel::class, $oshi]);

        /** @var Profile $user */
        $user = Auth::user();
        $action->execute($user, $oshi, $request);

        return redirect()->route('oshis.show', $oshi)
            ->with('success', 'チャンネルを登録しました。');
    }

    /** チャンネル登録を解除する */
    public function destroy(
        Oshi $oshi,
        UserChannel $userChannel,
        DeregisterChannelAction $action,
    ): RedirectResponse {
        $this->authorize('delete', $userChannel);
        $action->execute($userChannel);

        return redirect()->route('oshis.show', $oshi)
            ->with('success', 'チャンネル登録を解除しました。');
    }

    /** 同期設定を変更する（通知設定は NOTIFICATIONS_PAUSED として一時停止中） */
    public function update(
        UpdateChannelSettingsRequest $request,
        Oshi $oshi,
        UserChannel $userChannel,
        UpdateChannelSettingsAction $action,
    ): RedirectResponse {
        $this->authorize('update', $userChannel);
        $action->execute($userChannel, $request);

        return redirect()->route('oshis.show', $oshi)
            ->with('success', 'チャンネル設定を更新しました。');
    }

    /** メインチャンネルを変更する（Phase 6: US4） */
    public function setMain(
        Oshi $oshi,
        UserChannel $userChannel,
        SetMainChannelAction $action,
    ): RedirectResponse {
        $this->authorize('update', $userChannel);
        $action->execute($userChannel);

        return redirect()->route('oshis.show', $oshi);
    }
}

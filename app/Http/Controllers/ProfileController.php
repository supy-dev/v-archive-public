<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Profile\UpdateProfileAction;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Profile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * 認証済みユーザー本人のプロフィールを表示する。所有権は ProfilePolicy で
     * 強制し、他ユーザーのプロフィールは決して描画しない（FR-009 / SC-003）。
     */
    public function show(Request $request): View
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->authorize('view', $profile);

        $profile->loadCount(['oshis', 'userChannels']);
        $syncEnabledCount = $profile->userChannels()->where('sync_enabled', true)->count();

        return view('profile.show', compact('profile', 'syncEnabledCount'));
    }

    public function update(
        UpdateProfileRequest $request,
        UpdateProfileAction $action,
    ): RedirectResponse {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->authorize('update', $profile);
        $action->execute($profile, $request);

        return redirect()
            ->to(route('profile.show').'#profile-settings')
            ->with('status', 'プロフィール設定を保存しました。');
    }
}

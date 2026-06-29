<?php

declare(strict_types=1);

namespace App\Actions\Oshi;

use App\Http\Requests\StoreOshiRequest;
use App\Models\Oshi;
use App\Models\Profile;

class CreateOshiAction
{
    /**
     * 推しを作成して返す。
     * profile_id はリクエストボディから取得せず、認証済みプロフィールから設定する（憲法 III）。
     */
    public function execute(Profile $profile, StoreOshiRequest $request): Oshi
    {
        return Oshi::create([
            'profile_id' => $profile->id,
            'name'       => $request->input('name'),
            'group_name' => $request->input('group_name'),
            'color_id'   => $request->input('color_id'),
            'memo'       => $request->input('memo'),
        ]);
    }
}

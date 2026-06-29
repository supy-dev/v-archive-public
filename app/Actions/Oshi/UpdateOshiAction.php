<?php

declare(strict_types=1);

namespace App\Actions\Oshi;

use App\Http\Requests\UpdateOshiRequest;
use App\Models\Oshi;

class UpdateOshiAction
{
    /** 推しを更新する。所有権確認は Policy 側で完了済み前提。 */
    public function execute(Oshi $oshi, UpdateOshiRequest $request): Oshi
    {
        $oshi->update([
            'name'       => $request->input('name'),
            'group_name' => $request->input('group_name'),
            'color_id'   => $request->input('color_id'),
            'memo'       => $request->input('memo'),
        ]);

        return $oshi->refresh();
    }
}

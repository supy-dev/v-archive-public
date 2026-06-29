<?php

declare(strict_types=1);

namespace App\Actions\Oshi;

use App\Models\Oshi;

class DeleteOshiAction
{
    /**
     * 推しを削除する。
     * user_channels は ON DELETE CASCADE で自動削除。
     * 共有マスタ（youtube_channels）は削除しない（FR-017）。
     */
    public function execute(Oshi $oshi): void
    {
        $oshi->delete();
    }
}

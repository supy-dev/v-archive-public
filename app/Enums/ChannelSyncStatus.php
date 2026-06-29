<?php

declare(strict_types=1);

namespace App\Enums;

enum ChannelSyncStatus: string
{
    /** 登録済み・動画同期待ち（Feature 002 での初期状態） */
    case Pending = 'pending';

    /** 同期完了（Feature 003 以降が設定） */
    case Synced  = 'synced';

    /** 同期エラー（Feature 003 以降が設定） */
    case Error   = 'error';

    /** 画面表示用ラベル */
    public function label(): string
    {
        return match ($this) {
            self::Pending => '同期待ち',
            self::Synced  => '同期済み',
            self::Error   => 'エラー',
        };
    }

    /** Tailwind バッジカラークラス */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-gray-100 text-gray-700',
            self::Synced  => 'bg-green-100 text-green-700',
            self::Error   => 'bg-red-100 text-red-700',
        };
    }
}

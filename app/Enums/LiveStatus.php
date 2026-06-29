<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * YouTube 動画のライブ配信状態を表す Enum。
 */
enum LiveStatus: string
{
    /** 通常動画（ライブ配信でない） */
    case None = 'none';

    /** 配信予定（開始前） */
    case Upcoming = 'upcoming';

    /** 配信中 */
    case Live = 'live';

    /** 配信終了（アーカイブ化済み） */
    case Completed = 'completed';

    /** 判定不能 */
    case Unknown = 'unknown';

    /** 現在アクティブな配信かどうか */
    public function isActive(): bool
    {
        return $this === self::Live;
    }

    /** 日本語ラベルを返す */
    public function label(): string
    {
        return match ($this) {
            self::None      => '-',
            self::Upcoming  => '配信予定',
            self::Live      => 'ライブ中',
            self::Completed => '配信終了',
            self::Unknown   => '不明',
        };
    }
}

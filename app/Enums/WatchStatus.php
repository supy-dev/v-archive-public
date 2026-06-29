<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 視聴ステータスを表す Enum。
 *
 * want_to_watch / watching / watched / skipped の 4 値。
 * Feature 004 では手動変更対象は want_to_watch / watched / skipped の 3 値で、
 * watching は Feature 005 のプレイヤーから自動設定される（FR-005）。
 */
enum WatchStatus: string
{
    /** 未視聴（見るリストに追加した状態） */
    case WantToWatch = 'want_to_watch';

    /** 視聴中（Feature 005 のプレイヤーから自動設定） */
    case Watching = 'watching';

    /** 視聴済み */
    case Watched = 'watched';

    /** 見送り */
    case Skipped = 'skipped';

    /** 日本語ラベルを返す */
    public function label(): string
    {
        return match ($this) {
            self::WantToWatch => '未視聴',
            self::Watching    => '視聴中',
            self::Watched     => '視聴済み',
            self::Skipped     => '見送り',
        };
    }

    /**
     * ステータス変更時に自動設定するタイムスタンプのカラム名を返す。
     * want_to_watch への変更時は added_at を変更しないため null を返す。
     */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::WantToWatch => null,
            self::Watching    => 'started_at',
            self::Watched     => 'watched_at',
            self::Skipped     => 'skipped_at',
        };
    }
}

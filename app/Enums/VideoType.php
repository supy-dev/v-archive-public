<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * YouTube 動画の種別を表す Enum。
 *
 * 判定ロジックは YoutubeVideoTypeResolver に集約し、このクラスには表示ロジックのみ持つ。
 */
enum VideoType: string
{
    /** ライブ配信のアーカイブ録画 */
    case Archive = 'archive';

    /** ライブ配信中（live_status=live と対応） */
    case Live = 'live';

    /** プレミア公開・予定配信 */
    case Upcoming = 'upcoming';

    /** YouTube Shorts（縦動画・短尺） */
    case Short = 'short';

    /** 通常のアップロード動画 */
    case Video = 'video';

    /** 判定不能 */
    case Unknown = 'unknown';

    /** 日本語ラベルを返す */
    public function label(): string
    {
        return match ($this) {
            self::Archive  => 'アーカイブ',
            self::Live     => 'ライブ',
            self::Upcoming => '配信予定',
            self::Short    => 'ショート',
            self::Video    => '動画',
            self::Unknown  => '不明',
        };
    }

    /** 現在ライブ配信中かどうか */
    public function isLive(): bool
    {
        return $this === self::Live;
    }
}

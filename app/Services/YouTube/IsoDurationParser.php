<?php

declare(strict_types=1);

namespace App\Services\YouTube;

/**
 * YouTube Data API が返す ISO 8601 duration 文字列を秒数へ変換するユーティリティ。
 *
 * 例: "PT1H23M45S" → 5025 / "PT5M30S" → 330 / "P0D" → 0
 */
final class IsoDurationParser
{
    /**
     * ISO 8601 duration 文字列を秒数に変換する。
     *
     * null・空文字・不正フォーマットの場合は null を返す。
     * "P0D" はライブ配信中を示し 0 を返す。
     */
    public static function toSeconds(string $duration): ?int
    {
        if ($duration === '') {
            return null;
        }

        // "P0D" はライブ配信中（尺未確定）
        if ($duration === 'P0D') {
            return 0;
        }

        // PT[H時間][M分][S秒] 形式をパース
        if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $matches)) {
            return null;
        }

        $hours   = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);
        $seconds = (int) ($matches[3] ?? 0);

        return $hours * 3600 + $minutes * 60 + $seconds;
    }
}

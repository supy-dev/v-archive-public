<?php

declare(strict_types=1);

use App\Services\YouTube\IsoDurationParser;

it('PT1H23M45S を 5025 秒に変換する', function (): void {
    expect(IsoDurationParser::toSeconds('PT1H23M45S'))->toBe(5025);
});

it('PT30M を 1800 秒に変換する', function (): void {
    expect(IsoDurationParser::toSeconds('PT30M'))->toBe(1800);
});

it('PT45S を 45 秒に変換する', function (): void {
    expect(IsoDurationParser::toSeconds('PT45S'))->toBe(45);
});

it('PT2H を 7200 秒に変換する', function (): void {
    expect(IsoDurationParser::toSeconds('PT2H'))->toBe(7200);
});

it('P0D を 0 秒に変換する（ライブ中は P0D になる）', function (): void {
    expect(IsoDurationParser::toSeconds('P0D'))->toBe(0);
});

it('空文字列は null を返す', function (): void {
    expect(IsoDurationParser::toSeconds(''))->toBeNull();
});

it('不正な形式は null を返す', function (): void {
    expect(IsoDurationParser::toSeconds('invalid'))->toBeNull();
});

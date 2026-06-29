<?php

declare(strict_types=1);

use App\Services\YouTube\ChannelInput;

it('/channel/ URL からチャンネル ID を抽出する', function (): void {
    $input = ChannelInput::fromUrl('https://www.youtube.com/channel/UCQ0e7K7IPBFV_WWWFZ1VEgg');

    expect($input)->not->toBeNull()
        ->and($input->type)->toBe('channel_id')
        ->and($input->value)->toBe('UCQ0e7K7IPBFV_WWWFZ1VEgg');
});

it('@handle URL からハンドルを抽出する', function (): void {
    $input = ChannelInput::fromUrl('https://www.youtube.com/@hoshimachi_suisei');

    expect($input)->not->toBeNull()
        ->and($input->type)->toBe('handle')
        ->and($input->value)->toBe('hoshimachi_suisei');
});

it('@handle 文字列を認識する', function (): void {
    $input = ChannelInput::fromUrl('@Kizuna_AI');

    expect($input)->not->toBeNull()
        ->and($input->type)->toBe('handle')
        ->and($input->value)->toBe('Kizuna_AI');
});

it('@ なしの handle 文字列を認識する（URL 形式でない場合）', function (): void {
    $input = ChannelInput::fromUrl('@hoshimachi_suisei');

    expect($input)->not->toBeNull()
        ->and($input->type)->toBe('handle');
});

it('/c/ URL をハンドルとして扱う', function (): void {
    $input = ChannelInput::fromUrl('https://www.youtube.com/c/fubukisch');

    expect($input)->not->toBeNull()
        ->and($input->type)->toBe('handle')
        ->and($input->value)->toBe('fubukisch');
});

it('/user/ URL をユーザー名として扱う', function (): void {
    $input = ChannelInput::fromUrl('https://www.youtube.com/user/username');

    expect($input)->not->toBeNull()
        ->and($input->type)->toBe('username')
        ->and($input->value)->toBe('username');
});

it('末尾スラッシュを正規化する', function (): void {
    $input = ChannelInput::fromUrl('https://www.youtube.com/@suisei_hosimati/');

    expect($input)->not->toBeNull()
        ->and($input->value)->toBe('suisei_hosimati');
});

it('YouTube 以外の URL は null を返す', function (): void {
    $input = ChannelInput::fromUrl('https://www.google.com/');

    expect($input)->toBeNull();
});

it('空文字は null を返す', function (): void {
    $input = ChannelInput::fromUrl('');

    expect($input)->toBeNull();
});

it('動画 URL は null を返す', function (): void {
    $input = ChannelInput::fromUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

    expect($input)->toBeNull();
});

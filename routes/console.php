<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 30 分毎に定期同期 Job を dispatch（ShouldBeUnique により重複 Job は排除される）
Schedule::command('youtube:dispatch-syncs')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// 毎日 03:00 に削除・非公開動画をマーク（深夜バッチ）
Schedule::command('youtube:mark-unavailable')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

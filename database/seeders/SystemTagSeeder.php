<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 全ユーザー共有のシステムタグ（is_system=true）を投入するシーダー。
 * 冪等実行可能（同一 slug が存在する場合はスキップ）。
 */
class SystemTagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            ['slug' => 'waratta',       'name' => '笑った',     'color' => 'mint'],
            ['slug' => 'naita',         'name' => '泣いた',     'color' => 'blue'],
            ['slug' => 'uta',           'name' => '歌',         'color' => 'purple'],
            ['slug' => 'teetee',        'name' => 'てぇてぇ',   'color' => 'pink'],
            ['slug' => 'juudai-happyou','name' => '重大発表',   'color' => 'orange'],
            ['slug' => 'kami-scene',    'name' => '神シーン',   'color' => 'purple'],
            ['slug' => 'oshi-koi',      'name' => '推し活',     'color' => 'pink'],
            ['slug' => 'ikouze',        'name' => 'いこうぜ',   'color' => 'green'],
        ];

        foreach ($tags as $tag) {
            DB::table('tags')->updateOrInsert(
                ['slug' => $tag['slug'], 'is_system' => true],
                [
                    'id'         => Str::uuid()->toString(),
                    'profile_id' => null,
                    'name'       => $tag['name'],
                    'slug'       => $tag['slug'],
                    'color'      => $tag['color'],
                    'is_system'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

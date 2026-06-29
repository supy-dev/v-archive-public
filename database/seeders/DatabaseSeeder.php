<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * アプリケーションのデータベースに初期データを投入する。
     *
     * profiles は検証済み Supabase ユーザーから初回ログイン時に作成されるため
     * （SyncProfileFromClaimsAction 参照）、認証基盤として投入するデータはない。
     * ドメイン用シーダ（推し・チャンネル等）は後続で追加する。
     */
    public function run(): void
    {
        $this->call([
            SystemTagSeeder::class,
        ]);
    }
}

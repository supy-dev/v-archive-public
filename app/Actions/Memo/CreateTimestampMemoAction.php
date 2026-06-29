<?php

declare(strict_types=1);

namespace App\Actions\Memo;

use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use Illuminate\Support\Str;

/**
 * タイムスタンプメモを作成し、タグを紐付けるAction（FR-001 / FR-007 / FR-009）。
 *
 * new_tag_names にある名前のタグは firstOrCreate でユーザー固有タグとして作成する。
 * tag_ids と new_tag_names が空配列でも正常動作する（US3 前の段階から利用可能）。
 */
class CreateTimestampMemoAction
{
    /**
     * @param  list<string> $tagIds       既存タグのUUID一覧
     * @param  list<string> $newTagNames  インライン作成するタグ名一覧
     */
    public function execute(
        UserWatchItem $watchItem,
        int $seconds,
        string $body,
        array $tagIds = [],
        array $newTagNames = [],
    ): TimestampMemo {
        $memo = TimestampMemo::create([
            'profile_id'       => $watchItem->profile_id,
            'youtube_video_id' => $watchItem->youtube_video_id,
            'seconds'          => $seconds,
            'body'             => $body,
            'is_favorite'      => false,
        ]);

        $allTagIds = $this->resolveTagIds($watchItem->profile_id, $tagIds, $newTagNames);

        if (!empty($allTagIds)) {
            $memo->tags()->sync($allTagIds);
        }

        return $memo->load('tags');
    }

    /**
     * タグIDリストを解決する。
     * new_tag_names を firstOrCreate でユーザー固有タグに変換して tagIds に合算する。
     *
     * @param  list<string> $tagIds
     * @param  list<string> $newTagNames
     * @return list<string>
     */
    private function resolveTagIds(string $profileId, array $tagIds, array $newTagNames): array
    {
        foreach ($newTagNames as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $slug = Str::slug($name);
            if ($slug === '') {
                // ASCII 以外（日本語等）は正規化した名前をそのままスラッグとして使用する
                $slug = mb_strtolower(trim($name));
            }

            $tag = Tag::firstOrCreate(
                ['profile_id' => $profileId, 'slug' => $slug, 'is_system' => false],
                ['name' => $name, 'slug' => $slug, 'color' => null],
            );
            $tagIds[] = $tag->id;
        }

        return array_values(array_unique($tagIds));
    }
}

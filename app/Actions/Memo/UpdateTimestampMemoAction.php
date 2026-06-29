<?php

declare(strict_types=1);

namespace App\Actions\Memo;

use App\Models\Tag;
use App\Models\TimestampMemo;
use App\Models\UserWatchItem;
use Illuminate\Support\Str;

/**
 * タイムスタンプメモを更新し、タグ差分を同期するAction（FR-004 / FR-007）。
 */
class UpdateTimestampMemoAction
{
    /**
     * @param  list<string> $tagIds
     * @param  list<string> $newTagNames
     */
    public function execute(
        TimestampMemo $memo,
        UserWatchItem $watchItem,
        int $seconds,
        string $body,
        array $tagIds = [],
        array $newTagNames = [],
    ): TimestampMemo {
        $memo->update([
            'seconds' => $seconds,
            'body'    => $body,
        ]);

        $allTagIds = $this->resolveTagIds($watchItem->profile_id, $tagIds, $newTagNames);
        $memo->tags()->sync($allTagIds);

        return $memo->load('tags');
    }

    /**
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

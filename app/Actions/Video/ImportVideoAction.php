<?php

declare(strict_types=1);

namespace App\Actions\Video;

use App\Enums\ChannelSyncStatus;
use App\Enums\WatchStatus;
use App\Models\Profile;
use App\Models\UserWatchItem;
use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Services\YouTube\ChannelInput;
use App\Services\YouTube\FetchVideoDetailsService;
use App\Services\YouTube\SyncChannelVideosService;
use App\Services\YouTube\VideoInput;
use App\Services\YouTube\YouTubeApiException;
use App\Services\YouTube\YouTubeChannelResolverInterface;
use Illuminate\Validation\ValidationException;

/**
 * YouTube 動画 URL から動画を取り込み、UserWatchItem を作成するアクション。
 *
 * チャンネル登録（Oshi）なしで単体取り込みを可能にする（憲法 II: 共有マスタ分離）。
 * videos.list → channels.list の順で API を呼び、クォータ消費を最小化する（憲法 V）。
 */
class ImportVideoAction
{
    public function __construct(
        private readonly FetchVideoDetailsService    $fetchDetails,
        private readonly SyncChannelVideosService    $sync,
        private readonly YouTubeChannelResolverInterface $channelResolver,
    ) {}

    /**
     * @throws ValidationException URL 不正 / 動画・チャンネルが見つからない場合
     * @throws YouTubeApiException API 障害（429 / 5xx）の場合
     */
    public function execute(Profile $profile, string $videoUrl): UserWatchItem
    {
        // 1. URL パース
        $input = VideoInput::fromUrl($videoUrl);

        if ($input === null) {
            throw ValidationException::withMessages([
                'video_url' => ['対応形式: YouTube の動画 URL（watch?v=、youtu.be/、/live/、/shorts/）を入力してください。'],
            ]);
        }

        // 2. videos.list で動画詳細取得
        $resolved = $this->fetchDetails->fetchBatch([$input->videoId]);

        if (empty($resolved)) {
            throw ValidationException::withMessages([
                'video_url' => ['指定された動画が見つかりませんでした。URLを確認してください。'],
            ]);
        }

        $resolvedVideo = $resolved[0];

        // 3. YoutubeChannel を find-or-create（チャンネル登録不要）
        $youtubeChannel = YoutubeChannel::where('youtube_channel_id', $resolvedVideo->youtubeChannelId)->first();

        if ($youtubeChannel === null) {
            $resolvedChannel = $this->channelResolver->resolve(
                new ChannelInput('channel_id', $resolvedVideo->youtubeChannelId)
            );

            if ($resolvedChannel === null) {
                throw ValidationException::withMessages([
                    'video_url' => ['動画のチャンネル情報を取得できませんでした。しばらく後に再度お試しください。'],
                ]);
            }

            $youtubeChannel = YoutubeChannel::firstOrCreate(
                ['youtube_channel_id' => $resolvedChannel->youtubeChannelId],
                [
                    'title'               => $resolvedChannel->title,
                    'description'         => $resolvedChannel->description,
                    'handle'              => $resolvedChannel->handle,
                    'thumbnail_url'       => $resolvedChannel->thumbnailUrl,
                    'uploads_playlist_id' => $resolvedChannel->uploadsPlaylistId,
                    'published_at'        => $resolvedChannel->publishedAt,
                    'sync_status'         => ChannelSyncStatus::Synced->value,
                ],
            );
        }

        // 4. YoutubeVideo を upsert
        $this->sync->upsert($youtubeChannel, [$resolvedVideo]);

        $video = YoutubeVideo::where('youtube_video_id', $resolvedVideo->youtubeVideoId)->firstOrFail();

        // 5. UserWatchItem を find-or-create（重複追加を冪等に処理）
        $watchItem = UserWatchItem::firstOrCreate(
            [
                'profile_id'       => $profile->id,
                'youtube_video_id' => $video->id,
            ],
            [
                'status'   => WatchStatus::WantToWatch->value,
                'added_at' => now(),
            ],
        );

        return $watchItem;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Channel;

use App\Enums\ChannelSyncStatus;
use App\Http\Requests\StoreUserChannelRequest;
use App\Jobs\InitialSyncYoutubeChannelJob;
use App\Models\Oshi;
use App\Models\Profile;
use App\Models\UserChannel;
use App\Models\YoutubeChannel;
use App\Services\YouTube\ChannelInput;
use App\Services\YouTube\YouTubeApiException;
use App\Services\YouTube\YouTubeChannelResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterChannelAction
{
    public function __construct(
        private readonly YouTubeChannelResolverInterface $resolver,
    ) {}

    /**
     * YouTube チャンネルを解決し、共有マスタに upsert し、ユーザーの登録を作成する。
     *
     * 処理順序:
     * 1. URL 解析 → ChannelInput（対応形式外は ValidationException）
     * 2. 共有マスタ検索（既存なら API 呼び出し不要）
     * 3. API 解決 → 共有マスタ INSERT
     * 4. 重複確認（同一ユーザー・同一チャンネル）
     * 5. user_channels INSERT（is_main は既存件数で決定）
     */
    public function execute(Profile $profile, Oshi $oshi, StoreUserChannelRequest $request): UserChannel
    {
        // 1. URL 解析
        $input = ChannelInput::fromUrl((string) $request->input('channel_url'));

        if ($input === null) {
            throw ValidationException::withMessages([
                'channel_url' => ['対応形式: チャンネル URL（/channel/UC... または /@handle）、または @handle を入力してください。'],
            ]);
        }

        $userChannel = DB::transaction(function () use ($profile, $oshi, $input): UserChannel {
            // 2. 共有マスタを channel_id で検索（channel_id が判明している場合のみ）
            $youtubeChannel = null;

            if ($input->type === 'channel_id') {
                $youtubeChannel = YoutubeChannel::where('youtube_channel_id', $input->value)->first();
            }

            // 3. 共有マスタが存在しない場合は API を呼ぶ
            if ($youtubeChannel === null) {
                try {
                    $resolved = $this->resolver->resolve($input);
                } catch (YouTubeApiException) {
                    throw ValidationException::withMessages([
                        'channel_url' => ['チャンネル情報の取得に失敗しました。しばらく後に再度お試しください。'],
                    ]);
                }

                if ($resolved === null) {
                    throw ValidationException::withMessages([
                        'channel_url' => ['指定されたチャンネルが見つかりませんでした。URL または @handle を確認してください。'],
                    ]);
                }

                // handle 経由で解決した場合も共有マスタに存在するか確認（重複作成防止）
                $youtubeChannel = YoutubeChannel::firstOrCreate(
                    ['youtube_channel_id' => $resolved->youtubeChannelId],
                    [
                        'title'               => $resolved->title,
                        'description'         => $resolved->description,
                        'handle'              => $resolved->handle,
                        'thumbnail_url'       => $resolved->thumbnailUrl,
                        'uploads_playlist_id' => $resolved->uploadsPlaylistId,
                        'published_at'        => $resolved->publishedAt,
                        'sync_status'         => ChannelSyncStatus::Pending->value,
                    ],
                );
            }

            // 4. 重複確認（同一ユーザーが同一チャンネルを再登録しようとしている）
            $alreadyRegistered = UserChannel::where('profile_id', $profile->id)
                ->where('youtube_channel_id', $youtubeChannel->id)
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages([
                    'channel_url' => ['このチャンネルはすでに登録されています。'],
                ]);
            }

            // 5. is_main の決定（当該ユーザー・当該推しの既存チャンネル数が0なら true）
            $isFirstChannel = ! UserChannel::where('profile_id', $profile->id)
                ->where('oshi_id', $oshi->id)
                ->exists();

            return UserChannel::create([
                'profile_id'         => $profile->id,
                'oshi_id'            => $oshi->id,
                'youtube_channel_id' => $youtubeChannel->id,
                'is_main'            => $isFirstChannel,
                'sync_enabled'       => true,
                'notify_enabled'     => false,
                'registered_at'      => now(),
            ]);
        });

        // トランザクション完了後に初回同期 Job を dispatch（登録画面をブロックしない FR-005）
        InitialSyncYoutubeChannelJob::dispatch($userChannel->youtubeChannel);

        return $userChannel;
    }
}

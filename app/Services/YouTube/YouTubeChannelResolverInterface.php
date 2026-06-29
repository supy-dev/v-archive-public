<?php

declare(strict_types=1);

namespace App\Services\YouTube;

interface YouTubeChannelResolverInterface
{
    /**
     * ChannelInput からチャンネル情報を解決して返す。
     * チャンネルが見つからない場合は null を返す。
     * API エラー（429・5xx）の場合は YouTubeApiException をスローする。
     */
    public function resolve(ChannelInput $input): ?ResolvedChannel;
}

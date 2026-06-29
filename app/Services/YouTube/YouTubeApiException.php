<?php

declare(strict_types=1);

namespace App\Services\YouTube;

use RuntimeException;

/**
 * YouTube Data API のエラー（429・5xx・接続失敗等）をラップする例外。
 * ユーザー向けメッセージは呼び出し元で生成し、この例外は内部情報を露出しない（憲法 IV）。
 */
class YouTubeApiException extends RuntimeException {}

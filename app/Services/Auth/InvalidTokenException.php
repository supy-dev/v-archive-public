<?php

declare(strict_types=1);

namespace App\Services\Auth;

use RuntimeException;

/**
 * Supabase アクセストークンを信用できない場合に送出する。メッセージは
 * サーバログ用であり、生のトークンを含めてはならない（憲法 IV）。
 */
class InvalidTokenException extends RuntimeException {}

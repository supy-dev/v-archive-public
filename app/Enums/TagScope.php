<?php

declare(strict_types=1);

namespace App\Enums;

/** タグのスコープ（システム共有 / ユーザー固有）を表す Enum。 */
enum TagScope: string
{
    case System    = 'system';
    case UserOwned = 'user_owned';
}

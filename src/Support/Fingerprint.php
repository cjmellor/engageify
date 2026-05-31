<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

class Fingerprint
{
    public static function make(int|string|null $userId, ?string $ip, ?string $userAgent): string
    {
        if ($userId !== null) {
            return 'user:'.$userId;
        }

        return 'anon:'.hash(algo: 'sha256', data: ($ip ?? '').'|'.($userAgent ?? ''));
    }
}

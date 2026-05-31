<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use DateTimeInterface;

class HotScore
{
    private const int EPOCH = 1134028003;

    public static function calculate(int|float $score, DateTimeInterface $createdAt): float
    {
        $order = log10(num: max(abs(num: $score), 1));

        $sign = $score <=> 0;

        $seconds = $createdAt->getTimestamp() - self::EPOCH;

        return round(num: $sign * $order + $seconds / 45000, precision: 7);
    }
}

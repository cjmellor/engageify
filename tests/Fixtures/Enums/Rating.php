<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Rateable;

enum Rating: string implements EngagementType, Rateable
{
    case Stars = 'stars';
    case Quality = 'quality';

    public function min(): float
    {
        return match ($this) {
            self::Stars => 1.0,
            self::Quality => 0.0,
        };
    }

    public function max(): float
    {
        return match ($this) {
            self::Stars => 5.0,
            self::Quality => 10.0,
        };
    }

    public function step(): ?float
    {
        return match ($this) {
            self::Stars => 1.0,
            self::Quality => null,
        };
    }
}

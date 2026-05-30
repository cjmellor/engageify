<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\HasWeight;

enum Vote: string implements EngagementType, HasWeight
{
    case Up = 'up';
    case Down = 'down';

    public function weight(): int
    {
        return match ($this) {
            self::Up => 1,
            self::Down => -1,
        };
    }
}

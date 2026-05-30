<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Exclusive;
use Cjmellor\Engageify\Contracts\HasWeight;

enum Ballot: string implements EngagementType, Exclusive, HasWeight
{
    case Up = 'ballot_up';
    case Down = 'ballot_down';

    public function weight(): int
    {
        return match ($this) {
            self::Up => 1,
            self::Down => -1,
        };
    }

    public function group(): string
    {
        return 'ballot';
    }
}

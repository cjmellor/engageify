<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Exclusive;

enum Mood: string implements EngagementType, Exclusive
{
    case Happy = 'happy';
    case Sad = 'sad';
    case Angry = 'angry';

    public function group(): string
    {
        return 'mood';
    }
}

<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;

enum Emoji: string implements EngagementType
{
    case ThumbsUp = '👍';
    case ThumbsDown = '👎';
    case Tada = '🎉';
    case Rocket = '🚀';
}

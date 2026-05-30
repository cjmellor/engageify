<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;

enum Reaction: string implements EngagementType
{
    case Bookmark = 'bookmark';
    case Celebrate = 'celebrate';
}

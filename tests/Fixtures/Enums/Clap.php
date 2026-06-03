<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;

enum Clap: string implements EngagementType
{
    case Stars = 'stars';
}

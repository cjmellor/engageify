<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Enums;

use Cjmellor\Engageify\Contracts\EngagementType;

enum EngagementTypes: string implements EngagementType
{
    case Like = 'like';
    case Dislike = 'dislike';
    case Upvote = 'upvote';
    case Downvote = 'downvote';
}

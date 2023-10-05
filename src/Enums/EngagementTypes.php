<?php

namespace Cjmellor\Engageify\Enums;

enum EngagementTypes: string
{
    case Like = 'like';
    case Dislike = 'dislike';
    case Upvote = 'upvote';
    case Downvote = 'downvote';
}

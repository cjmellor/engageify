<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Events;

use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Model;

class ModelUpvotedEvent
{
    public function __construct(
        public Model $user,
        public Model $engageable,
        public Engagement $engagement,
    ) {
        //
    }
}

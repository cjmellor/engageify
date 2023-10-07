<?php

namespace Cjmellor\Engageify\Events;

use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ModelDownvotedEvent
{
    use Dispatchable;

    public function __construct(
        public Model $user,
        public Model $engageable,
        public Engagement $engagement,
    ) {
        //
    }
}

<?php

namespace Cjmellor\Engageify\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ModelDisengagedEvent
{
    use Dispatchable;

    public function __construct(
        public Model $user,
        public Model $engageable,
    ) {
        //
    }
}

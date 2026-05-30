<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Events;

use Illuminate\Database\Eloquent\Model;

class ModelDisengagedEvent
{
    public function __construct(
        public Model $user,
        public Model $engageable,
    ) {
        //
    }
}

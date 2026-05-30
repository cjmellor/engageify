<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Events;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Model;

class Engaged
{
    public function __construct(
        public Model $actor,
        public Model $engageable,
        public EngagementType $type,
        public Engagement $engagement,
    ) {
        //
    }
}

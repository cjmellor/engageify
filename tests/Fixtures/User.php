<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Tests\Fixtures;

use Cjmellor\Engageify\Concerns\EngagesWith;
use Cjmellor\Engageify\Concerns\HasEngagements;
use Cjmellor\Engageify\Concerns\HasViews;
use Cjmellor\Engageify\Tests\Fixtures\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Table(name: 'users')]
class User extends Authenticatable
{
    use EngagesWith, HasEngagements {
        HasEngagements::engagements insteadof EngagesWith;
        EngagesWith::engagements as authoredEngagements;
    }
    use HasFactory;
    use HasViews;

    protected $guarded = [];

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}

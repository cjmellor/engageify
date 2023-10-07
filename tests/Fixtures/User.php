<?php

namespace Cjmellor\Engageify\Tests\Fixtures;

use Cjmellor\Engageify\Concerns\HasEngagements;
use Cjmellor\Engageify\Tests\Fixtures\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasEngagements;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'users';

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}

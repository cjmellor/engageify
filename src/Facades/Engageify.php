<?php

namespace Cjmellor\Engageify\Facades;

use Illuminate\Support\Facades\Facade;

class Engageify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'engageify';
    }
}

<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Contracts;

interface Exclusive
{
    public function group(): string;
}

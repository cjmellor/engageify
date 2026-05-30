<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Contracts;

interface HasWeight
{
    public function weight(): int|float;
}

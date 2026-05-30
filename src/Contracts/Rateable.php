<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Contracts;

interface Rateable
{
    public function min(): float;

    public function max(): float;

    public function step(): ?float;
}

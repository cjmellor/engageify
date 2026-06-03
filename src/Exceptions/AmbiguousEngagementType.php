<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Cjmellor\Engageify\Contracts\EngagementType;
use Exception;

class AmbiguousEngagementType extends Exception
{
    /**
     * @param  list<class-string<EngagementType>>  $enums
     */
    public static function value(string $value, array $enums): self
    {
        return new self(message: "The engagement value [{$value}] is defined by more than one registered enum [".implode(', ', $enums).']. Registered verb enums must use unique backed values.');
    }
}

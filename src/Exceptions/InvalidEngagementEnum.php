<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Cjmellor\Engageify\Contracts\EngagementType;
use Exception;

class InvalidEngagementEnum extends Exception
{
    public static function notAnEnum(string $class): self
    {
        return new self(message: "The engageify.types entry [{$class}] is not a backed enum implementing ".EngagementType::class.'.');
    }
}

<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Cjmellor\Engageify\Contracts\EngagementType;
use Exception;

class EngagementValueException extends Exception
{
    public static function notAccepted(EngagementType $type): self
    {
        return new self(message: 'The engagement type ['.$type::class.'::'.$type->name.'] does not accept a caller-supplied value.');
    }

    public static function notAvailable(EngagementType $type): self
    {
        return new self(message: 'The engagement type ['.$type::class.'::'.$type->name.'] is binary and carries no value.');
    }
}

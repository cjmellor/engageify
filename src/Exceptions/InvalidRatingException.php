<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Cjmellor\Engageify\Contracts\Rateable;
use Exception;

class InvalidRatingException extends Exception
{
    public static function outOfRange(Rateable $type, int|float $value): self
    {
        return new self(message: "The rating [{$value}] is outside the allowed range [{$type->min()}, {$type->max()}].");
    }

    public static function offStep(Rateable $type, int|float $value): self
    {
        return new self(message: "The rating [{$value}] is not a multiple of the allowed step [{$type->step()}].");
    }

    public static function valueRequired(Rateable $type): self
    {
        return new self(message: 'A '.$type::class.' rating requires a value.');
    }
}

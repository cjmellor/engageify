<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Cjmellor\Engageify\Contracts\Rateable;
use Cjmellor\Engageify\Exceptions\InvalidRatingException;

class RatingScale
{
    public static function validate(Rateable $type, int|float $value): float
    {
        throw_if(
            $value < $type->min() || $value > $type->max(),
            InvalidRatingException::outOfRange(type: $type, value: $value),
        );

        $step = $type->step();

        if ($step !== null) {
            $offset = ($value - $type->min()) / $step;

            throw_if(
                abs($offset - round($offset)) > 1e-9,
                InvalidRatingException::offStep(type: $type, value: $value),
            );
        }

        return (float) $value;
    }
}

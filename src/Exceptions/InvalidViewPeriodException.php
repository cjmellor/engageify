<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Exception;

class InvalidViewPeriodException extends Exception
{
    /**
     * @param  array<int, string>  $allowed
     */
    public static function unsupported(string $period, array $allowed): self
    {
        return new self(message: "The view period [{$period}] is not supported. Allowed periods: ".implode(', ', $allowed).'.');
    }
}

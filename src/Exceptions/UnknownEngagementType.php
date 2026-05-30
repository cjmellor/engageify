<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Cjmellor\Engageify\Contracts\EngagementType;
use Exception;

class UnknownEngagementType extends Exception
{
    /**
     * @param  class-string<EngagementType>  $enum
     */
    public static function value(string $value, string $enum): self
    {
        return new self(message: "The configured engagement enum [{$enum}] has no case for value [{$value}].");
    }

    /**
     * @param  class-string<EngagementType>  $enum
     */
    public static function instance(EngagementType $type, string $enum): self
    {
        return new self(message: 'The engagement type ['.$type::class.'] does not belong to the configured enum ['.$enum.'].');
    }
}

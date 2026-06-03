<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Exceptions;

use Cjmellor\Engageify\Contracts\EngagementType;
use Exception;

class UnknownEngagementType extends Exception
{
    /**
     * @param  list<class-string<EngagementType>>  $enums
     */
    public static function value(string $value, array $enums): self
    {
        return new self(message: "No registered engagement enum has a case for value [{$value}]. Registered: [".implode(', ', $enums).'].');
    }

    /**
     * @param  list<class-string<EngagementType>>  $enums
     */
    public static function instance(EngagementType $type, array $enums): self
    {
        return new self(message: 'The engagement type ['.$type::class.'] is not a registered verb enum. Registered: ['.implode(', ', $enums).'].');
    }
}

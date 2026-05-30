<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Exceptions\UnknownEngagementType;

class TypeResolver
{
    /**
     * @return class-string<EngagementType>
     */
    public static function enum(): string
    {
        /** @var class-string<EngagementType> $enum */
        $enum = config(key: 'engageify.types');

        return $enum;
    }

    /**
     * @throws UnknownEngagementType when the configured enum has no such case
     */
    public static function resolve(string $value): EngagementType
    {
        $enum = self::enum();

        return $enum::tryFrom($value) ?? throw UnknownEngagementType::value(value: $value, enum: $enum);
    }

    /**
     * @throws UnknownEngagementType when the Verb is a case of a different enum
     */
    public static function ensure(EngagementType $type): EngagementType
    {
        $enum = self::enum();

        return $type instanceof $enum
            ? $type
            : throw UnknownEngagementType::instance(type: $type, enum: $enum);
    }
}

<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Exceptions\AmbiguousEngagementType;
use Cjmellor\Engageify\Exceptions\InvalidEngagementEnum;
use Cjmellor\Engageify\Exceptions\UnknownEngagementType;

class TypeResolver
{
    /**
     * @return list<class-string<EngagementType>>
     */
    public static function enums(): array
    {
        /** @var class-string<EngagementType>|array<class-string<EngagementType>> $types */
        $types = config(key: 'engageify.types');

        return is_array($types) ? array_values($types) : [$types];
    }

    /**
     * @throws AmbiguousEngagementType
     */
    public static function assertUniqueValues(): void
    {
        $owner = [];

        foreach (self::enums() as $enum) {
            if (! enum_exists($enum) || ! is_subclass_of($enum, EngagementType::class)) {
                throw InvalidEngagementEnum::notAnEnum(class: $enum);
            }

            foreach ($enum::cases() as $case) {
                if (isset($owner[$case->value])) {
                    throw AmbiguousEngagementType::value(value: $case->value, enums: [$owner[$case->value], $enum]);
                }

                $owner[$case->value] = $enum;
            }
        }
    }

    /**
     * @throws UnknownEngagementType when no registered enum has such a case
     */
    public static function resolve(string $value): EngagementType
    {
        foreach (self::enums() as $enum) {
            if (($case = $enum::tryFrom($value)) !== null) {
                return $case;
            }
        }

        throw UnknownEngagementType::value(value: $value, enums: self::enums());
    }

    /**
     * @throws UnknownEngagementType when the Verb belongs to no registered enum
     */
    public static function ensure(EngagementType $type): EngagementType
    {
        foreach (self::enums() as $enum) {
            if ($type instanceof $enum) {
                return $type;
            }
        }

        throw UnknownEngagementType::instance(type: $type, enums: self::enums());
    }
}

<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Casts;

use BackedEnum;
use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Support\TypeResolver;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<EngagementType, EngagementType>
 */
class EngagementTypeCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?EngagementType
    {
        return $value === null ? null : TypeResolver::resolve(value: (string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof BackedEnum) {
            $value = TypeResolver::resolve(value: (string) $value);
        }

        return (string) $value->value;
    }
}

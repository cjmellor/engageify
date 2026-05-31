<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait EngagesWith
{
    /**
     * @return HasMany<Engagement, $this>
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(related: Engagement::class, foreignKey: 'user_id');
    }

    public function hasEngaged(Model $target, EngagementType $type): bool
    {
        return $this->engagementQueryFor(target: $target, type: $type)->exists();
    }

    public function engagementValueFor(Model $target, EngagementType $type): ?float
    {
        $value = $this->engagementQueryFor(target: $target, type: $type)->value('value');

        return $value === null ? null : (float) $value;
    }

    public function ratingFor(Model $target, EngagementType $type): ?float
    {
        return $this->engagementValueFor(target: $target, type: $type);
    }

    /**
     * @return Builder<Engagement>
     */
    protected function engagementQueryFor(Model $target, EngagementType $type): Builder
    {
        return Engagement::query()
            ->where('user_id', $this->getKey())
            ->where('engagementable_type', $target->getMorphClass())
            ->where('engagementable_id', $target->getKey())
            ->where('type', $type->value);
    }
}

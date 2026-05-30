<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Exclusive;
use Cjmellor\Engageify\Contracts\HasWeight;
use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\Disengaged;
use Cjmellor\Engageify\Events\Engaged;
use Cjmellor\Engageify\Exceptions\EngagementValueException;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Support\TypeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait HasEngagements
{
    /**
     * @return MorphMany<Engagement, $this>
     */
    public function engagements(): MorphMany
    {
        return $this->morphMany(related: Engagement::class, name: 'engagementable');
    }

    public function like(): Model
    {
        return $this->engage(type: TypeResolver::resolve(value: EngagementTypes::Like->value));
    }

    public function dislike(): Model
    {
        return $this->engage(type: TypeResolver::resolve(value: EngagementTypes::Dislike->value));
    }

    public function upvote(): Model
    {
        return $this->engage(type: TypeResolver::resolve(value: EngagementTypes::Upvote->value));
    }

    public function downvote(): Model
    {
        return $this->engage(type: TypeResolver::resolve(value: EngagementTypes::Downvote->value));
    }

    public function unlike(): void
    {
        $this->disengage(type: TypeResolver::resolve(value: EngagementTypes::Like->value));
    }

    public function toggleLike(): void
    {
        $this->hasEngagedWithType(type: TypeResolver::resolve(value: EngagementTypes::Like->value))
            ? $this->unlike()
            : $this->like();
    }

    public function likes(bool $showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: TypeResolver::resolve(value: EngagementTypes::Like->value), showUsers: $showUsers);
    }

    public function dislikes(bool $showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: TypeResolver::resolve(value: EngagementTypes::Dislike->value), showUsers: $showUsers);
    }

    public function upvotes(bool $showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: TypeResolver::resolve(value: EngagementTypes::Upvote->value), showUsers: $showUsers);
    }

    public function downvotes(bool $showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: TypeResolver::resolve(value: EngagementTypes::Downvote->value), showUsers: $showUsers);
    }

    public function engage(EngagementType $type, int|float|null $value = null): Model
    {
        $type = TypeResolver::ensure(type: $type);

        if ($type instanceof Exclusive) {
            return $this->engageExclusive(type: $type, value: $value);
        }

        throw_if(
            config(key: 'engageify.allow_multiple_engagements') === false && $this->hasEngagedWithType(type: $type),
            UserCannotEngageException::class,
            'This model has already been engaged'
        );

        if (config(key: 'engageify.allow_caching')) {
            cache()->forget(key: $this->getEngagementCacheKey($type));
        }

        $engagement = $this->engagements()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'value' => $this->resolveEngagementValue(type: $type, value: $value),
        ]);

        event(new Engaged(actor: auth()->user(), engageable: $this, type: $type, engagement: $engagement));

        return $engagement;
    }

    public function score(EngagementType $type): float
    {
        $type = TypeResolver::ensure(type: $type);

        throw_unless($this->engagementCarriesValue(type: $type), EngagementValueException::notAvailable(type: $type));

        return (float) $this->engagements()->whereType($type)->sum(column: 'value');
    }

    public function averageOf(EngagementType $type): float
    {
        $type = TypeResolver::ensure(type: $type);

        throw_unless($this->engagementCarriesValue(type: $type), EngagementValueException::notAvailable(type: $type));

        return (float) $this->engagements()->whereType($type)->avg(column: 'value');
    }

    public function disengage(EngagementType $type): void
    {
        $this->engagements()
            ->whereUserId(auth()->id())
            ->whereType($type)
            ->delete();

        event(new Disengaged(actor: auth()->user(), engageable: $this, type: $type));
    }

    public function engagementCount(EngagementType $type): int
    {
        return $this->engagements()
            ->whereType($type)
            ->count();
    }

    public function netScore(string $group): float
    {
        return (float) $this->engagements()
            ->whereIn('type', $this->exclusiveGroupValues(group: $group))
            ->sum(column: 'value');
    }

    /**
     * @return array<string, int>
     */
    public function breakdown(string $group): array
    {
        $rows = $this->engagements()
            ->whereIn('type', $this->exclusiveGroupValues(group: $group))
            ->toBase()
            ->selectRaw('type, count(*) as aggregate')
            ->groupBy('type')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $breakdown[(string) $row->type] = (int) $row->aggregate;
        }

        return $breakdown;
    }

    protected function engageExclusive(EngagementType&Exclusive $type, int|float|null $value): Engagement
    {
        return DB::transaction(fn (): Engagement => $this->flipExclusive(type: $type, value: $value));
    }

    protected function flipExclusive(EngagementType&Exclusive $type, int|float|null $value): Engagement
    {
        $existing = $this->engagements()
            ->whereUserId(auth()->id())
            ->whereIn('type', $this->exclusiveGroupValues(group: $type->group()))
            ->lockForUpdate()
            ->get();

        $active = $existing->first(fn (Engagement $engagement): bool => $engagement->type === $type);

        $existing->each(function (Engagement $engagement): void {
            $engagement->delete();

            event(new Disengaged(actor: auth()->user(), engageable: $this, type: $engagement->type));
        });

        if ($active instanceof Engagement) {
            return $active;
        }

        $engagement = $this->engagements()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'value' => $this->resolveEngagementValue(type: $type, value: $value),
        ]);

        event(new Engaged(actor: auth()->user(), engageable: $this, type: $type, engagement: $engagement));

        return $engagement;
    }

    /**
     * @return array<int, string>
     */
    protected function exclusiveGroupValues(string $group): array
    {
        $enum = TypeResolver::enum();

        return collect($enum::cases())
            ->filter(fn (EngagementType $case): bool => $case instanceof Exclusive && $case->group() === $group)
            ->map(fn (EngagementType $case): string => $case->value)
            ->values()
            ->all();
    }

    protected function resolveEngagementValue(EngagementType $type, int|float|null $value): int|float|null
    {
        throw_unless($value === null, EngagementValueException::notAccepted(type: $type));

        return $type instanceof HasWeight ? $type->weight() : null;
    }

    protected function engagementCarriesValue(EngagementType $type): bool
    {
        return $type instanceof HasWeight;
    }

    protected function hasEngagedWithType(EngagementType $type): bool
    {
        return $this->engagements()
            ->whereUserId(auth()->id())
            ->whereType($type)
            ->exists();
    }

    protected function getEngagementCacheKey(EngagementType $type): string
    {
        return "engagements.$type->value.$this->id";
    }

    protected function getEngagementCount(EngagementType $type, bool $showUsers = false): Collection|int
    {
        if ($showUsers) {
            return $this->engagements()
                ->with(relations: 'user')
                ->whereType($type)
                ->get()
                ->pluck('user')
                ->when(config(key: 'engageify.allow_multiple_engagements'), fn ($users) => $users->unique());
        }

        if (config(key: 'engageify.allow_caching')) {
            return cache()->remember(
                key: $this->getEngagementCacheKey($type),
                ttl: config(key: 'engageify.cache_duration'),
                callback: fn () => $this->engagementCount(type: $type)
            );
        }

        return $this->engagementCount(type: $type);
    }
}

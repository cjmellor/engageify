<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Contracts\EngagementType;
use Cjmellor\Engageify\Contracts\Exclusive;
use Cjmellor\Engageify\Contracts\HasWeight;
use Cjmellor\Engageify\Contracts\Rateable;
use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\Disengaged;
use Cjmellor\Engageify\Events\Engaged;
use Cjmellor\Engageify\Exceptions\EngagementValueException;
use Cjmellor\Engageify\Exceptions\InvalidRatingException;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Support\RatingScale;
use Cjmellor\Engageify\Support\TypeResolver;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * @return MorphMany<EngagementCounter, $this>
     */
    public function engagementCounters(): MorphMany
    {
        return $this->morphMany(related: EngagementCounter::class, name: 'engagementable');
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

        if ($type instanceof Rateable) {
            return $this->rate(type: $type, value: $value);
        }

        throw_if(
            config(key: 'engageify.allow_multiple_engagements') === false && $this->hasEngagedWithType(type: $type),
            UserCannotEngageException::class,
            'This model has already been engaged'
        );

        $resolved = $this->resolveEngagementValue(type: $type, value: $value);

        return DB::transaction(function () use ($type, $resolved): Engagement {
            $engagement = $this->engagements()->create([
                'user_id' => auth()->id(),
                'type' => $type,
                'value' => $resolved,
            ]);

            EngagementCounter::record(engageable: $this, type: $type, countDelta: 1, valueDelta: (float) ($resolved ?? 0));

            event(new Engaged(actor: auth()->user(), engageable: $this, type: $type, engagement: $engagement));

            return $engagement;
        });
    }

    public function score(EngagementType $type): float
    {
        $type = TypeResolver::ensure(type: $type);

        throw_unless($this->engagementCarriesValue(type: $type), EngagementValueException::notAvailable(type: $type));

        return $this->counterSum(type: $type);
    }

    public function averageOf(EngagementType $type): float
    {
        $type = TypeResolver::ensure(type: $type);

        throw_unless($this->engagementCarriesValue(type: $type), EngagementValueException::notAvailable(type: $type));

        $count = $this->counterCount(type: $type);

        return $count === 0 ? 0.0 : $this->counterSum(type: $type) / $count;
    }

    public function disengage(EngagementType $type): void
    {
        DB::transaction(function () use ($type): void {
            $engagements = $this->engagements()
                ->whereUserId(auth()->id())
                ->whereType($type)
                ->get();

            if ($engagements->isNotEmpty()) {
                $this->engagements()->whereUserId(auth()->id())->whereType($type)->delete();

                EngagementCounter::record(
                    engageable: $this,
                    type: $type,
                    countDelta: -$engagements->count(),
                    valueDelta: -(float) $engagements->sum(fn (Engagement $engagement): float => (float) $engagement->value),
                );
            }

            event(new Disengaged(actor: auth()->user(), engageable: $this, type: $type));
        });
    }

    public function engagementCount(EngagementType $type): int
    {
        return $this->counterCount(type: $type);
    }

    public function netScore(string $group): float
    {
        return (float) $this->engagementCounters()
            ->whereIn('type', $this->exclusiveGroupValues(group: $group))
            ->sum(column: 'sum_value');
    }

    /**
     * @return array<string, int>
     */
    public function breakdown(string $group): array
    {
        $counters = $this->engagementCounters()
            ->whereIn('type', $this->exclusiveGroupValues(group: $group))
            ->get();

        $breakdown = [];

        foreach ($counters as $counter) {
            $breakdown[$counter->type] = $counter->count;
        }

        return $breakdown;
    }

    public function averageRating(EngagementType $type): float
    {
        return $this->averageOf(type: $type);
    }

    public function ratingCount(EngagementType $type): int
    {
        return $this->engagementCount(type: $type);
    }

    /**
     * @return array<string, int>
     */
    public function ratingDistribution(EngagementType $type): array
    {
        return $this->engagements()
            ->whereType($type)
            ->get()
            ->groupBy(fn (Engagement $engagement): string => (string) $engagement->value)
            ->map(fn (Collection $group): int => $group->count())
            ->all();
    }

    public function bayesianAverage(EngagementType $type, ?int $m = null): float
    {
        $m ??= (int) config(key: 'engageify.bayesian_minimum');

        $count = $this->counterCount(type: $type);
        $sum = $this->counterSum(type: $type);

        $globalCount = (int) EngagementCounter::query()->where('type', $type->value)->sum(column: 'count');
        $globalSum = (float) EngagementCounter::query()->where('type', $type->value)->sum(column: 'sum_value');
        $globalMean = $globalCount === 0 ? 0.0 : $globalSum / $globalCount;

        $denominator = $m + $count;

        return $denominator === 0
            ? 0.0
            : ($m * $globalMean + $sum) / $denominator;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeOrderByEngagementCount(Builder $query, EngagementType $type, string $direction = 'desc'): Builder
    {
        $model = $query->getModel();

        return $query
            ->leftJoinSub(
                EngagementCounter::query()
                    ->select(['engagementable_id', 'count'])
                    ->where('engagementable_type', $model->getMorphClass())
                    ->where('type', $type->value),
                'engagement_counts',
                'engagement_counts.engagementable_id',
                '=',
                $model->getQualifiedKeyName(),
            )
            ->orderBy('engagement_counts.count', $direction)
            ->select("{$model->getTable()}.*");
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeOrderByScore(Builder $query, string $group, string $direction = 'desc'): Builder
    {
        $model = $query->getModel();

        return $query
            ->leftJoinSub(
                EngagementCounter::query()
                    ->selectRaw('engagementable_id, sum(sum_value) as group_score')
                    ->where('engagementable_type', $model->getMorphClass())
                    ->whereIn('type', $this->exclusiveGroupValues(group: $group))
                    ->groupBy('engagementable_id'),
                'engagement_scores',
                'engagement_scores.engagementable_id',
                '=',
                $model->getQualifiedKeyName(),
            )
            ->orderBy('engagement_scores.group_score', $direction)
            ->select("{$model->getTable()}.*");
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeHot(Builder $query, string $direction = 'desc'): Builder
    {
        $model = $query->getModel();

        return $query
            ->leftJoinSub(
                EngagementCounter::query()
                    ->selectRaw('engagementable_id, max(hot_score) as hot_score')
                    ->where('engagementable_type', $model->getMorphClass())
                    ->groupBy('engagementable_id'),
                'engagement_hot',
                'engagement_hot.engagementable_id',
                '=',
                $model->getQualifiedKeyName(),
            )
            ->orderBy('engagement_hot.hot_score', $direction)
            ->select("{$model->getTable()}.*");
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeTop(Builder $query, string $period = 'all', string $direction = 'desc'): Builder
    {
        $model = $query->getModel();

        if ($period !== 'all') {
            $query->where($model->getQualifiedCreatedAtColumn(), '>=', now()->sub("1 {$period}"));
        }

        return $query
            ->leftJoinSub(
                EngagementCounter::query()
                    ->selectRaw('engagementable_id, sum(sum_value) as top_score')
                    ->where('engagementable_type', $model->getMorphClass())
                    ->groupBy('engagementable_id'),
                'engagement_top',
                'engagement_top.engagementable_id',
                '=',
                $model->getQualifiedKeyName(),
            )
            ->orderBy('engagement_top.top_score', $direction)
            ->select("{$model->getTable()}.*");
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeOrderByBayesian(Builder $query, EngagementType $type, ?int $m = null, string $direction = 'desc'): Builder
    {
        $m ??= (int) config(key: 'engageify.bayesian_minimum');

        $globalCount = (int) EngagementCounter::query()->where('type', $type->value)->sum(column: 'count');
        $globalSum = (float) EngagementCounter::query()->where('type', $type->value)->sum(column: 'sum_value');
        $globalMean = $globalCount === 0 ? 0.0 : $globalSum / $globalCount;

        $model = $query->getModel();

        return $query
            ->leftJoinSub(
                EngagementCounter::query()
                    ->selectRaw('engagementable_id, (? * ? + sum_value) / (? + count) as bayesian', [$m, $globalMean, $m])
                    ->where('engagementable_type', $model->getMorphClass())
                    ->where('type', $type->value),
                'engagement_bayesian',
                'engagement_bayesian.engagementable_id',
                '=',
                $model->getQualifiedKeyName(),
            )
            ->orderBy('engagement_bayesian.bayesian', $direction)
            ->select("{$model->getTable()}.*");
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function scopeWithUserEngagement(Builder $query, EngagementType $type, ?Model $user = null): Builder
    {
        $userKey = $user?->getKey() ?? auth()->id();

        $model = $query->getModel();

        $query
            ->addSelect("{$model->getTable()}.*")
            ->addSelect(['is_engaged' => $this->userEngagementSubquery(model: $model, type: $type, userKey: $userKey)->selectRaw('count(*)')])
            ->withCasts(['is_engaged' => 'boolean']);

        if ($type instanceof Rateable) {
            $query
                ->addSelect(['engagement_value' => $this->userEngagementSubquery(model: $model, type: $type, userKey: $userKey)->select('value')->limit(1)])
                ->withCasts(['engagement_value' => 'decimal:2']);
        }

        return $query;
    }

    /**
     * @return Builder<Engagement>
     */
    protected function userEngagementSubquery(Model $model, EngagementType $type, mixed $userKey): Builder
    {
        return Engagement::query()
            ->whereColumn('engagementable_id', $model->getQualifiedKeyName())
            ->where('engagementable_type', $model->getMorphClass())
            ->where('type', $type->value)
            ->where('user_id', $userKey);
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

            EngagementCounter::record(engageable: $this, type: $engagement->type, countDelta: -1, valueDelta: -(float) $engagement->value);

            event(new Disengaged(actor: auth()->user(), engageable: $this, type: $engagement->type));
        });

        if ($active instanceof Engagement) {
            return $active;
        }

        $resolved = $this->resolveEngagementValue(type: $type, value: $value);

        $engagement = $this->engagements()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'value' => $resolved,
        ]);

        EngagementCounter::record(engageable: $this, type: $type, countDelta: 1, valueDelta: (float) ($resolved ?? 0));

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

    protected function rate(EngagementType&Rateable $type, int|float|null $value): Engagement
    {
        throw_if($value === null, InvalidRatingException::valueRequired(type: $type));

        $validated = RatingScale::validate(type: $type, value: $value);

        return DB::transaction(function () use ($type, $validated): Engagement {
            $existing = $this->engagements()
                ->whereUserId(auth()->id())
                ->whereType($type)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Engagement) {
                $previous = (float) $existing->value;

                $existing->update(['value' => $validated]);

                EngagementCounter::record(engageable: $this, type: $type, countDelta: 0, valueDelta: $validated - $previous);

                $engagement = $existing;
            } else {
                $engagement = $this->engagements()->create([
                    'user_id' => auth()->id(),
                    'type' => $type,
                    'value' => $validated,
                ]);

                EngagementCounter::record(engageable: $this, type: $type, countDelta: 1, valueDelta: $validated);
            }

            event(new Engaged(actor: auth()->user(), engageable: $this, type: $type, engagement: $engagement));

            return $engagement;
        });
    }

    protected function resolveEngagementValue(EngagementType $type, int|float|null $value): int|float|null
    {
        throw_unless($value === null, EngagementValueException::notAccepted(type: $type));

        return $type instanceof HasWeight ? $type->weight() : null;
    }

    protected function engagementCarriesValue(EngagementType $type): bool
    {
        return $type instanceof HasWeight || $type instanceof Rateable;
    }

    protected function hasEngagedWithType(EngagementType $type): bool
    {
        return $this->engagements()
            ->whereUserId(auth()->id())
            ->whereType($type)
            ->exists();
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

        return $this->counterCount(type: $type);
    }

    protected function counterCount(EngagementType $type): int
    {
        return (int) $this->engagementCounters()
            ->where('type', $type->value)
            ->sum(column: 'count');
    }

    protected function counterSum(EngagementType $type): float
    {
        return (float) $this->engagementCounters()
            ->where('type', $type->value)
            ->sum(column: 'sum_value');
    }
}

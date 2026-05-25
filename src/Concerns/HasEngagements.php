<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\ModelDisengagedEvent;
use Cjmellor\Engageify\Events\ModelDislikedEvent;
use Cjmellor\Engageify\Events\ModelDownvotedEvent;
use Cjmellor\Engageify\Events\ModelLikedEvent;
use Cjmellor\Engageify\Events\ModelUpvotedEvent;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Concerns\HasRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasEngagements
{
    public function dislike(): Model
    {
        return $this->engage(type: EngagementTypes::Dislike);
    }

    public function engagements(): MorphMany
    {
        /** @var HasRelationships $this */
        return $this->morphMany(related: Engagement::class, name: 'engagementable');
    }

    public function upvote(): Model
    {
        return $this->engage(type: EngagementTypes::Upvote);
    }

    public function downvote(): Model
    {
        return $this->engage(type: EngagementTypes::Downvote);
    }

    public function likes($showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: EngagementTypes::Like, showUsers: $showUsers);
    }

    public function dislikes($showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: EngagementTypes::Dislike, showUsers: $showUsers);
    }

    public function upvotes($showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: EngagementTypes::Upvote, showUsers: $showUsers);
    }

    public function downvotes($showUsers = false): Collection|int
    {
        return $this->getEngagementCount(type: EngagementTypes::Downvote, showUsers: $showUsers);
    }

    public function toggleLike(): void
    {
        $this->hasEngagedWithType(type: EngagementTypes::Like)
            ? $this->unlike()
            : $this->like();
    }

    public function unlike(): void
    {
        $this->disengage(type: EngagementTypes::Like);
    }

    public function like(): Model
    {
        return $this->engage(type: EngagementTypes::Like);
    }

    protected function engage(EngagementTypes $type): Model
    {
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
        ]);

        match ($type) {
            EngagementTypes::Like => event(new ModelLikedEvent(auth()->user(), $this, $engagement)),
            EngagementTypes::Dislike => event(new ModelDislikedEvent(auth()->user(), $this, $engagement)),
            EngagementTypes::Upvote => event(new ModelUpvotedEvent(auth()->user(), $this, $engagement)),
            EngagementTypes::Downvote => event(new ModelDownvotedEvent(auth()->user(), $this, $engagement)),
        };

        return $engagement;
    }

    protected function hasEngagedWithType(EngagementTypes $type): bool
    {
        return $this->engagements()
            ->whereUserId(auth()->id())
            ->whereType($type)
            ->exists();
    }

    protected function getEngagementCacheKey(EngagementTypes $type): string
    {
        return "engagements.$type->value.$this->id";
    }

    protected function getEngagementCount(EngagementTypes $type, $showUsers = false): Collection|int
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

    protected function engagementCount(EngagementTypes $type): int
    {
        return $this->engagements()
            ->whereType($type)
            ->count();
    }

    protected function disengage(EngagementTypes $type): void
    {
        $this->engagements()
            ->whereUserId(auth()->id())
            ->whereType($type)
            ->delete();

        event(new ModelDisengagedEvent(auth()->user(), $this));
    }
}

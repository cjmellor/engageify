<?php

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\ModelDisengagedEvent;
use Cjmellor\Engageify\Events\ModelDislikedEvent;
use Cjmellor\Engageify\Events\ModelDownvotedEvent;
use Cjmellor\Engageify\Events\ModelLikedEvent;
use Cjmellor\Engageify\Events\ModelUpvotedEvent;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

trait HasEngagements
{
    public function dislike(): Model
    {
        return $this->engage(type: EngagementTypes::Dislike);
    }

    protected function engage(EngagementTypes $type): Model
    {
        throw_if(
            condition: config(key: 'engageify.allow_multiple_engagements') === false && $this->hasEngagedWithType(type: $type),
            exception: new UserCannotEngageException(message: 'This model has already been engaged')
        );

        if (config(key: 'engageify.allow_caching')) {
            cache()->forget(key: $this->getEngagementCacheKey($type));
        }

        $engagement = $this->engagements()->create([
            'user_id' => auth()->id(),
            'type' => $type,
        ]);

        match ($type) {
            EngagementTypes::Like => ModelLikedEvent::dispatch(auth()->user(), $this, $engagement),
            EngagementTypes::Dislike => ModelDislikedEvent::dispatch(auth()->user(), $this, $engagement),
            EngagementTypes::Upvote => ModelUpvotedEvent::dispatch(auth()->user(), $this, $engagement),
            EngagementTypes::Downvote => ModelDownvotedEvent::dispatch(auth()->user(), $this, $engagement),
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

    public function engagements(): MorphMany
    {
        /** @var \Illuminate\Database\Eloquent\Concerns\HasRelationships $this */
        return $this->morphMany(related: Engagement::class, name: 'engagementable');
    }

    protected function getEngagementCacheKey(EngagementTypes $type): string
    {
        return "engagements.$type->value.$this->id";
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

    protected function getEngagementCount(EngagementTypes $type, $showUsers = false): Collection|int
    {
        if (config(key: 'engageify.allow_caching')) {
            return cache()->remember(
                key: $this->getEngagementCacheKey($type),
                ttl: config(key: 'engageify.cache_duration'),
                callback: fn () => $this->engagementCount(type: $type)
            );
        }

        if ($showUsers) {
            // If caching is enabled, you might still be only seeing the cached count
            // This is because the cache is only cleared when an engagement is made
            if (config(key: 'engageify.allow_caching')) {
                cache()->forget(key: $this->getEngagementCacheKey($type));
            }

            return $this->engagements()
                ->with(relations: 'user')
                ->whereType($type)
                ->get()
                ->pluck('user')
                ->when(config(key: 'engageify.allow_multiple_engagements'), fn ($users) => $users->unique());
        }

        return $this->engagementCount(type: $type);
    }

    protected function engagementCount(EngagementTypes $type): int
    {
        return $this->engagements()
            ->whereType($type)
            ->count();
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

    protected function disengage(EngagementTypes $type): void
    {
        $this->engagements()
            ->whereUserId(auth()->id())
            ->whereType($type)
            ->delete();

        ModelDisengagedEvent::dispatch(auth()->user(), $this);
    }

    public function like(): Model
    {
        return $this->engage(type: EngagementTypes::Like);
    }
}

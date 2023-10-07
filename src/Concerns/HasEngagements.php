<?php

namespace Cjmellor\Engageify\Concerns;

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
            cache()->forget(key: "engagements.$type->value.$this->id");
        }

        return $this->engagements()->create([
            'user_id' => auth()->id(),
            'type' => $type,
        ]);
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

    public function upvote(): Model
    {
        return $this->engage(type: EngagementTypes::Upvote);
    }

    public function downvote(): Model
    {
        return $this->engage(type: EngagementTypes::Downvote);
    }

    public function likes(): int
    {
        return $this->getEngagementCount(type: EngagementTypes::Like);
    }

    protected function getEngagementCount(EngagementTypes $type): int
    {
        if (config(key: 'engageify.allow_caching')) {
            return cache()->remember(
                key: "engagements.$type->value.$this->id",
                ttl: config(key: 'engageify.cache_duration'),
                callback: fn () => $this->engagementCount(type: $type)
            );
        }

        return $this->engagementCount(type: $type);
    }

    protected function engagementCount(EngagementTypes $type): int
    {
        return $this->engagements()
            ->whereType($type->value)
            ->count();
    }

    public function dislikes(): int
    {
        return $this->getEngagementCount(type: EngagementTypes::Dislike);
    }

    public function upvotes(): int
    {
        return $this->getEngagementCount(type: EngagementTypes::Upvote);
    }

    public function downvotes(): int
    {
        return $this->getEngagementCount(type: EngagementTypes::Downvote);
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
    }

    public function like(): Model
    {
        return $this->engage(type: EngagementTypes::Like);
    }
}

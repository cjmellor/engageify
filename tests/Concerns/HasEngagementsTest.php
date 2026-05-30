<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\Disengaged;
use Cjmellor\Engageify\Events\Engaged;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

test(description: 'a Model can be engaged', closure: function (string $type): void {
    $this->actingAs($this->user);

    $this->user->{$type}();

    expect($this->user)->engagements->toHaveCount(count: 1);

    $this->assertDatabaseHas(table: Engagement::class, data: [
        'engagementable_id' => $this->user->id,
        'engagementable_type' => User::class,
        'type' => $type,
    ]);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'a non-logged in User cannot engage with a Model')
    ->defer(fn () => $this->user->like())
    ->throws(Exception::class);

test(description: 'a User cannot engage with a Model twice', closure: function (string $type): void {
    config(['engageify.allow_multiple_engagements' => false]);

    $this->actingAs($this->user);

    $this->user->{$type}();
    $this->user->{$type}();
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
])->throws(exception: UserCannotEngageException::class, exceptionMessage: 'This model has already been engaged');

it(description: 'counts the correct number of engagements', closure: function (string $type): void {
    config(['engageify.allow_multiple_engagements' => true]);

    $this->actingAs($this->user);

    $this->user->{$type}();
    $this->user->{$type}();

    $types = $type.'s';
    expect($this->user)->{$types}()->toBe(expected: 2);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'a Model that has been liked, can be disliked', closure: function (): void {
    $this->actingAs($this->user);

    $this->user->like();
    $this->user->unlike();

    expect($this->user)->likes()->toBe(expected: 0);

    $this->assertDatabaseCount(table: Engagement::class, count: 0);
});

test(description: 'a Like can be toggled', closure: function (): void {
    $this->actingAs($this->user);

    $this->user->like();
    $this->user->toggleLike();

    expect($this->user)->likes()->toBe(expected: 0);

    $this->assertDatabaseCount(table: Engagement::class, count: 0);
});

test(description: 'engaging a Model dispatches the generic Engaged event carrying the actor, engageable, Verb and engagement', closure: function (string $type): void {
    Event::fake();

    $this->actingAs($this->user);

    $this->user->{$type}();

    Event::assertDispatched(event: Engaged::class, callback: fn (Engaged $event): bool => $event->actor->is($this->user)
        && $event->engageable->is($this->user)
        && $event->type === EngagementTypes::from($type)
        && $event->engagement->engagementable->is($this->user));
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'disengaging a Model dispatches the generic Disengaged event carrying the actor, engageable and Verb', closure: function (): void {
    Event::fake();

    $this->actingAs($this->user);

    $this->user->like();
    $this->user->unlike();

    Event::assertDispatched(event: Disengaged::class, callback: fn (Disengaged $event): bool => $event->actor->is($this->user)
        && $event->engageable->is($this->user)
        && $event->type === EngagementTypes::Like);
});

test(description: 'retrieve unique list of Users\' who engaged with a Model', closure: function (string $type): void {
    config(['engageify.allow_multiple_engagements' => true]);

    config(['engageify.users.model' => User::class]);

    $this->actingAs($this->user);

    $user2 = User::factory()->createOne();

    $this->user->{$type}();

    $this->actingAs($user2);

    $this->user->{$type}();
    $this->user->{$type}();

    $types = $type.'s';

    expect($this->user)->{$types}(showUsers: true)->toBeInstanceOf(class: Collection::class)
        ->and($this->user)->{$types}(showUsers: true)->toHaveCount(count: 2);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'engagement counts are cached when caching is enabled', closure: function (): void {
    config(['engageify.allow_caching' => true]);

    $this->actingAs($this->user);

    $this->user->like();

    $cacheKey = "engagements.like.{$this->user->id}";

    expect(cache()->has(key: $cacheKey))->toBeFalse();

    expect($this->user->likes())->toBe(expected: 1)
        ->and(cache()->has(key: $cacheKey))->toBeTrue();
});

test(description: 'the cached count is cleared when a new engagement is made', closure: function (): void {
    config(['engageify.allow_caching' => true]);
    config(['engageify.allow_multiple_engagements' => true]);

    $this->actingAs($this->user);

    $this->user->like();
    expect($this->user->likes())->toBe(expected: 1);

    $this->user->like();
    expect($this->user->likes())->toBe(expected: 2);
});

test(description: 'toggleLike likes a Model that has not been liked yet', closure: function (): void {
    $this->actingAs($this->user);

    $this->user->toggleLike();

    expect($this->user)->likes()->toBe(expected: 1);
    $this->assertDatabaseCount(table: Engagement::class, count: 1);
});

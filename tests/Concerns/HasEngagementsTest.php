<?php

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

test(description: 'a Model can be engaged', closure: function (string $type): void {
    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model
    $this->user->{$type}();

    // The Model should have one engagement
    expect($this->user)->engagements->toHaveCount(count: 1);

    // Check the database has the correct data
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
    // Engage with the Model, without being authenticated
    ->defer(fn () => $this->user->like())
    // Exception should be thrown
    ->throws(Exception::class);

test(description: 'a User cannot engage with a Model twice', closure: function (string $type): void {
    // Turn off multiple engagements
    config(['engageify.allow_multiple_engagements' => false]);

    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model, twice
    $this->user->{$type}();
    $this->user->{$type}();
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
    // The custom Exception should be thrown with a message
])->throws(exception: UserCannotEngageException::class, exceptionMessage: 'This model has already been engaged');

it(description: 'counts the correct number of engagements', closure: function (string $type): void {
    // Turn on multiple engagements
    config(['engageify.allow_multiple_engagements' => true]);

    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model, twice
    $this->user->{$type}();
    $this->user->{$type}();

    // The Model should have two engagements
    $types = $type.'s';
    expect($this->user)->{$types}()->toBe(expected: 2);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'a Model that has been liked, can be disliked', closure: function (): void {
    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model
    $this->user->like();
    // Disengage with the Model
    $this->user->unlike();

    // The Model should have no engagements
    expect($this->user)->likes()->toBe(expected: 0);

    // The database should be empty
    $this->assertDatabaseCount(table: Engagement::class, count: 0);
});

test(description: 'a Like can be toggled', closure: function (): void {
    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model
    $this->user->like();
    // Toggle the Like
    $this->user->toggleLike();

    // The Model should have no engagements
    expect($this->user)->likes()->toBe(expected: 0);

    // The database should be empty
    $this->assertDatabaseCount(table: Engagement::class, count: 0);
});

test(description: 'when a Model is Engaged, the appropriate Event will run', closure: function (string $type): void {
    // Using Events, so fake them
    Event::fake();

    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model
    $this->user->{$type}();

    $eventName = sprintf('Cjmellor\\Engageify\\Events\\Model%sdEvent', ucfirst($type));

    // Assert the event ran
    Event::assertDispatched(event: $eventName);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test('events return with data', closure: function (string $type): void {
    // Using Events, so fake them
    Event::fake();

    // A User must be authenticated
    $this->actingAs($this->user);

    // Engage with the Model
    $this->user->{$type}();

    $eventName = sprintf('Cjmellor\\Engageify\\Events\\Model%sdEvent', ucfirst($type));

    // Assert the event ran and returned the correct data
    Event::assertDispatched(event: $eventName, callback: function ($event): bool {
        return $event->user->is($this->user)
            && $event->engageable->is($this->user)
            && $event->engagement->engagementable->is($this->user);
    });
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'retrieve unique list of Users\' who engaged with a Model', closure: function (string $type) {
    // Turn on multiple engagements
    config(['engageify.allow_multiple_engagements' => true]);

    // Config the User model
    config(['engageify.users.model' => User::class]);

    // A User must be authenticated
    $this->actingAs($this->user);

    // Generate a second User
    $user2 = User::factory()->createOne();

    // User one engages with the Model
    $this->user->{$type}();

    // Now login as User two
    $this->actingAs($user2);

    // User two engaged with User one, twice (to check uniqueness)
    $this->user->{$type}();
    $this->user->{$type}();

    $types = $type.'s';

    // Show the Users' who liked Model one and should be an instance of Collection
    expect($this->user)->{$types}(showUsers: true)->toBeInstanceOf(class: Collection::class)
        ->and($this->user)->{$types}(showUsers: true)->toHaveCount(count: 2);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

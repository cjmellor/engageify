<?php

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

test(description: 'a Model can be engaged', closure: function (string $type) {
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

test(description: 'a User cannot engage with a Model twice', closure: function (string $type) {
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

it(description: 'counts the correct number of engagements', closure: function (string $type) {
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

test(description: 'a Model that has been liked, can be disliked', closure: function () {
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

test(description: 'a Like can be toggled', closure: function () {
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

it(description: 'retrieves engagement counts from the cache', closure: function () {
    // Turn caching on
    config(['engageify.allow_caching' => true]);

    // An authenticated user is required
    $this->actingAs($this->user);

    // Engage with the Model first
    $this->user->like();

    // Retrieve the engagement count, which will cache the results
    $this->user->likes();

    // Because it's cached, a database query should not be made
    // If a query is run -- mimicking a database query -- the test will fail
    DB::listen(fn () => $this->fail(message: 'A database query was made when it should not have been'));

    // Retrieve the engagement count again, which should be retrieved from the cache
    expect($this->user)->likes()->toBe(expected: 1);
});

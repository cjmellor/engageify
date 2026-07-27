<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\Disengaged;
use Cjmellor\Engageify\Events\Engaged;
use Cjmellor\Engageify\Exceptions\UserCannotEngageException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Ballot;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->actor = User::factory()->createOne();
});

test(description: 'an explicit actor is written to the engagement, not the authenticated user', closure: function (string $type): void {
    $this->actingAs($this->user);

    $this->user->{$type}(actor: $this->actor);

    $this->assertDatabaseHas(table: Engagement::class, data: [
        'engagementable_id' => $this->user->id,
        'engagementable_type' => User::class,
        'user_id' => $this->actor->id,
        'type' => $type,
    ]);
})->with([
    EngagementTypes::Like->value,
    EngagementTypes::Dislike->value,
    EngagementTypes::Upvote->value,
    EngagementTypes::Downvote->value,
]);

test(description: 'an explicit actor can engage with no authenticated user at all', closure: function (): void {
    $this->user->engage(type: EngagementTypes::Like, actor: $this->actor);

    $this->assertDatabaseHas(table: Engagement::class, data: [
        'user_id' => $this->actor->id,
        'type' => EngagementTypes::Like->value,
    ]);
});

test(description: 'engaging without an actor and without an authenticated user throws')
    ->defer(fn () => $this->user->engage(type: EngagementTypes::Like))
    ->throws(exception: UserCannotEngageException::class, exceptionMessage: 'No actor was given and no user is authenticated');

test(description: 'disengaging without an actor and without an authenticated user throws')
    ->defer(fn () => $this->user->disengage(type: EngagementTypes::Like))
    ->throws(exception: UserCannotEngageException::class);

test(description: 'events carry the explicit actor', closure: function (): void {
    Event::fake();

    $this->actingAs($this->user);

    $this->user->like(actor: $this->actor);
    $this->user->unlike(actor: $this->actor);

    Event::assertDispatched(event: Engaged::class, callback: fn (Engaged $event): bool => $event->actor->is($this->actor));
    Event::assertDispatched(event: Disengaged::class, callback: fn (Disengaged $event): bool => $event->actor->is($this->actor));
});

test(description: 'disengaging only removes the given actor\'s engagements', closure: function (): void {
    config(['engageify.allow_multiple_engagements' => false]);

    $this->user->like(actor: $this->actor);
    $this->user->like(actor: $this->user);

    $this->user->unlike(actor: $this->actor);

    expect($this->user->engagements()->pluck('user_id')->all())->toBe([$this->user->id]);
});

test(description: 'the per-actor engagement guard is scoped to the actor', closure: function (): void {
    config(['engageify.allow_multiple_engagements' => false]);

    $this->user->like(actor: $this->actor);
    $this->user->like(actor: $this->user);

    expect($this->user->engagements()->count())->toBe(2);
});

test(description: 'toggleLike honours the explicit actor', closure: function (): void {
    $this->user->toggleLike(actor: $this->actor);

    expect($this->user->engagements()->count())->toBe(1);

    $this->user->toggleLike(actor: $this->actor);

    expect($this->user->engagements()->count())->toBe(0);
});

test(description: 'exclusive engagements flip per actor', closure: function (): void {
    config(['engageify.types' => Ballot::class]);

    $this->user->engage(type: Ballot::Down, actor: $this->actor);
    $this->user->engage(type: Ballot::Down, actor: $this->user);
    $this->user->engage(type: Ballot::Up, actor: $this->actor);

    expect($this->user->engagements()->whereUserId($this->actor->id)->pluck('type')->all())->toBe([Ballot::Up])
        ->and($this->user->engagements()->whereUserId($this->user->id)->pluck('type')->all())->toBe([Ballot::Down]);
});

test(description: 'exclusive engagements dispatch events carrying the explicit actor', closure: function (): void {
    config(['engageify.types' => Ballot::class]);

    Event::fake();

    $this->user->engage(type: Ballot::Down, actor: $this->actor);
    $this->user->engage(type: Ballot::Up, actor: $this->actor);

    Event::assertDispatched(event: Disengaged::class, callback: fn (Disengaged $event): bool => $event->actor->is($this->actor));
    Event::assertDispatched(event: Engaged::class, callback: fn (Engaged $event): bool => $event->actor->is($this->actor));
});

test(description: 'ratings are recorded and updated per actor', closure: function (): void {
    config(['engageify.types' => Rating::class]);

    $this->user->engage(type: Rating::Stars, value: 4, actor: $this->actor);
    $this->user->engage(type: Rating::Stars, value: 2, actor: $this->user);
    $this->user->engage(type: Rating::Stars, value: 5, actor: $this->actor);

    expect($this->user->engagements()->whereUserId($this->actor->id)->count())->toBe(1)
        ->and((float) $this->user->engagements()->whereUserId($this->actor->id)->value('value'))->toBe(5.0)
        ->and((float) $this->user->engagements()->whereUserId($this->user->id)->value('value'))->toBe(2.0);
});

test(description: 'ratings dispatch the Engaged event carrying the explicit actor', closure: function (): void {
    config(['engageify.types' => Rating::class]);

    Event::fake();

    $this->user->engage(type: Rating::Stars, value: 4, actor: $this->actor);

    Event::assertDispatched(event: Engaged::class, callback: fn (Engaged $event): bool => $event->actor->is($this->actor));
});

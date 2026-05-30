<?php

declare(strict_types=1);

use Cjmellor\Engageify\Events\Disengaged;
use Cjmellor\Engageify\Events\Engaged;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Ballot;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Mood;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    config(['engageify.types' => Ballot::class]);

    $this->actingAs($this->user);
});

test('switching within a group deletes the old member and inserts the new, never two rows', function (): void {
    $this->user->engage(Ballot::Down);
    $this->user->engage(Ballot::Up);

    expect($this->user->engagements()->count())->toBe(1)
        ->and($this->user->engagements()->first()->type)->toBe(Ballot::Up);
});

test('re-recording the active member toggles it off', function (): void {
    $this->user->engage(Ballot::Up);
    $this->user->engage(Ballot::Up);

    expect($this->user->engagements()->count())->toBe(0);
});

test('switching fires Disengaged for the cleared member and Engaged for the new', function (): void {
    Event::fake();

    $this->user->engage(Ballot::Down);
    $this->user->engage(Ballot::Up);

    Event::assertDispatched(Disengaged::class, fn (Disengaged $event): bool => $event->type === Ballot::Down);
    Event::assertDispatched(Engaged::class, fn (Engaged $event): bool => $event->type === Ballot::Up);
});

test('netScore sums the weights across a group', function (): void {
    $target = $this->user;
    $voterA = User::factory()->createOne();
    $voterB = User::factory()->createOne();

    $this->actingAs($voterA);
    $target->engage(Ballot::Up);

    $this->actingAs($voterB);
    $target->engage(Ballot::Up);

    $this->actingAs($target);
    $target->engage(Ballot::Down);

    expect($target->netScore('ballot'))->toBe(1.0);
});

test('breakdown returns per-member counts across a group', function (): void {
    $target = $this->user;
    $voterA = User::factory()->createOne();

    $this->actingAs($voterA);
    $target->engage(Ballot::Up);

    $this->actingAs($target);
    $target->engage(Ballot::Down);

    expect($target->breakdown('ballot'))
        ->toHaveKey('ballot_up', 1)
        ->toHaveKey('ballot_down', 1);
});

test('a vote switch is atomic — a failure during the flip rolls back, leaving the original vote intact', function (): void {
    $this->user->engage(Ballot::Down);

    Event::listen(Engaged::class, function (): void {
        throw new RuntimeException('listener boom');
    });

    expect(fn (): mixed => $this->user->engage(Ballot::Up))
        ->toThrow(exception: RuntimeException::class);

    expect($this->user->engagements()->count())->toBe(1)
        ->and($this->user->engagements()->first()->type)->toBe(Ballot::Down);
});

test('a non-weighted reaction group switches between members and carries no value', function (): void {
    config(['engageify.types' => Mood::class]);

    $target = $this->user;
    $voterA = User::factory()->createOne();

    $this->actingAs($voterA);
    $target->engage(Mood::Happy);

    $this->actingAs($target);
    $target->engage(Mood::Sad);
    $target->engage(Mood::Angry);

    expect($target->engagements()->count())->toBe(2)
        ->and($target->breakdown('mood'))->toHaveKey('happy', 1)
        ->and($target->breakdown('mood'))->toHaveKey('angry', 1)
        ->and($target->netScore('mood'))->toBe(0.0)
        ->and($target->engagements()->whereType(Mood::Angry)->first()->value)->toBeNull();
});

<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Events\Disengaged;
use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Ballot;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Vote;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->actingAs($this->user);
});

test('engaging increments the counter and disengaging decrements it — no stale count after unlike', function (): void {
    $this->user->like();

    expect($this->user->likes())->toBe(1)
        ->and($this->user->engagementCount(EngagementTypes::Like))->toBe(1);

    $this->user->unlike();

    expect($this->user->likes())->toBe(0);

    $this->assertDatabaseHas(EngagementCounter::class, [
        'type' => 'like',
        'count' => 0,
    ]);
});

test('a counter decremented below zero is stored without an out-of-range error and recount repairs it', function (): void {
    config(['engageify.types' => Vote::class]);

    EngagementCounter::record(engageable: $this->user, type: Vote::Up->value, countDelta: -1, valueDelta: -1.0);

    expect(EngagementCounter::query()->where('type', Vote::Up->value)->firstOrFail()->count)->toBe(-1);

    EngagementCounter::rebuild();

    $this->assertDatabaseCount(EngagementCounter::class, 0);
});

test('a counter belongs to its engageable', function (): void {
    $this->user->like();

    $counter = EngagementCounter::query()->firstOrFail();

    expect($counter->engagementable->is($this->user))->toBeTrue();
});

test('a weighted Verb keeps sum_value in step and score reads it', function (): void {
    config(['engageify.types' => Vote::class, 'engageify.allow_multiple_engagements' => true]);

    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Up);

    expect($this->user->score(Vote::Up))->toBe(2.0)
        ->and($this->user->engagementCount(Vote::Up))->toBe(2);
});

test('a flip keeps the counter in step for both members', function (): void {
    config(['engageify.types' => Ballot::class]);

    $this->user->engage(Ballot::Down);
    $this->user->engage(Ballot::Up);

    expect($this->user->engagementCount(Ballot::Down))->toBe(0)
        ->and($this->user->engagementCount(Ballot::Up))->toBe(1)
        ->and($this->user->netScore('ballot'))->toBe(1.0);
});

test('re-rating updates sum_value without changing the count', function (): void {
    config(['engageify.types' => Rating::class]);

    $this->user->engage(Rating::Stars, 4);

    expect($this->user->score(Rating::Stars))->toBe(4.0)
        ->and($this->user->ratingCount(Rating::Stars))->toBe(1);

    $this->user->engage(Rating::Stars, 2);

    expect($this->user->score(Rating::Stars))->toBe(2.0)
        ->and($this->user->ratingCount(Rating::Stars))->toBe(1);
});

test('reads return zero for a Verb with no engagements', function (): void {
    config(['engageify.types' => Vote::class]);

    expect($this->user->engagementCount(Vote::Up))->toBe(0)
        ->and($this->user->score(Vote::Up))->toBe(0.0)
        ->and($this->user->averageOf(Vote::Up))->toBe(0.0);
});

test('disengaging when nothing is engaged fires no event and leaves the counter untouched', function (): void {
    Event::fake();

    $this->user->unlike();

    Event::assertNotDispatched(Disengaged::class);

    expect($this->user->likes())->toBe(0);

    $this->assertDatabaseCount(EngagementCounter::class, 0);
});

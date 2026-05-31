<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Vote;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->actor = $this->user;
    $this->target = User::factory()->createOne();

    $this->actingAs($this->actor);
});

test('hasEngaged reflects whether the actor engaged the target with a Verb', function (): void {
    $this->target->like();

    expect($this->actor->hasEngaged($this->target, EngagementTypes::Like))->toBeTrue()
        ->and($this->actor->hasEngaged($this->target, EngagementTypes::Dislike))->toBeFalse();
});

test('engagementValueFor returns the typed value of a weighted engagement', function (): void {
    config(['engageify.types' => Vote::class]);

    $this->target->engage(Vote::Up);

    expect($this->actor->engagementValueFor($this->target, Vote::Up))->toBe(1.0);
});

test('engagementValueFor returns null for a binary engagement', function (): void {
    $this->target->like();

    expect($this->actor->engagementValueFor($this->target, EngagementTypes::Like))->toBeNull();
});

test('ratingFor returns the actor\'s rating of the target', function (): void {
    config(['engageify.types' => Rating::class]);

    $this->target->engage(Rating::Stars, 4);

    expect($this->actor->ratingFor($this->target, Rating::Stars))->toBe(4.0);
});

test('the actor relation returns the engagements the actor authored', function (): void {
    $other = User::factory()->createOne();

    $this->target->like();
    $other->like();

    expect($this->actor->authoredEngagements)->toHaveCount(2);
});

test('withUserEngagement attaches feed-state for N rows in a single query', function (): void {
    $engaged = User::factory()->createOne();
    $notEngaged = User::factory()->createOne();

    $engaged->like();

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $feed = User::query()
        ->whereIn('id', [$engaged->id, $notEngaged->id])
        ->withUserEngagement(EngagementTypes::Like)
        ->get();

    expect($queries)->toBe(1)
        ->and($feed->firstWhere('id', $engaged->id)->is_engaged)->toBeTrue()
        ->and($feed->firstWhere('id', $notEngaged->id)->is_engaged)->toBeFalse();
});

test('withUserEngagement accepts an explicit actor', function (): void {
    $otherActor = User::factory()->createOne();

    $this->target->like();

    $forActor = User::query()->whereKey($this->target->id)->withUserEngagement(EngagementTypes::Like, $this->actor)->first();
    $forOther = User::query()->whereKey($this->target->id)->withUserEngagement(EngagementTypes::Like, $otherActor)->first();

    expect($forActor->is_engaged)->toBeTrue()
        ->and($forOther->is_engaged)->toBeFalse();
});

test('withUserEngagement attaches the typed value for a Rateable Verb', function (): void {
    config(['engageify.types' => Rating::class]);

    $this->target->engage(Rating::Stars, 4);

    $row = User::query()->whereKey($this->target->id)->withUserEngagement(Rating::Stars)->first();

    expect($row->is_engaged)->toBeTrue()
        ->and((float) $row->engagement_value)->toBe(4.0);
});

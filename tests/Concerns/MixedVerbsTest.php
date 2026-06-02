<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Exceptions\AmbiguousEngagementType;
use Cjmellor\Engageify\Support\TypeResolver;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Clap;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Mood;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Reaction;

beforeEach(function (): void {
    $this->actingAs($this->user);
});

test('a model can engage verbs from several registered enums without swapping config', function (): void {
    config(['engageify.types' => [Rating::class, Reaction::class]]);

    $this->user->engage(Rating::Stars, 4);
    $this->user->engage(Reaction::Bookmark);

    expect($this->user->engagementCount(Reaction::Bookmark))->toBe(1)
        ->and($this->user->averageRating(Rating::Stars))->toBe(4.0);
});

test('an exclusive verb flips correctly even when its enum is not first in the registry', function (): void {
    config(['engageify.types' => [Rating::class, Mood::class]]);

    $this->user->engage(Mood::Happy);
    $this->user->engage(Mood::Sad);

    expect($this->user->engagementCount(Mood::Happy))->toBe(0)
        ->and($this->user->engagementCount(Mood::Sad))->toBe(1)
        ->and($this->user->breakdown('mood'))->toMatchArray(['sad' => 1]);
});

test('registering enums that share a backed value is rejected with a clear error', function (): void {
    config(['engageify.types' => [Rating::class, Clap::class]]);

    expect(fn () => TypeResolver::assertUniqueValues())
        ->toThrow(AmbiguousEngagementType::class);
});

test('stored rows from different registered enums hydrate back to their own cases', function (): void {
    config(['engageify.types' => [Rating::class, Reaction::class]]);

    $this->user->engage(Rating::Stars, 3);
    $this->user->engage(Reaction::Bookmark);

    $types = $this->user->engagements()->orderBy('id')->get()->map(fn ($e) => $e->type);

    expect($types[0])->toBe(Rating::Stars)
        ->and($types[1])->toBe(Reaction::Bookmark);
});

test('the like() helper resolves its verb from whichever registered enum defines it', function (): void {
    config(['engageify.types' => [Rating::class, EngagementTypes::class]]);

    $this->user->like();

    expect($this->user->likes())->toBe(1);
});

test('a single enum (not wrapped in an array) still works', function (): void {
    config(['engageify.types' => Reaction::class]);

    $this->user->engage(Reaction::Bookmark);

    expect($this->user->engagementCount(Reaction::Bookmark))->toBe(1)
        ->and($this->user->engagements()->first()->type)->toBe(Reaction::Bookmark);
});

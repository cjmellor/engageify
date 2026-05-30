<?php

declare(strict_types=1);

use Cjmellor\Engageify\Exceptions\InvalidRatingException;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\User;

beforeEach(function (): void {
    config(['engageify.types' => Rating::class]);

    $this->actingAs($this->user);
});

test('a Rateable Verb stores a caller-supplied value validated against its scale', function (): void {
    $this->user->engage(Rating::Stars, 4);

    expect($this->user->engagements()->count())->toBe(1)
        ->and((float) $this->user->engagements()->first()->value)->toBe(4.0);
});

test('a rating outside the scale range throws', function (): void {
    expect(fn (): mixed => $this->user->engage(Rating::Stars, 6))
        ->toThrow(exception: InvalidRatingException::class);

    expect(fn (): mixed => $this->user->engage(Rating::Stars, 0))
        ->toThrow(exception: InvalidRatingException::class);
});

test('a rating that is off-step throws', function (): void {
    expect(fn (): mixed => $this->user->engage(Rating::Stars, 2.5))
        ->toThrow(exception: InvalidRatingException::class);
});

test('rating without a value throws', function (): void {
    expect(fn (): mixed => $this->user->engage(Rating::Stars))
        ->toThrow(exception: InvalidRatingException::class);
});

test('re-rating updates the existing row and ignores allow_multiple_engagements', function (): void {
    config(['engageify.allow_multiple_engagements' => false]);

    $this->user->engage(Rating::Stars, 4);
    $this->user->engage(Rating::Stars, 2);

    expect($this->user->engagements()->count())->toBe(1)
        ->and((float) $this->user->engagements()->first()->value)->toBe(2.0);
});

test('fractional ratings are supported on a continuous scale', function (): void {
    $target = $this->user;
    $raterA = User::factory()->createOne();
    $raterB = User::factory()->createOne();

    $this->actingAs($raterA);
    $target->engage(Rating::Quality, 4.2);

    $this->actingAs($raterB);
    $target->engage(Rating::Quality, 9.5);

    expect($target->ratingCount(Rating::Quality))->toBe(2)
        ->and(round($target->averageRating(Rating::Quality), 2))->toBe(6.85);
});

test('averageRating and ratingCount aggregate ratings across actors', function (): void {
    $target = $this->user;
    $raterA = User::factory()->createOne();
    $raterB = User::factory()->createOne();

    $this->actingAs($raterA);
    $target->engage(Rating::Stars, 5);

    $this->actingAs($raterB);
    $target->engage(Rating::Stars, 3);

    expect($target->averageRating(Rating::Stars))->toBe(4.0)
        ->and($target->ratingCount(Rating::Stars))->toBe(2);
});

test('ratingDistribution returns a histogram keyed by value', function (): void {
    $target = $this->user;
    $raterA = User::factory()->createOne();
    $raterB = User::factory()->createOne();
    $raterC = User::factory()->createOne();

    $this->actingAs($raterA);
    $target->engage(Rating::Stars, 5);

    $this->actingAs($raterB);
    $target->engage(Rating::Stars, 5);

    $this->actingAs($raterC);
    $target->engage(Rating::Stars, 3);

    expect($target->ratingDistribution(Rating::Stars))
        ->toHaveKey('5.00', 2)
        ->toHaveKey('3.00', 1);
});

test('bayesianAverage pulls a low-count item toward the global mean, and m is configurable', function (): void {
    $target = $this->user;
    $other = User::factory()->createOne();
    $rater = User::factory()->createOne();

    $this->actingAs($rater);
    $other->engage(Rating::Stars, 2);
    $target->engage(Rating::Stars, 5);

    expect(round($target->bayesianAverage(Rating::Stars, 10), 2))->toBe(3.64)
        ->and(round($target->bayesianAverage(Rating::Stars, 1), 2))->toBe(4.25);
});

test('bayesianAverage uses the configured minimum when m is omitted', function (): void {
    config(['engageify.bayesian_minimum' => 1]);

    $target = $this->user;
    $other = User::factory()->createOne();
    $rater = User::factory()->createOne();

    $this->actingAs($rater);
    $other->engage(Rating::Stars, 2);
    $target->engage(Rating::Stars, 5);

    expect(round($target->bayesianAverage(Rating::Stars), 2))->toBe(4.25);
});

test('bayesianAverage returns zero when there is no prior and no ratings', function (): void {
    expect($this->user->bayesianAverage(Rating::Stars, 0))->toBe(0.0);
});

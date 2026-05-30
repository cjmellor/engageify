<?php

declare(strict_types=1);

use Cjmellor\Engageify\Exceptions\EngagementValueException;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Reaction;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Vote;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config(['engageify.types' => Vote::class]);
    config(['engageify.allow_multiple_engagements' => true]);

    $this->actingAs($this->user);
});

test('a HasWeight Verb engages with its derived weight', function (): void {
    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Down);

    expect((float) $this->user->engagements()->where('type', Vote::Up->value)->first()->value)->toBe(1.0)
        ->and((float) $this->user->engagements()->where('type', Vote::Down->value)->first()->value)->toBe(-1.0);
});

test('a binary Verb stores a null value', function (): void {
    config(['engageify.types' => Reaction::class]);

    $this->user->engage(Reaction::Bookmark);

    expect($this->user->engagements()->first()->value)->toBeNull();
});

test('engage() throws when an explicit value is passed to a HasWeight Verb', function (): void {
    expect(fn (): Model => $this->user->engage(Vote::Up, 5))
        ->toThrow(exception: EngagementValueException::class);
});

test('engage() throws when an explicit value is passed to a binary Verb', function (): void {
    config(['engageify.types' => Reaction::class]);

    expect(fn (): Model => $this->user->engage(Reaction::Bookmark, 5))
        ->toThrow(exception: EngagementValueException::class);
});

test('score() sums the value across a Verb\'s engagements', function (): void {
    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Down);

    expect($this->user->score(Vote::Up))->toBe(2.0)
        ->and($this->user->score(Vote::Down))->toBe(-1.0);
});

test('averageOf() averages the value across a Verb\'s engagements', function (): void {
    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Down);

    expect($this->user->averageOf(Vote::Up))->toBe(1.0)
        ->and($this->user->averageOf(Vote::Down))->toBe(-1.0);
});

test('score() throws on a binary Verb', function (): void {
    config(['engageify.types' => Reaction::class]);

    expect(fn (): float => $this->user->score(Reaction::Bookmark))
        ->toThrow(exception: EngagementValueException::class);
});

test('averageOf() throws on a binary Verb', function (): void {
    config(['engageify.types' => Reaction::class]);

    expect(fn (): float => $this->user->averageOf(Reaction::Bookmark))
        ->toThrow(exception: EngagementValueException::class);
});

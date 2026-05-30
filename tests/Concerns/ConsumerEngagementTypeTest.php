<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Exceptions\UnknownEngagementType;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Reaction;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model;

beforeEach(function (): void {
    config(['engageify.types' => Reaction::class]);

    $this->actingAs($this->user);
});

test('a consumer-defined Verb can be engaged, read back as a typed enum, and counted', function (): void {
    $this->user->engage(Reaction::Bookmark);

    expect($this->user->engagements()->first()->type)->toBe(Reaction::Bookmark)
        ->and($this->user->engagementCount(Reaction::Bookmark))->toBe(expected: 1);

    $this->assertDatabaseHas(table: Engagement::class, data: [
        'engagementable_id' => $this->user->id,
        'engagementable_type' => User::class,
        'type' => 'bookmark',
    ]);
});

test('engage() rejects a Verb that does not belong to the configured enum', function (): void {
    config(['engageify.types' => EngagementTypes::class]);

    expect(fn (): Model => $this->user->engage(Reaction::Bookmark))
        ->toThrow(exception: UnknownEngagementType::class);
});

test('a default wrapper throws when the configured enum lacks that Verb', function (): void {
    expect(fn (): Model => $this->user->like())
        ->toThrow(exception: UnknownEngagementType::class);
});

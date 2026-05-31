<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Ballot;
use Cjmellor\Engageify\Tests\Fixtures\User;

test('orderByEngagementCount sorts engageables by their counter in SQL', function (): void {
    config(['engageify.allow_multiple_engagements' => true]);

    $popular = User::factory()->createOne();
    $quiet = User::factory()->createOne();

    $this->actingAs($this->user);

    foreach (range(1, 3) as $ignored) {
        $popular->like();
    }

    $quiet->like();

    $ordered = User::query()->orderByEngagementCount(EngagementTypes::Like)->pluck('id');

    expect($ordered->take(2)->all())->toBe([$popular->id, $quiet->id]);
});

test('orderByScore sorts engageables by their net group score in SQL', function (): void {
    config(['engageify.types' => Ballot::class]);

    $winner = User::factory()->createOne();
    $loser = User::factory()->createOne();
    $voterA = User::factory()->createOne();
    $voterB = User::factory()->createOne();

    $this->actingAs($voterA);
    $winner->engage(Ballot::Up);

    $this->actingAs($voterB);
    $winner->engage(Ballot::Up);

    $this->actingAs($voterA);
    $loser->engage(Ballot::Down);

    $ordered = User::query()->orderByScore('ballot')->pluck('id');

    expect($ordered->take(2)->all())->toBe([$winner->id, $loser->id]);
});

<?php

declare(strict_types=1);

use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Vote;

test('engageify:recount rebuilds the counters from the source rows', function (): void {
    config(['engageify.types' => Vote::class, 'engageify.allow_multiple_engagements' => true]);

    $this->actingAs($this->user);

    $this->user->engage(Vote::Up);
    $this->user->engage(Vote::Up);

    EngagementCounter::query()->update(['count' => 99, 'sum_value' => 99]);

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($this->user->engagementCount(Vote::Up))->toBe(2)
        ->and($this->user->score(Vote::Up))->toBe(2.0);
});

test('engageify:recount recomputes hot_score so hot ranking survives a backfill', function (): void {
    config(['engageify.types' => Vote::class, 'engageify.allow_multiple_engagements' => true]);

    $this->actingAs($this->user);

    foreach (range(1, 5) as $ignored) {
        $this->user->engage(Vote::Up);
    }

    $find = fn (): EngagementCounter => EngagementCounter::query()
        ->where('engagementable_id', $this->user->id)
        ->where('type', Vote::Up->value)
        ->firstOrFail();

    $expected = (float) $find()->hot_score;

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($expected)->toBeGreaterThan(0.0)
        ->and((float) $find()->hot_score)->toBe($expected);
});

<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Vote;
use Cjmellor\Engageify\Tests\Fixtures\User;

beforeEach(function (): void {
    $this->actingAs($this->user);
});

test('hot_score is computed when a weighted score changes and orders hot()', function (): void {
    config(['engageify.types' => Vote::class, 'engageify.allow_multiple_engagements' => true]);

    $hotter = User::factory()->createOne();
    $cooler = User::factory()->createOne();

    foreach (range(1, 5) as $ignored) {
        $hotter->engage(Vote::Up);
    }

    $cooler->engage(Vote::Up);

    $ordered = User::query()->whereIn('id', [$hotter->id, $cooler->id])->hot()->pluck('id');

    expect($ordered->all())->toBe([$hotter->id, $cooler->id]);
});

test('hot_score stays zero for a binary Verb and resets to zero when the score returns to zero', function (): void {
    $this->user->like();

    $likeCounter = EngagementCounter::query()->where('type', EngagementTypes::Like->value)->firstOrFail();
    expect((float) $likeCounter->hot_score)->toBe(0.0);

    config(['engageify.types' => Vote::class]);

    $this->user->engage(Vote::Up);
    $this->user->disengage(Vote::Up);

    $voteCounter = EngagementCounter::query()->where('type', Vote::Up->value)->firstOrFail();
    expect((float) $voteCounter->hot_score)->toBe(0.0);
});

test('top(period) ranks engageables created within the window; top(all) ignores the window', function (): void {
    config(['engageify.types' => Vote::class]);

    $recent = User::factory()->createOne();
    $old = User::factory()->createOne(['created_at' => now()->subMonths(2)]);

    $recent->engage(Vote::Up);
    $old->engage(Vote::Up);

    $thisWeek = User::query()->top('week')->pluck('id');
    $allTime = User::query()->top('all')->pluck('id');

    expect($thisWeek->all())->toContain($recent->id)
        ->and($thisWeek->all())->not->toContain($old->id)
        ->and($allTime->all())->toContain($old->id);
});

test('orderByBayesian ranks honestly using the global mean, with a configurable m', function (): void {
    config(['engageify.types' => Rating::class]);

    $sure = User::factory()->createOne();
    $unsure = User::factory()->createOne();
    $low = User::factory()->createOne();

    $raters = User::factory()->count(3)->create();

    foreach ($raters as $rater) {
        $this->actingAs($rater);
        $sure->engage(Rating::Stars, 5);
    }

    $this->actingAs($raters->first());
    $unsure->engage(Rating::Stars, 5);
    $low->engage(Rating::Stars, 1);

    $this->actingAs($raters->get(1));
    $low->engage(Rating::Stars, 1);

    $ordered = User::query()->orderByBayesian(Rating::Stars, 5)->pluck('id')->all();

    expect(array_search($sure->id, $ordered, true))
        ->toBeLessThan(array_search($unsure->id, $ordered, true));
});

test('the counter columns are exposed for custom ranking scopes', function (): void {
    config(['engageify.types' => Vote::class]);

    $post = User::factory()->createOne();
    $post->engage(Vote::Up);

    $counter = EngagementCounter::query()
        ->where('engagementable_id', $post->id)
        ->where('type', Vote::Up->value)
        ->firstOrFail();

    expect($counter->count)->toBe(1)
        ->and((float) $counter->sum_value)->toBe(1.0)
        ->and((float) $counter->hot_score)->toBeGreaterThan(0.0);
});

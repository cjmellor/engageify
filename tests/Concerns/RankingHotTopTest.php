<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Rating;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Vote;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

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

test('hot() ranks by net score, so downvotes cool an item down', function (): void {
    config(['engageify.types' => Vote::class, 'engageify.allow_multiple_engagements' => true]);

    $contested = User::factory()->createOne();
    $liked = User::factory()->createOne();

    foreach (range(1, 20) as $ignored) {
        $contested->engage(Vote::Up);
    }

    foreach (range(1, 19) as $ignored) {
        $contested->engage(Vote::Down);
    }

    foreach (range(1, 5) as $ignored) {
        $liked->engage(Vote::Up);
    }

    $hot = fn (): array => User::query()->whereIn('id', [$contested->id, $liked->id])->hot()->pluck('id')->all();

    expect($hot())->toBe([$liked->id, $contested->id]);

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($hot())->toBe([$liked->id, $contested->id]);
});

test('hot_score carries the age term for a binary Verb and when the net score returns to zero', function (): void {
    $this->user->like();

    $likeCounter = EngagementCounter::query()->where('type', EngagementTypes::Like->value)->firstOrFail();
    expect((float) $likeCounter->hot_score)->toBeGreaterThan(0.0);

    config(['engageify.types' => Vote::class]);

    $this->user->engage(Vote::Up);
    $this->user->disengage(Vote::Up);

    $voteCounter = EngagementCounter::query()->where('type', Vote::Up->value)->firstOrFail();
    expect((float) $voteCounter->hot_score)->toBeGreaterThan(0.0);
});

test('hot() ranks a net-zero item between upvoted and downvoted items of the same age', function (): void {
    config(['engageify.types' => Vote::class, 'engageify.allow_multiple_engagements' => true]);

    $createdAt = now();

    $upvoted = User::factory()->createOne(['created_at' => $createdAt]);
    $neutral = User::factory()->createOne(['created_at' => $createdAt]);
    $downvoted = User::factory()->createOne(['created_at' => $createdAt]);

    foreach (range(1, 10) as $ignored) {
        $upvoted->engage(Vote::Up);
        $downvoted->engage(Vote::Down);
    }

    $neutral->engage(Vote::Up);
    $neutral->engage(Vote::Down);

    $hot = fn (): array => User::query()->whereIn('id', [$upvoted->id, $neutral->id, $downvoted->id])->hot()->pluck('id')->all();

    expect($hot())->toBe([$upvoted->id, $neutral->id, $downvoted->id]);

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($hot())->toBe([$upvoted->id, $neutral->id, $downvoted->id]);
});

test('hot() ranks items that only have binary Verbs by age, newest first', function (): void {
    $older = User::factory()->createOne(['created_at' => now()->subDays(3)]);
    $newer = User::factory()->createOne(['created_at' => now()->subDay()]);
    $viewedOnly = User::factory()->createOne(['created_at' => now()]);

    $older->like();
    $newer->like();
    $viewedOnly->recordView();

    $hot = fn (): array => User::query()->whereIn('id', [$older->id, $newer->id, $viewedOnly->id])->hot()->pluck('id')->all();

    expect($hot())->toBe([$newer->id, $older->id, $viewedOnly->id]);

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($hot())->toBe([$newer->id, $older->id, $viewedOnly->id]);
});

test('an item whose engagements were all withdrawn has no hot_score, live and after a recount', function (): void {
    $withdrawn = User::factory()->createOne(['created_at' => now()]);
    $liked = User::factory()->createOne(['created_at' => now()->subYear()]);

    $withdrawn->like();
    $withdrawn->unlike();
    $liked->like();

    $hot = fn (): array => User::query()->whereIn('id', [$withdrawn->id, $liked->id])->hot()->pluck('id')->all();

    expect((float) $withdrawn->engagementCounters()->max('hot_score'))->toBe(0.0)
        ->and($hot())->toBe([$liked->id, $withdrawn->id]);

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($hot())->toBe([$liked->id, $withdrawn->id]);
});

test('hot() ranks a never-engaged item level with one whose engagements were all withdrawn', function (): void {
    $withdrawn = User::factory()->createOne();
    $untouched = User::factory()->createOne();

    $withdrawn->like();
    $withdrawn->unlike();

    $scoped = fn () => User::query()->whereIn('id', [$withdrawn->id, $untouched->id]);

    expect($scoped()->hot('asc')->orderBy('id')->pluck('id')->all())->toBe([$withdrawn->id, $untouched->id])
        ->and($scoped()->hot()->orderByDesc('id')->pluck('id')->all())->toBe([$untouched->id, $withdrawn->id]);
});

test('top() and hot() rank by net vote score and leave rating sums out', function (): void {
    config(['engageify.types' => [Vote::class, Rating::class], 'engageify.allow_multiple_engagements' => true]);

    $createdAt = now();

    $wellRated = User::factory()->createOne(['created_at' => $createdAt]);
    $wellVoted = User::factory()->createOne(['created_at' => $createdAt]);

    $wellRated->engage(Vote::Up);
    $wellRated->engage(Rating::Stars, 5);

    $wellVoted->engage(Vote::Up);
    $wellVoted->engage(Vote::Up);

    $scoped = fn () => User::query()->whereIn('id', [$wellRated->id, $wellVoted->id]);

    expect($scoped()->top()->pluck('id')->all())->toBe([$wellVoted->id, $wellRated->id])
        ->and($scoped()->hot()->pluck('id')->all())->toBe([$wellVoted->id, $wellRated->id]);

    $this->artisan('engageify:recount')->assertSuccessful();

    expect($scoped()->hot()->pluck('id')->all())->toBe([$wellVoted->id, $wellRated->id]);
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

test('record updates the counter inside its own transaction so callers need not', function (): void {
    config(['engageify.types' => Vote::class]);

    $post = User::factory()->createOne();

    EngagementCounter::record(engageable: $post, type: Vote::Up->value, countDelta: 1, valueDelta: 1.0);

    $levels = [];
    DB::listen(function (QueryExecuted $query) use (&$levels): void {
        $levels[] = $query->connection->transactionLevel();
    });

    EngagementCounter::record(engageable: $post, type: Vote::Up->value, countDelta: 1, valueDelta: 1.0);

    expect(max($levels))->toBeGreaterThan(1);
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

<?php

declare(strict_types=1);

use Cjmellor\Engageify\Exceptions\InvalidViewPeriodException;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Models\ViewBucket;
use Cjmellor\Engageify\Tests\Fixtures\User;

test('recordView counts once per fingerprint per cooldown; repeats within the window do not count', function (): void {
    $post = User::factory()->createOne();

    $this->actingAs($this->user);
    $post->recordView();
    $post->recordView();

    expect($post->viewCount())->toBe(1);
});

test('distinct viewers are counted separately', function (): void {
    $post = User::factory()->createOne();
    $viewerA = User::factory()->createOne();
    $viewerB = User::factory()->createOne();

    $this->actingAs($viewerA);
    $post->recordView();

    $this->actingAs($viewerB);
    $post->recordView();

    expect($post->viewCount())->toBe(2);
});

test('recording a view writes no per-view engagement row', function (): void {
    $post = User::factory()->createOne();

    $this->actingAs($this->user);
    $post->recordView();

    $this->assertDatabaseCount(Engagement::class, 0);

    expect($post->viewCount())->toBe(1);
});

test('with buckets off only the lifetime total is kept', function (): void {
    config(['engageify.views.buckets' => false]);

    $post = User::factory()->createOne();

    $this->actingAs($this->user);
    $post->recordView();

    $this->assertDatabaseCount(ViewBucket::class, 0);

    expect($post->viewCount())->toBe(1);
});

test('with buckets on daily rows are written and viewsInLast works', function (): void {
    config(['engageify.views.buckets' => true]);

    $post = User::factory()->createOne();

    $this->actingAs($this->user);
    $post->recordView();

    $this->assertDatabaseCount(ViewBucket::class, 1);

    expect($post->viewsInLast(7))->toBe(1);
});

test('viewsInLast counts exactly N daily buckets ending today', function (): void {
    $post = User::factory()->createOne();

    ViewBucket::query()->create([
        'viewable_type' => $post->getMorphClass(),
        'viewable_id' => $post->id,
        'date' => today(),
        'count' => 1,
    ]);

    ViewBucket::query()->create([
        'viewable_type' => $post->getMorphClass(),
        'viewable_id' => $post->id,
        'date' => today()->subDays(7),
        'count' => 10,
    ]);

    expect($post->viewsInLast(7))->toBe(1);
});

test('orderByMostViewed ranks by the lifetime total', function (): void {
    $popular = User::factory()->createOne();
    $quiet = User::factory()->createOne();

    foreach (User::factory()->count(3)->create() as $viewer) {
        $this->actingAs($viewer);
        $popular->recordView();
    }

    $this->actingAs($this->user);
    $quiet->recordView();

    $ordered = User::query()->whereIn('id', [$popular->id, $quiet->id])->orderByMostViewed()->pluck('id');

    expect($ordered->all())->toBe([$popular->id, $quiet->id]);
});

test('orderByMostViewed rejects a period outside the allowed set', function (): void {
    User::query()->orderByMostViewed('fortnight');
})->throws(InvalidViewPeriodException::class);

test('orderByMostViewed window excludes buckets on the far edge of the period', function (): void {
    $recent = User::factory()->createOne();
    $edge = User::factory()->createOne();

    ViewBucket::query()->create([
        'viewable_type' => $recent->getMorphClass(),
        'viewable_id' => $recent->id,
        'date' => today(),
        'count' => 1,
    ]);

    ViewBucket::query()->create([
        'viewable_type' => $edge->getMorphClass(),
        'viewable_id' => $edge->id,
        'date' => today()->subDays(7),
        'count' => 100,
    ]);

    $ordered = User::query()->whereIn('id', [$recent->id, $edge->id])->orderByMostViewed('week')->pluck('id');

    expect($ordered->all())->toBe([$recent->id, $edge->id]);
});

test('orderByMostViewed with a period ranks by views within the window only', function (): void {
    config(['engageify.views.buckets' => true]);

    $popular = User::factory()->createOne();
    $quiet = User::factory()->createOne();

    $viewers = User::factory()->count(2)->create();
    foreach ($viewers as $viewer) {
        $this->actingAs($viewer);
        $popular->recordView();
    }

    $this->actingAs($this->user);
    $quiet->recordView();

    ViewBucket::query()->create([
        'viewable_type' => $quiet->getMorphClass(),
        'viewable_id' => $quiet->id,
        'date' => now()->subMonths(2),
        'count' => 100,
    ]);

    $ordered = User::query()->whereIn('id', [$popular->id, $quiet->id])->orderByMostViewed('week')->pluck('id');

    expect($ordered->all())->toBe([$popular->id, $quiet->id]);
});

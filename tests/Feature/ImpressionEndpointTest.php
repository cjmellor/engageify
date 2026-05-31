<?php

declare(strict_types=1);

use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Support\ImpressionRecorder;
use Cjmellor\Engageify\Support\ImpressionToken;
use Cjmellor\Engageify\Tests\Fixtures\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

function impressionCount(User $model): int
{
    return (int) EngagementCounter::query()
        ->where('engagementable_type', $model->getMorphClass())
        ->where('engagementable_id', $model->id)
        ->where('type', ImpressionRecorder::TYPE)
        ->sum('count');
}

test('@impression renders a data attribute carrying a valid signed token', function (): void {
    $model = User::factory()->createOne();

    $html = Blade::render('@impression($model)', ['model' => $model]);

    expect($html)->toStartWith('data-engageify-impression="');

    preg_match('/data-engageify-impression="([^"]+)"/', $html, $matches);

    expect(ImpressionToken::verify($matches[1]))->toBe(['type' => $model->getMorphClass(), 'id' => (string) $model->id]);
});

test('the endpoint accepts a valid token and counts one impression', function (): void {
    $model = User::factory()->createOne();

    $this->postJson(route('engageify.impressions'), ['token' => ImpressionToken::generate($model, 3600)])
        ->assertNoContent();

    expect(impressionCount($model))->toBe(1);
});

test('the endpoint dedups repeat impressions within the cooldown', function (): void {
    $model = User::factory()->createOne();
    $token = ImpressionToken::generate($model, 3600);

    $this->postJson(route('engageify.impressions'), ['token' => $token])->assertNoContent();
    $this->postJson(route('engageify.impressions'), ['token' => $token])->assertNoContent();

    expect(impressionCount($model))->toBe(1);
});

test('the endpoint rejects a forged token without counting', function (): void {
    $model = User::factory()->createOne();

    $this->postJson(route('engageify.impressions'), ['token' => 'forged.token'])
        ->assertForbidden();

    expect(impressionCount($model))->toBe(0);
});

test('the impression endpoint is throttled', function (): void {
    $route = Route::getRoutes()->getByName('engageify.impressions');

    expect($route->gatherMiddleware())->toContain('throttle:60,1');
});

<?php

declare(strict_types=1);

use Cjmellor\Engageify\Support\ImpressionToken;
use Cjmellor\Engageify\Tests\Fixtures\User;

test('a generated token verifies and returns the model type and id', function (): void {
    $model = User::factory()->createOne();

    $verified = ImpressionToken::verify(ImpressionToken::generate($model, 3600));

    expect($verified)->toBe(['type' => $model->getMorphClass(), 'id' => (string) $model->id]);
});

test('a tampered token is rejected', function (): void {
    $model = User::factory()->createOne();

    $token = ImpressionToken::generate($model, 3600);

    expect(ImpressionToken::verify($token.'tampered'))->toBeNull();
});

test('an expired token is rejected', function (): void {
    $model = User::factory()->createOne();

    expect(ImpressionToken::verify(ImpressionToken::generate($model, -10)))->toBeNull();
});

test('a malformed token is rejected', function (): void {
    expect(ImpressionToken::verify('not-a-real-token'))->toBeNull();
});

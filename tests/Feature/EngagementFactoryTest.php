<?php

declare(strict_types=1);

use Cjmellor\Engageify\Enums\EngagementTypes;
use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Tests\Fixtures\User;

it('builds a persistable engagement from the configured user model', function (): void {
    config()->set('engageify.users.model', User::class);

    $engagement = Engagement::factory()->like()->create();

    expect($engagement->exists)->toBeTrue()
        ->and($engagement->type)->toBe(EngagementTypes::Like)
        ->and($engagement->user_id)->not->toBeNull()
        ->and($engagement->engagementable_type)->toBe((new User)->getMorphClass());
});

it('honours the configured user model rather than a hardcoded fixture', function (): void {
    config()->set('engageify.users.model', User::class);

    $engagement = Engagement::factory()->upvote()->create();

    expect($engagement->engagementable_type)->toBe((new User)->getMorphClass())
        ->and(User::query()->whereKey($engagement->user_id)->exists())->toBeTrue();
});

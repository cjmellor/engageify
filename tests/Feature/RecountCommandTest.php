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

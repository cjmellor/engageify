<?php

declare(strict_types=1);

use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Support\ImpressionRecorder;
use Cjmellor\Engageify\Tests\Fixtures\User;

function recordedImpressions(User $model): int
{
    return (int) EngagementCounter::query()
        ->where('engagementable_type', $model->getMorphClass())
        ->where('engagementable_id', $model->getKey())
        ->where('type', ImpressionRecorder::TYPE)
        ->sum('count');
}

it('dedupes impressions on the impressions cooldown, independent of the views cooldown', function (): void {
    config()->set('engageify.users.model', User::class);
    config()->set('engageify.views.cooldown', 0);
    config()->set('engageify.impressions.cooldown', 3600);
    $model = User::factory()->createOne();

    ImpressionRecorder::record($model->getMorphClass(), $model->getKey());
    ImpressionRecorder::record($model->getMorphClass(), $model->getKey());

    expect(recordedImpressions($model))->toBe(1);
});

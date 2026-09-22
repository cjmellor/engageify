<?php

declare(strict_types=1);

use Cjmellor\Engageify\Models\Engagement;
use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Tests\Fixtures\Enums\Emoji;

beforeEach(function (): void {
    config(['engageify.types' => Emoji::class]);

    $this->actingAs($this->user);
});

test('an emoji-valued Verb keeps one engagement and one counter per emoji', function (): void {
    foreach (Emoji::cases() as $emoji) {
        $this->user->engage($emoji);
    }

    expect(Engagement::query()->count())->toBe(count(Emoji::cases()))
        ->and(EngagementCounter::query()->count())->toBe(count(Emoji::cases()))
        ->and(EngagementCounter::query()->pluck('count', 'type')->all())
        ->toEqualCanonicalizing(
            collect(Emoji::cases())->mapWithKeys(fn (Emoji $emoji): array => [$emoji->value => 1])->all(),
        );
});

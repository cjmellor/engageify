<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Cjmellor\Engageify\Models\EngagementCounter;
use Illuminate\Support\Facades\Cache;

class ImpressionRecorder
{
    public const TYPE = 'impression';

    public static function record(string $morphType, int|string $morphId): void
    {
        $fingerprint = Fingerprint::make(
            userId: auth()->id(),
            ip: request()->ip(),
            userAgent: request()->userAgent(),
        );

        $key = 'impression:'.$fingerprint.':'.$morphType.':'.$morphId;

        if (! Cache::add(key: $key, value: true, ttl: (int) config(key: 'engageify.impressions.cooldown'))) {
            return;
        }

        EngagementCounter::tally(engagementableType: $morphType, engagementableId: $morphId, type: self::TYPE);
    }
}

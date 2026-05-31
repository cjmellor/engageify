<?php

declare(strict_types=1);

namespace Cjmellor\Engageify\Support;

use Cjmellor\Engageify\Models\EngagementCounter;
use Cjmellor\Engageify\Models\ViewBucket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ViewRecorder
{
    public const TYPE = 'view';

    public static function record(Model $viewable): void
    {
        $fingerprint = Fingerprint::make(
            userId: auth()->id(),
            ip: request()->ip(),
            userAgent: request()->userAgent(),
        );

        $key = 'view:'.$fingerprint.':'.$viewable->getMorphClass().':'.$viewable->getKey();

        if (! Cache::add(key: $key, value: true, ttl: (int) config(key: 'engageify.views.cooldown'))) {
            return;
        }

        EngagementCounter::record(engageable: $viewable, type: self::TYPE, countDelta: 1, valueDelta: 0);

        if (config(key: 'engageify.views.buckets')) {
            ViewBucket::record(viewable: $viewable, date: today());
        }
    }
}

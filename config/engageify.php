<?php

declare(strict_types=1);
use Cjmellor\Engageify\Enums\EngagementTypes;

return [
    /*
    |--------------------------------------------------------------------------
    | Engagement Types
    |--------------------------------------------------------------------------
    |
    | The string-backed enum that defines the available engagement Verbs. Ship
    | your own enum implementing Cjmellor\Engageify\Contracts\EngagementType to
    | extend the vocabulary — no migration required.
    |
    */
    'types' => EngagementTypes::class,

    /*
    |--------------------------------------------------------------------------
    | User Configuration
    |--------------------------------------------------------------------------
    |
    | Customise the values used to identify the user in the database.
    |
    */
    'users' => [
        'foreign_key' => 'user_id',
        'model' => 'App\Models\User',
        'table' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Multiple Engagements
    |--------------------------------------------------------------------------
    |
    | Allow multiple engagements of the same type.
    |
    */
    'allow_multiple_engagements' => env(key: 'ENGAGEIFY_MULTIPLE_ENGAGEMENTS', default: false),

    /*
    |--------------------------------------------------------------------------
    | Bayesian Minimum
    |--------------------------------------------------------------------------
    |
    | The prior weight (m) used by bayesianAverage(): the number of "average"
    | votes an Engageable is seeded with before its own ratings outweigh the
    | global mean. Higher values pull low-count items harder toward the mean.
    |
    */
    'bayesian_minimum' => env(key: 'ENGAGEIFY_BAYESIAN_MINIMUM', default: 5),

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | View tracking is a count-only subsystem. `cooldown` is the number of
    | seconds a fingerprint is deduplicated for (repeat views inside the window
    | don't count). `buckets` opts into a per-day time-series table powering
    | viewsInLast()/orderByMostViewed($period).
    |
    | CAUTION: buckets cannot be backfilled — while off, only the lifetime total
    | is kept, so enable it from day one if you may ever want windows/trending.
    |
    */
    'views' => [
        'cooldown' => env(key: 'ENGAGEIFY_VIEW_COOLDOWN', default: 3600),
        'buckets' => env(key: 'ENGAGEIFY_VIEW_BUCKETS', default: false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Impressions
    |--------------------------------------------------------------------------
    |
    | Viewport-impression tracking. `endpoint` is the route the browser posts a
    | signed token to; `throttle` is its rate limit (Laravel `maxAttempts,
    | decayMinutes`); `token_ttl` is how long a signed impression token stays
    | valid, in seconds.
    |
    */
    'impressions' => [
        'endpoint' => env(key: 'ENGAGEIFY_IMPRESSION_ENDPOINT', default: 'engageify/impressions'),
        'throttle' => env(key: 'ENGAGEIFY_IMPRESSION_THROTTLE', default: '60,1'),
        'token_ttl' => env(key: 'ENGAGEIFY_IMPRESSION_TOKEN_TTL', default: 86400),

        /*
        | How long (in seconds) a fingerprint is de-duplicated for — repeat
        | impressions of the same element by the same viewer inside this window
        | are not counted. Independent of the `views.cooldown` above.
        */
        'cooldown' => env(key: 'ENGAGEIFY_IMPRESSION_COOLDOWN', default: 3600),

        /*
        | Inject the impression-tracking script into HTML responses. Off by
        | default — rewriting every HTML response must be a deliberate switch.
        | `threshold` is the fraction of the element that must be visible and
        | `dwell` the milliseconds it must stay visible before it counts (IAB
        | standard: 50% for 1000ms).
        */
        'inject_script' => env(key: 'ENGAGEIFY_IMPRESSION_INJECT', default: false),
        'threshold' => env(key: 'ENGAGEIFY_IMPRESSION_THRESHOLD', default: 0.5),
        'dwell' => env(key: 'ENGAGEIFY_IMPRESSION_DWELL', default: 1000),
    ],
];

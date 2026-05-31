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
];

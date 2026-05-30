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
    | Allow Caching
    |--------------------------------------------------------------------------
    |
    | The engagement counts can be cached to improve performance.
    |
    */
    'allow_caching' => env(key: 'ENGAGEIFY_ALLOW_CACHING', default: false),
    'cache_duration' => env(key: 'ENGAGEIFY_CACHE_DURATION', default: 3600),

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
];

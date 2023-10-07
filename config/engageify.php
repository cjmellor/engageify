<?php

return [
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
        'model' => App\Models\User::class,
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
];

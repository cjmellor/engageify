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
];

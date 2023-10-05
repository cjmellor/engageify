<?php

namespace Cjmellor\Engageify;

use Illuminate\Support\ServiceProvider;

class EngageifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/engageify.php' => config_path(path: 'engageify.php'),
        ], groups: 'engageify-config');

        $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');
    }
}

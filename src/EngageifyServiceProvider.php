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

        if (method_exists(object_or_class: $this, method: 'publishesMigrations')) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path(path: 'migrations'),
            ], groups: 'engageify-migrations');
        } else {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path(path: 'migrations'),
            ], groups: 'engageify-migrations');

            $this->loadMigrationsFrom(paths: __DIR__.'/../database/migrations');
        }
    }
}

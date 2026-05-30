<?php

declare(strict_types=1);

namespace Cjmellor\Engageify;

use Illuminate\Support\ServiceProvider;

class EngageifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/engageify.php',
            key: 'engageify',
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/engageify.php' => config_path(path: 'engageify.php'),
        ], groups: 'engageify-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path(path: 'migrations'),
        ], groups: 'engageify-migrations');
    }
}
